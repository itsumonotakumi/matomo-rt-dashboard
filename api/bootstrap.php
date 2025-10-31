<?php
/**
 * API共通ブートストラップ
 *
 * 全APIエンドポイントで最初にrequireされるファイル
 */

// エラーレポート設定（本番環境では非表示）
error_reporting(E_ALL);
ini_set('display_errors', '0');

// 設定を読み込み
$config = require __DIR__ . '/../config/config.php';

// ヘルパー関数

/**
 * エラーログを記録
 */
function logError(string $message, array $context = []): void
{
    global $config;

    $logFile = $config['ERROR_LOG_FILE'];
    $maxSize = $config['ERROR_LOG_MAX_SIZE'];

    // ログファイルサイズチェック（1MB超でtruncate）
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        file_put_contents($logFile, '');
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[{$timestamp}] {$message} {$contextStr}\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * JSON形式でエラーレスポンスを返す
 */
function jsonError(string $code, string $message, int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'error' => [
            'code' => $code,
            'message' => $message,
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * JSON形式で成功レスポンスを返す
 */
function jsonSuccess(array $data): void
{
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * キャッシュを取得（TTL内なら返す、なければnull）
 */
function getCache(string $cacheKey, int $ttl): ?array
{
    global $config;

    $cacheFile = $config['CACHE_PATH'] . '/' . $cacheKey . '.json';

    if (!file_exists($cacheFile)) {
        return null;
    }

    $mtime = filemtime($cacheFile);
    $age = time() - $mtime;

    if ($age > $ttl) {
        return null; // 期限切れ
    }

    $json = file_get_contents($cacheFile);
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logError("キャッシュ破損: {$cacheKey}");
        return null;
    }

    return $data;
}

/**
 * キャッシュを保存
 */
function setCache(string $cacheKey, array $data): bool
{
    global $config;

    $cacheFile = $config['CACHE_PATH'] . '/' . $cacheKey . '.json';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // ファイルロックして書き込み
    $fp = fopen($cacheFile, 'c');
    if (!$fp) {
        logError("キャッシュファイルを開けません: {$cacheKey}");
        return false;
    }

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return true;
}

/**
 * 最後のキャッシュを取得（TTL無視）
 */
function getLastCache(string $cacheKey): ?array
{
    global $config;

    $cacheFile = $config['CACHE_PATH'] . '/' . $cacheKey . '.json';

    if (!file_exists($cacheFile)) {
        return null;
    }

    $json = file_get_contents($cacheFile);
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}

/**
 * Matomo APIを呼び出す
 */
function callMatomoApi(string $method, array $params = []): array
{
    global $config;

    $baseUrl = rtrim($config['MATOMO_URL'], '/');
    $tokenAuth = $config['TOKEN_AUTH'];
    $timeout = $config['API_TIMEOUT'];
    $maxRetries = $config['API_MAX_RETRIES'];
    $userAgent = $config['USER_AGENT'];

    if (empty($baseUrl) || empty($tokenAuth)) {
        throw new Exception('Matomo接続情報が設定されていません');
    }

    // パラメータを構築
    $params['module'] = 'API';
    $params['method'] = $method;
    $params['format'] = 'JSON';
    $params['token_auth'] = $tokenAuth;

    $url = $baseUrl . '/index.php?' . http_build_query($params);

    // リトライロジック
    $attempt = 0;
    $lastError = null;

    while ($attempt <= $maxRetries) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'user_agent' => $userAgent,
                    'ignore_errors' => true,
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                throw new Exception('API呼び出しに失敗しました');
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('APIレスポンスのJSONパースに失敗しました');
            }

            // Matomoがエラーを返した場合
            if (isset($data['result']) && $data['result'] === 'error') {
                throw new Exception($data['message'] ?? 'Matomoがエラーを返しました');
            }

            return $data;

        } catch (Exception $e) {
            $lastError = $e->getMessage();
            $attempt++;

            if ($attempt <= $maxRetries) {
                usleep(500000); // 0.5秒待機
            }
        }
    }

    throw new Exception("API呼び出し失敗（{$maxRetries}回リトライ）: {$lastError}");
}

/**
 * 入力値のバリデーション
 */
function validateConfig(array $input): array
{
    $errors = [];

    // MATOMO_URL
    if (empty($input['MATOMO_URL'])) {
        $errors[] = 'Matomo URLは必須です';
    } elseif (!filter_var($input['MATOMO_URL'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Matomo URLの形式が不正です';
    } elseif (!preg_match('/^https?:\/\//i', $input['MATOMO_URL'])) {
        $errors[] = 'Matomo URLはhttp://またはhttps://で始まる必要があります';
    }

    // TOKEN_AUTH
    if (empty($input['TOKEN_AUTH'])) {
        $errors[] = 'トークンは必須です';
    } elseif (strlen($input['TOKEN_AUTH']) < 32) {
        $errors[] = 'トークンの形式が不正です（32文字以上必要）';
    }

    // SITE_IDS
    if (empty($input['SITE_IDS'])) {
        $errors[] = 'サイトIDは最低1つ必要です';
    } elseif (!is_array($input['SITE_IDS'])) {
        $errors[] = 'サイトIDは配列である必要があります';
    } else {
        if (count($input['SITE_IDS']) > 10) {
            $errors[] = 'サイトIDは最大10個までです';
        }

        foreach ($input['SITE_IDS'] as $id) {
            if (!is_int($id) && !ctype_digit($id)) {
                $errors[] = 'サイトIDは整数である必要があります';
                break;
            }
            $idInt = (int)$id;
            if ($idInt < 1 || $idInt > 99999) {
                $errors[] = 'サイトIDは1〜99999の範囲である必要があります';
                break;
            }
        }
    }

    // TIMEZONE
    if (!empty($input['TIMEZONE'])) {
        if (!in_array($input['TIMEZONE'], timezone_identifiers_list())) {
            $errors[] = '無効なタイムゾーンです';
        }
    }

    // TTL
    if (isset($input['CACHE_TTL_ACTIVE_30'])) {
        $ttl = (int)$input['CACHE_TTL_ACTIVE_30'];
        if ($ttl < 10 || $ttl > 3600) {
            $errors[] = 'TTL（直近30分）は10〜3600秒の範囲である必要があります';
        }
    }

    if (isset($input['CACHE_TTL_HOURLY'])) {
        $ttl = (int)$input['CACHE_TTL_HOURLY'];
        if ($ttl < 10 || $ttl > 3600) {
            $errors[] = 'TTL（時間帯別）は10〜3600秒の範囲である必要があります';
        }
    }

    return $errors;
}

/**
 * ISO 8601形式で現在時刻を返す
 */
function getCurrentTimestamp(): string
{
    return date('c'); // ISO 8601
}

// 例外ハンドラ（JSONで返す）
set_exception_handler(function ($exception) {
    logError('Uncaught exception: ' . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);

    jsonError('INTERNAL_ERROR', 'サーバーエラーが発生しました', 200);
});

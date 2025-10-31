<?php
/**
 * ヘルスチェックAPI
 *
 * GET /api/health.php
 *
 * レスポンス例:
 * {
 *   "ok": true,
 *   "checks": {
 *     "config": "ok",
 *     "storage": "ok",
 *     "cache": "ok",
 *     "matomo": "ok"
 *   }
 * }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/_guard.php';

$checks = [];
$allOk = true;

// 設定チェック
try {
    if (empty($config['MATOMO_URL']) || empty($config['TOKEN_AUTH']) || empty($config['SITE_IDS'])) {
        $checks['config'] = 'error: 設定が不完全です';
        $allOk = false;
    } else {
        $checks['config'] = 'ok';
    }
} catch (Exception $e) {
    $checks['config'] = 'error: ' . $e->getMessage();
    $allOk = false;
}

// ストレージ書き込みチェック
try {
    $testFile = $config['STORAGE_PATH'] . '/.health_test';
    $testData = 'test-' . time();

    if (file_put_contents($testFile, $testData) === false) {
        throw new Exception('書き込み失敗');
    }

    if (file_get_contents($testFile) !== $testData) {
        throw new Exception('読み込み失敗');
    }

    @unlink($testFile);
    $checks['storage'] = 'ok';

} catch (Exception $e) {
    $checks['storage'] = 'error: ' . $e->getMessage();
    $allOk = false;
}

// キャッシュディレクトリチェック
try {
    if (!is_dir($config['CACHE_PATH'])) {
        throw new Exception('キャッシュディレクトリが存在しません');
    }

    if (!is_writable($config['CACHE_PATH'])) {
        throw new Exception('キャッシュディレクトリに書き込めません');
    }

    $checks['cache'] = 'ok';

} catch (Exception $e) {
    $checks['cache'] = 'error: ' . $e->getMessage();
    $allOk = false;
}

// Matomo接続チェック（設定があれば）
if (!empty($config['MATOMO_URL']) && !empty($config['TOKEN_AUTH']) && !empty($config['SITE_IDS'])) {
    try {
        $testIdSite = $config['SITE_IDS'][0];
        $data = callMatomoApi('Live.getCounters', [
            'idSite' => $testIdSite,
            'lastMinutes' => 1,
        ]);

        $checks['matomo'] = 'ok';

    } catch (Exception $e) {
        $checks['matomo'] = 'error: ' . $e->getMessage();
        $allOk = false;
    }
} else {
    $checks['matomo'] = 'skipped: 設定なし';
}

$response = [
    'ok' => $allOk,
    'checks' => $checks,
    'timestamp' => getCurrentTimestamp(),
];

if (!$allOk) {
    http_response_code(500);
}

jsonSuccess($response);

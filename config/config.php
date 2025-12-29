<?php
/**
 * 設定ローダー
 *
 * 優先順位: settings.json > .env > defaults.php
 */

// デフォルト設定を読み込み
$defaults = require __DIR__ . '/defaults.php';

// 設定をマージする関数
function mergeConfig(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if ($value !== null && $value !== '') {
            $base[$key] = $value;
        }
    }
    return $base;
}

// .envファイルの簡易パーサー（Composer不要）
function loadEnv(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];

    foreach ($lines as $line) {
        // コメント行をスキップ
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // KEY=VALUE形式をパース
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // クォートを除去
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            $env[$key] = $value;
        }
    }

    return $env;
}

// .envから読み込み
$envPath = __DIR__ . '/../.env';
$envConfig = [];

if (file_exists($envPath)) {
    $env = loadEnv($envPath);

    // SITE_IDSはカンマ区切りを配列に変換
    if (isset($env['SITE_IDS'])) {
        $siteIds = array_filter(array_map('trim', explode(',', $env['SITE_IDS'])));
        $env['SITE_IDS'] = array_map('intval', $siteIds);
    }

    // 数値型に変換
    foreach (['CACHE_TTL_ACTIVE_30', 'CACHE_TTL_HOURLY', 'API_TIMEOUT', 'API_MAX_RETRIES', 'MAX_SITE_IDS', 'MAX_LOGIN_ATTEMPTS', 'LOGIN_COOLDOWN_SECONDS'] as $key) {
        if (isset($env[$key])) {
            $env[$key] = (int)$env[$key];
        }
    }

    // ブール型に変換
    if (isset($env['DEBUG_MODE'])) {
        $env['DEBUG_MODE'] = filter_var($env['DEBUG_MODE'], FILTER_VALIDATE_BOOLEAN);
    }

    $envConfig = $env;
}

// settings.jsonから読み込み
$settingsPath = $defaults['SETTINGS_FILE'];
$settingsConfig = [];

if (file_exists($settingsPath)) {
    $json = file_get_contents($settingsPath);
    $settings = json_decode($json, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($settings)) {
        $settingsConfig = $settings;
    }
}

// 設定をマージ（優先順位: settings.json > .env > defaults）
$config = mergeConfig($defaults, $envConfig);
$config = mergeConfig($config, $settingsConfig);

// タイムゾーンを設定
if (!empty($config['TIMEZONE'])) {
    date_default_timezone_set($config['TIMEZONE']);
}

return $config;

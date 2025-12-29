<?php
/**
 * デフォルト設定値
 *
 * このファイルは初期値を提供します。
 * 実際の設定は storage/settings.json または .env で上書きされます。
 */

return [
    // デバッグモード（本番環境では必ずfalseにすること）
    'DEBUG_MODE' => false,

    // Matomo接続情報
    'MATOMO_URL' => '',
    'TOKEN_AUTH' => '',
    'SITE_IDS' => [],

    // タイムゾーン
    'TIMEZONE' => 'Asia/Tokyo',

    // キャッシュTTL（秒）
    'CACHE_TTL_ACTIVE_30' => 60,      // 直近30分: 60秒
    'CACHE_TTL_HOURLY' => 300,         // 時間帯別: 5分

    // 管理画面認証（初期値）
    'ADMIN_USERNAME' => 'admin',
    'ADMIN_PASSWORD' => '', // 初回アクセス時に設定を強制

    // セキュリティ
    'SESSION_NAME' => 'MATOMO_RT_ADMIN',
    'CSRF_TOKEN_NAME' => 'csrf_token',

    // ログイン試行制限
    'MAX_LOGIN_ATTEMPTS' => 5,
    'LOGIN_COOLDOWN_SECONDS' => 300, // 5分

    // API設定
    'API_TIMEOUT' => 10,               // タイムアウト（秒）
    'API_MAX_RETRIES' => 2,            // リトライ回数
    'USER_AGENT' => 'MatomoRTDashboard/1.0',

    // サイトID制限
    'MAX_SITE_IDS' => 10,

    // ログファイル
    'ERROR_LOG_FILE' => __DIR__ . '/../storage/error.log',
    'ERROR_LOG_MAX_SIZE' => 1048576,   // 1MB

    // ストレージパス
    'STORAGE_PATH' => __DIR__ . '/../storage',
    'CACHE_PATH' => __DIR__ . '/../storage/cache',
    'SETTINGS_FILE' => __DIR__ . '/../storage/settings.json',

    // タイムゾーン一覧
    'AVAILABLE_TIMEZONES' => [
        'Asia/Tokyo',
        'Asia/Shanghai',
        'Asia/Seoul',
        'Asia/Singapore',
        'Asia/Bangkok',
        'Asia/Jakarta',
        'Asia/Manila',
        'Asia/Hong_Kong',
        'Asia/Taipei',
        'UTC',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Sao_Paulo',
        'Australia/Sydney',
    ],
];

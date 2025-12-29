<?php
/**
 * デバッグ用ファイル
 * 本番サーバーにアップロードして、エラー原因を調査
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>デバッグ情報</h1>";
echo "<pre>";

// 1. PHPバージョンチェック
echo "=== PHPバージョン ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "必須バージョン: 8.1以上\n";
if (version_compare(phpversion(), '8.1.0', '>=')) {
    echo "✅ OK\n";
} else {
    echo "❌ NG - PHPバージョンが古いです\n";
}
echo "\n";

// 2. 必要な設定チェック
echo "=== PHP設定 ===\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF') . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "\n";

// 3. ディレクトリ存在チェック
echo "=== ディレクトリチェック ===\n";
$dirs = [
    'storage' => __DIR__ . '/storage',
    'storage/cache' => __DIR__ . '/storage/cache',
    'config' => __DIR__ . '/config',
    'api' => __DIR__ . '/api',
    'admin' => __DIR__ . '/admin',
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = is_writable($path);
    echo "{$name}: ";
    echo $exists ? '✅ 存在' : '❌ なし';
    echo " | ";
    echo $writable ? '✅ 書き込み可' : '❌ 書き込み不可';
    echo "\n";
}
echo "\n";

// 4. ファイル存在チェック
echo "=== ファイルチェック ===\n";
$files = [
    'config/config.php' => __DIR__ . '/config/config.php',
    'config/defaults.php' => __DIR__ . '/config/defaults.php',
    'api/bootstrap.php' => __DIR__ . '/api/bootstrap.php',
    'admin/auth.php' => __DIR__ . '/admin/auth.php',
    'storage/settings.json' => __DIR__ . '/storage/settings.json',
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    echo "{$name}: ";
    echo $exists ? '✅ 存在' : '❌ なし';
    if ($exists) {
        echo " | ";
        echo $readable ? '✅ 読み込み可' : '❌ 読み込み不可';
    }
    echo "\n";
}
echo "\n";

// 5. 設定ファイル読み込みテスト
echo "=== 設定ファイル読み込みテスト ===\n";
try {
    if (file_exists(__DIR__ . '/config/config.php')) {
        $config = require __DIR__ . '/config/config.php';
        echo "✅ config.php の読み込み成功\n";
        echo "MATOMO_URL: " . ($config['MATOMO_URL'] ?: '(未設定)') . "\n";
        echo "TOKEN_AUTH: " . (empty($config['TOKEN_AUTH']) ? '(未設定)' : '(設定済み・' . strlen($config['TOKEN_AUTH']) . '文字)') . "\n";
        echo "SITE_IDS: " . (empty($config['SITE_IDS']) ? '(未設定)' : implode(',', $config['SITE_IDS'])) . "\n";
        echo "TIMEZONE: " . $config['TIMEZONE'] . "\n";
    } else {
        echo "❌ config.php が見つかりません\n";
    }
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    echo "ファイル: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo "\n";

// 6. bootstrap.php 読み込みテスト
echo "=== bootstrap.php 読み込みテスト ===\n";
try {
    if (file_exists(__DIR__ . '/api/bootstrap.php')) {
        require_once __DIR__ . '/api/bootstrap.php';
        echo "✅ bootstrap.php の読み込み成功\n";
    } else {
        echo "❌ bootstrap.php が見つかりません\n";
    }
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    echo "ファイル: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "スタックトレース:\n";
    echo $e->getTraceAsString() . "\n";
}
echo "\n";

// 7. セッションテスト
echo "=== セッションテスト ===\n";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "✅ セッション開始成功\n";
        echo "Session ID: " . session_id() . "\n";
    } else {
        echo "✅ セッション既に開始済み\n";
    }
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
}
echo "\n";

// 8. 書き込みテスト
echo "=== 書き込みテスト ===\n";
try {
    $testFile = __DIR__ . '/storage/.debug_test';
    $testData = 'test-' . time();

    if (file_put_contents($testFile, $testData) === false) {
        echo "❌ storage/ への書き込み失敗\n";
    } else {
        echo "✅ storage/ への書き込み成功\n";

        if (file_get_contents($testFile) === $testData) {
            echo "✅ storage/ からの読み込み成功\n";
        } else {
            echo "❌ storage/ からの読み込み失敗\n";
        }

        @unlink($testFile);
        echo "✅ テストファイル削除完了\n";
    }
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
}
echo "\n";

// 9. エラーログ確認
echo "=== エラーログ（最新10行） ===\n";
$errorLog = __DIR__ . '/storage/error.log';
if (file_exists($errorLog)) {
    $lines = file($errorLog);
    $lastLines = array_slice($lines, -10);
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
} else {
    echo "(error.log は存在しません)\n";
}

echo "</pre>";
echo "<hr>";
echo "<p><strong>次のステップ:</strong></p>";
echo "<ol>";
echo "<li>上記のチェック項目で ❌ があれば、それが原因の可能性が高いです</li>";
echo "<li>特に「PHPバージョン」「allow_url_fopen」「ディレクトリの書き込み権限」を確認してください</li>";
echo "<li>問題なければ、<a href='admin/index.php'>管理画面</a>にアクセスしてみてください</li>";
echo "</ol>";

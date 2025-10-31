<?php
/**
 * 管理画面認証処理
 */

session_start();

require_once __DIR__ . '/../api/bootstrap.php';

/**
 * ログイン試行回数チェック
 */
function checkLoginAttempts(): bool
{
    global $config;

    $attemptsFile = $config['STORAGE_PATH'] . '/.login_attempts';
    $maxAttempts = $config['MAX_LOGIN_ATTEMPTS'];
    $cooldown = $config['LOGIN_COOLDOWN_SECONDS'];

    if (!file_exists($attemptsFile)) {
        return true;
    }

    $data = json_decode(file_get_contents($attemptsFile), true);
    if (!$data || !isset($data['count']) || !isset($data['timestamp'])) {
        return true;
    }

    // クールダウン期間が過ぎたらリセット
    if (time() - $data['timestamp'] > $cooldown) {
        @unlink($attemptsFile);
        return true;
    }

    // 試行回数チェック
    return $data['count'] < $maxAttempts;
}

/**
 * ログイン試行回数を記録
 */
function recordLoginAttempt(): void
{
    global $config;

    $attemptsFile = $config['STORAGE_PATH'] . '/.login_attempts';

    $data = ['count' => 1, 'timestamp' => time()];

    if (file_exists($attemptsFile)) {
        $existing = json_decode(file_get_contents($attemptsFile), true);
        if ($existing && isset($existing['count'])) {
            $data['count'] = $existing['count'] + 1;
        }
    }

    file_put_contents($attemptsFile, json_encode($data), LOCK_EX);
}

/**
 * ログイン試行回数をリセット
 */
function resetLoginAttempts(): void
{
    global $config;
    $attemptsFile = $config['STORAGE_PATH'] . '/.login_attempts';
    @unlink($attemptsFile);
}

/**
 * ログイン処理
 */
function doLogin(string $username, string $password): bool
{
    global $config;

    if (!checkLoginAttempts()) {
        return false;
    }

    // 設定から認証情報を取得
    $adminUsername = $config['ADMIN_USERNAME'] ?? 'admin';
    $adminPasswordHash = $config['ADMIN_PASSWORD'] ?? '';

    // 初期パスワードが未設定の場合
    if (empty($adminPasswordHash)) {
        // デフォルトパスワード "admin" でログイン可能
        if ($username === $adminUsername && $password === 'admin') {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['force_password_change'] = true;
            resetLoginAttempts();
            return true;
        }
    } else {
        // 通常のログイン
        if ($username === $adminUsername && password_verify($password, $adminPasswordHash)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['force_password_change'] = false;
            resetLoginAttempts();
            return true;
        }
    }

    recordLoginAttempt();
    return false;
}

/**
 * ログアウト処理
 */
function doLogout(): void
{
    session_destroy();
    session_start();
}

/**
 * 認証チェック
 */
function isAuthenticated(): bool
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * パスワード変更強制チェック
 */
function needsPasswordChange(): bool
{
    return isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true;
}

/**
 * CSRFトークン生成
 */
function generateCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 */
function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * パスワード変更
 */
function changePassword(string $newPassword): bool
{
    global $config;

    $settingsFile = $config['SETTINGS_FILE'];

    // 設定を読み込み
    $settings = [];
    if (file_exists($settingsFile)) {
        $json = file_get_contents($settingsFile);
        $settings = json_decode($json, true) ?: [];
    }

    // パスワードをハッシュ化して保存
    $settings['ADMIN_PASSWORD'] = password_hash($newPassword, PASSWORD_DEFAULT);

    $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents($settingsFile, $json, LOCK_EX) === false) {
        return false;
    }

    // パーミッション設定（600）
    @chmod($settingsFile, 0600);

    // セッションフラグをクリア
    $_SESSION['force_password_change'] = false;

    return true;
}

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (doLogin($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['login_error'] = 'ログインに失敗しました。ユーザー名またはパスワードが正しくありません。';
            if (!checkLoginAttempts()) {
                $_SESSION['login_error'] = 'ログイン試行回数が上限に達しました。しばらく待ってから再試行してください。';
            }
            header('Location: index.php');
            exit;
        }
    }

    if ($action === 'logout') {
        doLogout();
        header('Location: index.php');
        exit;
    }

    if ($action === 'change_password') {
        if (!isAuthenticated()) {
            header('Location: index.php');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            $_SESSION['error'] = 'セキュリティトークンが無効です。';
            header('Location: index.php');
            exit;
        }

        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || strlen($newPassword) < 8) {
            $_SESSION['error'] = 'パスワードは8文字以上で設定してください。';
            header('Location: index.php');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'パスワードが一致しません。';
            header('Location: index.php');
            exit;
        }

        if (changePassword($newPassword)) {
            $_SESSION['success'] = 'パスワードを変更しました。';
        } else {
            $_SESSION['error'] = 'パスワードの変更に失敗しました。';
        }

        header('Location: index.php');
        exit;
    }
}

<?php
/**
 * 管理画面メイン
 */

require_once __DIR__ . '/auth.php';

// 設定保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
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

    // 入力値を取得
    $input = [
        'MATOMO_URL' => trim($_POST['matomo_url'] ?? ''),
        'TOKEN_AUTH' => trim($_POST['token_auth'] ?? ''),
        'SITE_IDS' => array_filter(array_map('intval', explode(',', $_POST['site_ids'] ?? ''))),
        'TIMEZONE' => $_POST['timezone'] ?? 'Asia/Tokyo',
        'CACHE_TTL_ACTIVE_30' => (int)($_POST['cache_ttl_active_30'] ?? 60),
        'CACHE_TTL_HOURLY' => (int)($_POST['cache_ttl_hourly'] ?? 300),
    ];

    // バリデーション
    $errors = validateConfig($input);

    if (empty($errors)) {
        // 既存設定を読み込み
        $settingsFile = $config['SETTINGS_FILE'];
        $existingSettings = [];

        if (file_exists($settingsFile)) {
            $json = file_get_contents($settingsFile);
            $existingSettings = json_decode($json, true) ?: [];
        }

        // 新しい設定をマージ（パスワードは保持）
        $newSettings = array_merge($existingSettings, $input);

        // 保存
        $json = json_encode($newSettings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents($settingsFile, $json, LOCK_EX) !== false) {
            @chmod($settingsFile, 0600);
            $_SESSION['success'] = '設定を保存しました。';

            // キャッシュをクリア
            if (isset($_POST['clear_cache']) && $_POST['clear_cache'] === '1') {
                $cacheFiles = glob($config['CACHE_PATH'] . '/*.json');
                foreach ($cacheFiles as $file) {
                    @unlink($file);
                }
                $_SESSION['success'] .= ' キャッシュをクリアしました。';
            }
        } else {
            $_SESSION['error'] = '設定の保存に失敗しました。ファイルの書き込み権限を確認してください。';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }

    header('Location: index.php');
    exit;
}

// 接続テスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_connection') {
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

    try {
        if (empty($config['SITE_IDS'])) {
            throw new Exception('サイトIDが設定されていません');
        }

        $testIdSite = $config['SITE_IDS'][0];
        $data = callMatomoApi('Live.getCounters', [
            'idSite' => $testIdSite,
            'lastMinutes' => 1,
        ]);

        $_SESSION['success'] = 'Matomo接続テスト成功！サイトID ' . $testIdSite . ' にアクセスできました。';

    } catch (Exception $e) {
        $_SESSION['error'] = '接続テスト失敗: ' . $e->getMessage();
    }

    header('Location: index.php');
    exit;
}

$csrfToken = generateCsrfToken();
$loginError = $_SESSION['login_error'] ?? null;
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;

unset($_SESSION['login_error'], $_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - Matomo リアルタイムダッシュボード</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { margin-bottom: 10px; color: #333; }
        h2 { margin: 30px 0 15px; color: #555; font-size: 1.3em; border-bottom: 2px solid #3498db; padding-bottom: 8px; }
        .subtitle { color: #777; margin-bottom: 30px; }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #555;
        }
        input[type="text"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea { resize: vertical; min-height: 60px; font-family: monospace; }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        button:hover { background: #2980b9; }
        button.secondary {
            background: #95a5a6;
        }
        button.secondary:hover { background: #7f8c8d; }
        button.danger {
            background: #e74c3c;
        }
        button.danger:hover { background: #c0392b; }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
        }
        .alert-success {
            background: #efe;
            border: 1px solid #cfc;
            color: #060;
        }
        .alert-warning {
            background: #ffc;
            border: 1px solid #fc6;
            color: #c60;
        }
        .help-text {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .login-box {
            max-width: 400px;
            margin: 100px auto;
        }
        .logout-link {
            text-align: right;
            margin-bottom: 20px;
        }
        .logout-link form {
            display: inline;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
    </style>
</head>
<body>

<?php if (!isAuthenticated()): ?>
    <!-- ログイン画面 -->
    <div class="container login-box">
        <h1>管理画面ログイン</h1>
        <p class="subtitle">Matomo リアルタイムダッシュボード</p>

        <?php if ($loginError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST" action="auth.php">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label>ユーザー名</label>
                <input type="text" name="username" required autofocus value="admin">
            </div>

            <div class="form-group">
                <label>パスワード</label>
                <input type="password" name="password" required>
                <div class="help-text">初期パスワード: admin</div>
            </div>

            <button type="submit">ログイン</button>
        </form>
    </div>

<?php elseif (needsPasswordChange()): ?>
    <!-- パスワード変更強制 -->
    <div class="container">
        <h1>パスワード変更が必要です</h1>
        <p class="subtitle">セキュリティのため、初期パスワードを変更してください。</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="auth.php">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label>新しいパスワード（8文字以上）</label>
                <input type="password" name="new_password" required minlength="8">
            </div>

            <div class="form-group">
                <label>パスワード（確認）</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>

            <button type="submit">パスワードを変更</button>
        </form>
    </div>

<?php else: ?>
    <!-- 管理画面メイン -->
    <div class="container">
        <div class="logout-link">
            <form method="POST" action="auth.php">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="secondary">ログアウト</button>
            </form>
        </div>

        <h1>管理画面</h1>
        <p class="subtitle">Matomo リアルタイムダッシュボード設定</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= nl2br(htmlspecialchars($error)) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <h2>Matomo接続設定</h2>

            <div class="form-group">
                <label>Matomo URL *</label>
                <input type="text" name="matomo_url" value="<?= htmlspecialchars($config['MATOMO_URL']) ?>" required placeholder="https://matomo.example.com">
                <div class="help-text">MatomoのベースURL（末尾のスラッシュは任意）</div>
            </div>

            <div class="form-group">
                <label>トークン（token_auth）*</label>
                <input type="text" name="token_auth" value="<?= htmlspecialchars($config['TOKEN_AUTH']) ?>" required placeholder="32文字以上のトークン">
                <div class="help-text">Matomoの設定画面から取得した読み取り専用トークン</div>
            </div>

            <div class="form-group">
                <label>サイトID（カンマ区切り、最大10個）*</label>
                <input type="text" name="site_ids" value="<?= htmlspecialchars(implode(',', $config['SITE_IDS'])) ?>" required placeholder="1,2,3">
                <div class="help-text">集計するサイトのID（例: 1,2,3）</div>
            </div>

            <h2>表示設定</h2>

            <div class="form-group">
                <label>タイムゾーン</label>
                <select name="timezone">
                    <?php foreach ($config['AVAILABLE_TIMEZONES'] as $tz): ?>
                        <option value="<?= htmlspecialchars($tz) ?>" <?= $tz === $config['TIMEZONE'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tz) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">Matomoのサイト設定と合わせてください</div>
            </div>

            <h2>キャッシュ設定</h2>

            <div class="form-group">
                <label>直近30分のTTL（秒）</label>
                <input type="number" name="cache_ttl_active_30" value="<?= $config['CACHE_TTL_ACTIVE_30'] ?>" min="10" max="3600">
                <div class="help-text">推奨: 60秒</div>
            </div>

            <div class="form-group">
                <label>時間帯別のTTL（秒）</label>
                <input type="number" name="cache_ttl_hourly" value="<?= $config['CACHE_TTL_HOURLY'] ?>" min="10" max="3600">
                <div class="help-text">推奨: 300秒（5分）</div>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" name="clear_cache" id="clear_cache" value="1">
                <label for="clear_cache" style="margin-bottom: 0;">保存時にキャッシュをクリア</label>
            </div>

            <div class="btn-group">
                <button type="submit">設定を保存</button>
            </div>
        </form>

        <form method="POST" action="index.php" style="margin-top: 20px;">
            <input type="hidden" name="action" value="test_connection">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" class="secondary">接続テスト</button>
        </form>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">
            <h2>その他</h2>
            <p><a href="../public/index.html" style="color: #3498db; text-decoration: none;">→ ダッシュボードを表示</a></p>
            <p style="margin-top: 10px;">
                <a href="../api/health.php" target="_blank" style="color: #3498db; text-decoration: none;">→ ヘルスチェック</a>
            </p>
        </div>
    </div>

<?php endif; ?>

</body>
</html>

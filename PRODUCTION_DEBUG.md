# 本番環境のデバッグ手順

本番環境（https://portal.yamanex.co.jp/mrd/）でエラーが発生した場合の調査手順をまとめます。

## 📋 前提条件

- **開発環境**: /home/takumin/dev/matomo-rt-dashboard/
- **本番環境**: ~/yamanex.co.jp/public_html/mrtd/
- この2つは別サーバーです

---

## 🔍 ステップ1: PHPバージョンの確認

### 1-1. version.phpをアップロード

開発環境で作成した `version.php` を本番サーバーにアップロードします。

```bash
# FTP/SFTP/SCPなどで以下のファイルをアップロード
version.php → ~/yamanex.co.jp/public_html/mrtd/version.php
```

### 1-2. アクセスして確認

```
https://portal.yamanex.co.jp/mrd/version.php
```

**確認ポイント:**
- PHP Version が **8.1.0以上** であること
- それ以下の場合、PHPバージョンのアップグレードが必要

**もし500エラーになる場合:**
- PHPのバージョンが非常に古い（7.x以下）可能性
- サーバーのコントロールパネルでPHPバージョンを確認

---

## 🔍 ステップ2: phpinfo.phpで詳細確認

### 2-1. phpinfo.phpをアップロード

```bash
# 開発環境から本番環境へアップロード
phpinfo.php → ~/yamanex.co.jp/public_html/mrtd/phpinfo.php
```

### 2-2. アクセスして確認

```
https://portal.yamanex.co.jp/mrd/phpinfo.php
```

**確認ポイント:**
- `allow_url_fopen`: **On** であること（Matomo API呼び出しに必須）
- `session.save_path`: 書き込み可能なディレクトリであること
- `PHP Version`: 8.1以上であること

**⚠️ セキュリティ注意:**
確認後は必ず削除してください！

```bash
rm ~/yamanex.co.jp/public_html/mrtd/phpinfo.php
```

---

## 🔍 ステップ3: エラーログの確認

### 3-1. storage/error.log を確認

本番サーバーで以下を実行：

```bash
# エラーログの最新20行を表示
tail -n 20 ~/yamanex.co.jp/public_html/mrtd/storage/error.log

# リアルタイムでエラーログを監視
tail -f ~/yamanex.co.jp/public_html/mrtd/storage/error.log
```

### 3-2. Apacheエラーログも確認

```bash
# サーバーのApacheエラーログを確認（パスは環境により異なる）
tail -n 50 ~/logs/error_log
# または
tail -n 50 /var/log/apache2/error.log
```

---

## 🔍 ステップ4: settings.jsonの確認と修正

### 4-1. 現在の設定を確認

```bash
cat ~/yamanex.co.jp/public_html/mrtd/storage/settings.json
```

**現在の問題:**
```json
{
    "ADMIN_PASSWORD": "$2y$10$icsKJWEeu6wJcHjDBVy2ieY4xccLZi0Di81EGaryQ6Wn8uWj7YffO"
}
```

→ **Matomo接続情報が一切入っていない！**

### 4-2. 正しい設定に修正

```bash
nano ~/yamanex.co.jp/public_html/mrtd/storage/settings.json
```

以下の内容に書き換えます：

```json
{
    "ADMIN_PASSWORD": "$2y$10$icsKJWEeu6wJcHjDBVy2ieY4xccLZi0Di81EGaryQ6Wn8uWj7YffO",
    "MATOMO_URL": "https://あなたのMatomoのURL",
    "TOKEN_AUTH": "あなたのMatomoトークン（32文字以上）",
    "SITE_IDS": [1, 2, 3],
    "TIMEZONE": "Asia/Tokyo",
    "CACHE_TTL_ACTIVE_30": 60,
    "CACHE_TTL_HOURLY": 300
}
```

**必須項目:**
- `MATOMO_URL`: MatomoサーバーのベースURL（例: `https://matomo.yamanex.co.jp`）
- `TOKEN_AUTH`: Matomoの管理画面で取得したtoken_auth（32文字以上）
- `SITE_IDS`: 集計したいサイトのID（配列形式、例: `[1, 2, 3]`）

### 4-3. パーミッション確認

```bash
chmod 600 ~/yamanex.co.jp/public_html/mrtd/storage/settings.json
```

---

## 🔍 ステップ5: health.phpで接続テスト

### 5-1. ヘルスチェックを実行

```
https://portal.yamanex.co.jp/mrd/api/health.php
```

**期待される成功レスポンス:**
```json
{
    "ok": true,
    "checks": {
        "config": "ok",
        "storage": "ok",
        "cache": "ok",
        "matomo": "ok"
    },
    "timestamp": "2025-12-29T12:34:56+09:00"
}
```

**エラーレスポンス例:**
```json
{
    "ok": false,
    "checks": {
        "config": "error: 設定が不完全です",
        "storage": "ok",
        "cache": "ok",
        "matomo": "skipped: 設定なし"
    },
    "timestamp": "2025-12-29T12:34:56+09:00"
}
```

→ この場合、settings.jsonの内容を再確認

---

## 🔍 ステップ6: デバッグモードを有効化（必要な場合のみ）

### 6-1. .envファイルを作成

**⚠️ 本番環境では通常不要！トラブルシューティング時のみ使用**

```bash
cd ~/yamanex.co.jp/public_html/mrtd/
cp .env.example .env
nano .env
```

以下の行を追加：

```env
DEBUG_MODE=true
```

### 6-2. エラーを確認

ブラウザで管理画面やAPIにアクセスすると、詳細なエラーメッセージが表示されます。

### 6-3. ⚠️ 確認後は必ず無効化

```bash
nano .env
```

```env
DEBUG_MODE=false
```

または .env ファイルを削除：

```bash
rm ~/yamanex.co.jp/public_html/mrtd/.env
```

---

## 🔍 ステップ7: .htaccessの確認

### 7-1. ルートの.htaccessが存在するか確認

```bash
ls -la ~/yamanex.co.jp/public_html/mrtd/.htaccess
```

### 7-2. なければアップロード

開発環境から本番環境へ以下のファイルをアップロード：

```
.htaccess → ~/yamanex.co.jp/public_html/mrtd/.htaccess
storage/.htaccess → ~/yamanex.co.jp/public_html/mrtd/storage/.htaccess
config/.htaccess → ~/yamanex.co.jp/public_html/mrtd/config/.htaccess
```

---

## 🔍 ステップ8: パーミッションの確認

### 8-1. 必要なディレクトリとファイルのパーミッション

```bash
cd ~/yamanex.co.jp/public_html/mrtd/

# ディレクトリ
chmod 755 storage
chmod 755 storage/cache

# ファイル
chmod 600 storage/settings.json
chmod 600 storage/error.log
```

### 8-2. 確認

```bash
ls -la storage/
```

**期待される出力:**
```
drwxr-xr-x  3 user group 4096 Dec 29 12:00 .
drwxr-xr-x  8 user group 4096 Dec 29 12:00 ..
-rw-------  1 user group  300 Dec 29 12:00 settings.json
-rw-------  1 user group 1234 Dec 29 12:00 error.log
drwxr-xr-x  2 user group 4096 Dec 29 12:00 cache
```

---

## 📊 トラブルシューティングチェックリスト

### ✅ 必須チェック項目

- [ ] PHPバージョンが8.1以上
- [ ] allow_url_fopen が On
- [ ] storage/ ディレクトリが書き込み可能（755）
- [ ] storage/settings.json が存在し、600パーミッション
- [ ] settings.json に MATOMO_URL, TOKEN_AUTH, SITE_IDS が設定されている
- [ ] .htaccess ファイルがルートに存在
- [ ] Matomoサーバーが稼働中で、token_authが有効

### ❌ よくあるエラーと解決方法

#### 1. 500 Internal Server Error

**原因:**
- PHPバージョンが古い（8.1未満）
- PHPの構文エラー
- .htaccessの設定エラー

**解決策:**
1. version.php で PHPバージョンを確認
2. Apache error_log を確認
3. デバッグモードを一時的に有効化

#### 2. 403 Forbidden

**原因:**
- CORS制御によるブロック
- .htaccess による制限

**解決策:**
1. 同一ドメインからアクセスしているか確認
2. ブラウザの開発者ツールでCORSエラーを確認

#### 3. 「設定が不完全です」エラー

**原因:**
- settings.json に Matomo接続情報が未設定

**解決策:**
1. settings.json の内容を確認
2. MATOMO_URL, TOKEN_AUTH, SITE_IDS を追加

#### 4. 「Matomo APIへの接続に失敗しました」

**原因:**
- TOKEN_AUTH が無効
- MATOMO_URL が間違っている
- allow_url_fopen が無効
- Matomoサーバーがダウン

**解決策:**
1. phpinfo.php で allow_url_fopen を確認
2. Matomoの管理画面でトークンを再確認
3. MATOMO_URL のスペルミスをチェック

---

## 🚀 推奨デバッグフロー

```
1. version.php でPHPバージョン確認
   ↓
2. settings.json を正しく設定
   ↓
3. health.php で接続テスト
   ↓ (エラーの場合)
4. storage/error.log を確認
   ↓
5. デバッグモードを一時的に有効化
   ↓
6. 問題を特定して修正
   ↓
7. デバッグモードを無効化
   ↓
8. health.php で再テスト
```

---

## 📝 注意事項

1. **デバッグモードは本番環境では必ず無効化**
   - セキュリティリスクがあります
   - トラブルシューティング時のみ一時的に有効化

2. **phpinfo.php は確認後すぐ削除**
   - サーバー情報が全て公開されます

3. **settings.json のパーミッションは600**
   - トークンが含まれるため、他のユーザーから読めないようにする

4. **エラーログは定期的に確認**
   - 問題の早期発見につながります

---

## 🆘 それでも解決しない場合

1. サーバーのコントロールパネルでPHPバージョンを確認
2. ホスティング会社のサポートに問い合わせ
3. 開発環境で同じ設定を試してみる
4. GitHubのissueで報告

---

**作成日:** 2025-12-29
**対象バージョン:** v1.0.0

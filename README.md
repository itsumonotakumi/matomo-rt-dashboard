# Matomo リアルタイムダッシュボード

**共有レンタルサーバー対応**の、Matomoリアルタイム訪問統計を表示するダッシュボードです。

## 特徴

- ✅ **共有レンタルサーバー対応**（root権限不要、純PHP）
- ✅ **Composer不要**（依存関係ゼロ）
- ✅ **セキュア**（TOKEN_AUTHはサーバー側のみ、CORS対策、CSRF対策）
- ✅ **リアルタイム表示**（直近30分 + 今日の時間帯別）
- ✅ **最大10サイト集計**
- ✅ **キャッシュ機能**（API負荷軽減）
- ✅ **GUI設定画面**（Webから簡単設定）
- ✅ **ダークモード対応**
- ✅ **レスポンシブデザイン**

---

## 目次

1. [必要環境](#必要環境)
2. [インストール手順](#インストール手順)
3. [初期設定](#初期設定)
4. [使い方](#使い方)
5. [トラブルシューティング](#トラブルシューティング)
6. [テスト手順](#テスト手順)
7. [セキュリティ](#セキュリティ)
8. [FAQ](#faq)
9. [ライセンス](#ライセンス)

---

## 必要環境

### サーバー側

- **PHP 8.1以上**
- Apache または Nginx
- `allow_url_fopen` が有効（外部API呼び出し用）
- 書き込み可能なディレクトリ（`storage/`）

### Matomo側

- Matomo 4.x以上
- **読み取り権限のあるtoken_auth**（設定 → セキュリティ → 認証トークン）
- 集計したいサイトのID（1〜10個）

---

## インストール手順

### 1. ファイルをアップロード

サーバーの任意のディレクトリ（例: `public_html/matomo-dashboard/`）にすべてのファイルをアップロードします。

```bash
# FTP/SFTPでアップロード、またはgit clone
git clone <リポジトリURL> matomo-rt-dashboard
cd matomo-rt-dashboard
```

### 2. パーミッション設定

以下のディレクトリとファイルに書き込み権限を付与してください。

```bash
chmod 755 storage
chmod 755 storage/cache
chmod 600 storage/settings.json   # 作成後
chmod 600 storage/error.log        # 作成後
```

または、共有サーバーのファイルマネージャーから設定してください。

| パス | パーミッション |
|------|---------------|
| `storage/` | 755 (rwxr-xr-x) |
| `storage/cache/` | 755 (rwxr-xr-x) |
| `storage/settings.json` | 600 (rw-------) |
| `storage/error.log` | 600 (rw-------) |

### 3. .htaccess確認

Apache環境の場合、`.htaccess`ファイルがアップロードされていることを確認してください。

- ルート: `.htaccess`
- `storage/`: `storage/.htaccess`
- `config/`: `config/.htaccess`
- `public/`: `public/.htaccess`

---

## 初期設定

### 1. 管理画面にアクセス

ブラウザで以下にアクセスしてください。

```
https://your-domain.com/matomo-rt-dashboard/admin/index.php
```

### 2. 初回ログイン

デフォルトの認証情報でログインします。

- **ユーザー名**: `admin`
- **パスワード**: `admin`

⚠️ **重要**: 初回ログイン後、**必ずパスワードを変更してください**。

### 3. パスワード変更

初回ログイン後、自動的にパスワード変更画面が表示されます。

- 新しいパスワード（8文字以上）を入力
- 確認用にもう一度入力
- 「パスワードを変更」をクリック

### 4. Matomo接続情報を設定

管理画面で以下の情報を入力し、「設定を保存」をクリックします。

| 項目 | 説明 | 例 |
|------|------|-----|
| **Matomo URL** | MatomoのベースURL | `https://matomo.example.com` |
| **トークン** | Matomoの読み取りトークン | `abcdef123456...`（32文字以上） |
| **サイトID** | 集計するサイトID（カンマ区切り、最大10個） | `1,2,3` |
| **タイムゾーン** | 表示タイムゾーン | `Asia/Tokyo` |
| **キャッシュTTL** | キャッシュ有効期間（秒） | 直近30分: `60`, 時間帯別: `300` |

### 5. 接続テスト

設定保存後、「接続テスト」ボタンをクリックしてMatomo APIへの接続を確認します。

成功すれば「Matomo接続テスト成功！」と表示されます。

---

## 使い方

### ダッシュボードを表示

ブラウザで以下にアクセスしてください。

```
https://your-domain.com/matomo-rt-dashboard/public/index.html
```

### 表示内容

#### 数値カード

- **直近30分 アクティブ訪問**: 過去30分間のアクティブな訪問数
- **今日の総訪問数**: 本日0時〜現在までの合計訪問数

#### グラフ

- **直近30分 アクティブ訪問数（サイト別）**: サイトごとの内訳を棒グラフで表示
- **今日の時間帯別訪問数（0〜23時）**: 時間帯ごとの訪問数を棒グラフで表示

### 自動更新

- **直近30分**: 60秒ごとに自動更新
- **時間帯別**: 5分ごとに自動更新

---

## トラブルシューティング

### 1. 「設定が不完全です」と表示される

**原因**: Matomo接続情報が未設定

**解決策**:
1. 管理画面にアクセス
2. Matomo URL、トークン、サイトIDを入力
3. 「設定を保存」をクリック

### 2. 「Matomo APIへの接続に失敗しました」

**原因**: トークンが無効、または Matomo がダウンしている

**解決策**:
1. Matomoの管理画面でトークンを再確認
2. Matomo URLが正しいか確認（末尾のスラッシュは任意）
3. `storage/error.log` を確認

```bash
tail -f storage/error.log
```

### 3. グラフが表示されない

**原因**: Chart.jsが読み込めていない、またはJavaScriptエラー

**解決策**:
1. ブラウザの開発者ツール（F12）でコンソールエラーを確認
2. CDN（Chart.js）が正常に読み込めているか確認
3. `public/assets/app.js` のパスが正しいか確認

### 4. 「書き込み権限がありません」エラー

**原因**: `storage/` ディレクトリの書き込み権限がない

**解決策**:

```bash
chmod 755 storage
chmod 755 storage/cache
```

### 5. パスワードを忘れた

**解決策**:

`storage/settings.json` を削除すると、初期パスワード（`admin`）に戻ります。

```bash
rm storage/settings.json
```

---

## テスト手順

### 1. 設定の保存と再読込

1. 管理画面でMatomo接続情報を入力し保存
2. ページをリロード
3. 入力した値が保持されていることを確認

### 2. 接続テスト

1. 管理画面で「接続テスト」ボタンをクリック
2. 「Matomo接続テスト成功！」が表示されることを確認

### 3. API動作確認

ブラウザで直接APIにアクセスしてJSONが返ることを確認：

```
https://your-domain.com/matomo-rt-dashboard/api/health.php
https://your-domain.com/matomo-rt-dashboard/api/active_30.php
https://your-domain.com/matomo-rt-dashboard/api/hourly_today.php
```

### 4. ダッシュボード表示

1. `public/index.html` にアクセス
2. 数値カードに値が表示されることを確認
3. グラフが描画されることを確認
4. 60秒待って自動更新されることを確認

### 5. キャッシュ動作確認

1. `active_30.php` に2回連続でアクセス
2. 2回目はキャッシュから返される（レスポンスが速い）
3. `storage/cache/active_30.json` が作成されていることを確認

### 6. セキュリティチェック

1. ページソース（右クリック → ソースを表示）を確認
2. `token_auth` が一切表示されていないことを確認
3. ブラウザの開発者ツール（F12）のネットワークタブを確認
4. どのリクエストにも `token_auth` が含まれていないことを確認

### 7. 多サイトテスト

1. 管理画面で `SITE_IDS` に11個以上入力
2. バリデーションエラーが表示されることを確認

---

## セキュリティ

### 実装済みのセキュリティ対策

- ✅ **TOKEN_AUTHの非公開**: サーバー側のみで管理、クライアントに一切露出しない
- ✅ **CORS制御**: 同一オリジンのみ許可
- ✅ **CSRF対策**: セッショントークンによる検証
- ✅ **ブルートフォース対策**: ログイン試行回数制限（5回まで、5分クールダウン）
- ✅ **パスワードハッシュ化**: `password_hash(PASSWORD_DEFAULT)`
- ✅ **settings.json保護**: パーミッション600、`.htaccess`で直アクセス拒否
- ✅ **セキュリティヘッダー**: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`
- ✅ **入力検証**: URL、整数配列、TTL範囲をバリデーション
- ✅ **.gitignore**: センシティブファイルをGit管理外に

### 推奨事項

1. **HTTPS必須**: 必ずHTTPS環境で運用してください
2. **定期的なパスワード変更**: 管理画面パスワードを定期的に変更
3. **トークンの権限**: Matomoで「読み取りのみ」のトークンを使用
4. **ログ監視**: `storage/error.log` を定期的にチェック
5. **アップデート**: セキュリティパッチが出たら速やかに適用

---

## FAQ

### Q1. Composerは必要ですか？

**A**: 不要です。純PHPで動作します。

### Q2. データベースは必要ですか？

**A**: 不要です。設定は `storage/settings.json` に保存されます。

### Q3. Cronは使えますか？

**A**: 使えますが、必須ではありません。キャッシュは初回アクセス時に生成されます。

任意でCronを設定すると、キャッシュを事前に温めることができます：

```bash
# 毎分実行
* * * * * curl -s https://your-domain.com/matomo-rt-dashboard/api/active_30.php > /dev/null
```

### Q4. サイトIDを10個以上追加できますか？

**A**: できません。パフォーマンスを考慮し、最大10個に制限しています。

### Q5. .envファイルは使えますか？

**A**: はい。`.env.example` をコピーして `.env` を作成し、設定を記述できます。

ただし、**GUI設定画面での設定を推奨**します（優先順位: `settings.json` > `.env` > `defaults.php`）。

### Q6. 障害時の動作は？

**A**: Matomoがダウンしていても、**最後のキャッシュ**が返されます（フェイルソフト）。

ダッシュボード上に「キャッシュ表示中」の警告が表示されます。

### Q7. タイムゾーンはどこで設定しますか？

**A**: 管理画面の「タイムゾーン」プルダウンで設定できます。

Matomoのサイト設定と合わせてください。

---

## ファイル構成

```
matomo-rt-dashboard/
├─ public/                  # 公開ディレクトリ（Webルート）
│  ├─ index.html           # ダッシュボード
│  ├─ assets/
│  │  ├─ style.css         # スタイルシート
│  │  └─ app.js            # フロントエンドJS
│  └─ .htaccess            # キャッシュ・セキュリティ設定
├─ api/                     # APIエンドポイント
│  ├─ bootstrap.php        # 共通初期化
│  ├─ _guard.php           # CORS・認証ガード
│  ├─ active_30.php        # 直近30分API
│  ├─ hourly_today.php     # 時間帯別API
│  └─ health.php           # ヘルスチェック
├─ admin/                   # 管理画面
│  ├─ index.php            # 設定UI
│  └─ auth.php             # 認証処理
├─ config/                  # 設定
│  ├─ defaults.php         # デフォルト値
│  ├─ config.php           # 設定ローダー
│  └─ .htaccess            # 直アクセス拒否
├─ storage/                 # データ保存
│  ├─ settings.json        # 設定（600パーミッション）
│  ├─ error.log            # エラーログ
│  ├─ cache/               # キャッシュファイル
│  └─ .htaccess            # 直アクセス拒否
├─ .htaccess                # ルート設定
├─ .gitignore               # Git除外設定
├─ .env.example             # 環境変数サンプル
└─ README.md                # このファイル
```

---

## バージョン履歴

### v1.0.0 (2025-10-31)

- 初回リリース
- 直近30分 + 時間帯別表示
- GUI設定画面
- キャッシュ機能
- CORS/CSRF対策
- ダークモード対応

---

## ライセンス

MIT License

---

## サポート

問題が発生した場合は、以下を確認してください：

1. `storage/error.log` のエラーログ
2. ブラウザの開発者ツール（F12）のコンソールエラー
3. `api/health.php` のヘルスチェック結果

---

## 開発者向け情報

### カスタマイズ

#### 更新間隔の変更

`public/assets/app.js` の `CONFIG` を編集：

```javascript
const CONFIG = {
    ACTIVE_INTERVAL: 60000,  // 60秒 → 変更可能
    HOURLY_INTERVAL: 300000, // 5分 → 変更可能
    API_BASE: '../api',
};
```

#### グラフの色変更

`public/assets/app.js` の `backgroundColor` / `borderColor` を編集：

```javascript
backgroundColor: 'rgba(52, 152, 219, 0.8)', // 変更可能
```

#### TTLの変更

管理画面から変更、または `config/defaults.php` を編集：

```php
'CACHE_TTL_ACTIVE_30' => 60,   // 秒
'CACHE_TTL_HOURLY' => 300,     // 秒
```

---

**以上！めっちゃ完璧なダッシュボードができたね！✨**

何か問題があったら、まずは `api/health.php` でヘルスチェックしてみてね！🚀

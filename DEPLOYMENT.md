# ゼミサーバーへのデプロイ手順

## 📋 事前準備

### 1. ゼミサーバーのデータベース情報を確認
以下の情報を確認してください：
- データベースタイプ: PostgreSQL
- ホスト名（例: localhost）
- ポート番号（通常: 5432）
- データベース名
- ユーザー名
- パスワード

## 🚀 デプロイ手順

### ステップ1: config.phpの設定

`config.php`を開き、以下の部分を**ゼミサーバーの情報に変更**してください：

```php
define('DB_TYPE', 'pgsql'); // PostgreSQLを使用
define('DB_HOST', 'localhost'); // ← ゼミサーバーのホスト名
define('DB_PORT', '5432'); // ← ポート番号
define('DB_NAME', 'doutor_db'); // ← データベース名
define('DB_USER', 'your_username'); // ← ユーザー名
define('DB_PASS', 'your_password'); // ← パスワード
```

### ステップ2: ファイルをサーバーにアップロード

以下のファイル・フォルダをすべてアップロードしてください：

```
hattyuu_app/
├── index.php
├── login.php
├── logout.php
├── auth.php
├── config.php ← 設定済み
├── order_calculator.php
├── items.php
├── item_edit.php
├── calendar.php
├── event_edit.php
├── feedback.php
├── css/
├── js/
├── includes/
└── db/
    └── init_postgresql.sql ← PostgreSQL用
```

**注意**: `db/doutor.db`（SQLiteファイル）はアップロード不要です。

### ステップ3: データベースの初期化

ゼミサーバーにSSHでログインし、以下のコマンドを実行：

```bash
# PostgreSQLに接続
psql -U your_username -d doutor_db

# SQLファイルを実行
\i /path/to/hattyuu_app/db/init_postgresql.sql

# 確認
\dt  # テーブル一覧を表示
SELECT * FROM users;  # ユーザーが作成されているか確認

# 終了
\q
```

または、コマンドラインから直接実行：

```bash
psql -U your_username -d doutor_db -f /path/to/hattyuu_app/db/init_postgresql.sql
```

### ステップ4: ログイン確認

1. ブラウザでアクセス（例: `https://your-server.ac.jp/~username/hattyuu_app/`）
2. ログイン画面が表示されることを確認
3. 以下の情報でログイン：
   - ユーザー名: `admin`
   - パスワード: `admin123`

## 🔧 トラブルシューティング

### エラー: "AUTOINCREMENT"
→ `init_postgresql.sql`を使用してください（`init.sql`ではありません）

### ログインできない
以下を確認：

1. **データベースにユーザーが登録されているか確認**
   ```sql
   SELECT * FROM users;
   ```

2. **ユーザーが存在しない場合、手動で追加**
   ```sql
   INSERT INTO users (username, password) 
   VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
   ```

3. **パスワードハッシュを再生成する場合**
   ```php
   <?php
   echo password_hash('admin123', PASSWORD_DEFAULT);
   ?>
   ```
   このコードをPHPで実行し、出力されたハッシュをINSERTしてください。

### データベース接続エラー
- `config.php`の設定を再確認
- データベース名、ユーザー名、パスワードが正しいか確認
- PostgreSQLが起動しているか確認

### 権限エラー
ファイルの権限を確認：
```bash
chmod 755 *.php
chmod 755 css js includes
```

## 📝 SQLiteとPostgreSQLの主な違い

| 項目 | SQLite | PostgreSQL |
|------|--------|------------|
| 自動増分 | `AUTOINCREMENT` | `SERIAL` |
| 日時型 | `DATETIME` | `TIMESTAMP` |
| 文字列型 | `TEXT` | `VARCHAR(255)` または `TEXT` |
| 競合処理 | `INSERT OR IGNORE` | `ON CONFLICT DO NOTHING` |

## ✅ デプロイ完了チェックリスト

- [ ] `config.php`にゼミサーバーのDB情報を設定
- [ ] すべてのファイルをアップロード
- [ ] `init_postgresql.sql`を実行
- [ ] テーブルが作成されたことを確認
- [ ] ユーザーが登録されたことを確認
- [ ] ブラウザでアクセスできることを確認
- [ ] ログインできることを確認
- [ ] 商品管理、発注計算などの機能が動作することを確認

## 🔒 セキュリティ注意事項

### 本番環境での推奨設定

1. **パスワード変更**
   ```sql
   UPDATE users SET password = '新しいハッシュ' WHERE username = 'admin';
   ```

2. **config.phpの保護**
   - データベースパスワードが含まれるため、Webから直接アクセスできないようにする
   - `.htaccess`で保護するか、Web公開ディレクトリの外に配置

3. **エラー表示を無効化**
   本番環境では、PHPのエラー表示を無効にしてください：
   ```php
   // config.phpの先頭に追加
   ini_set('display_errors', 0);
   error_reporting(0);
   ```

---

何か問題が発生した場合は、エラーメッセージを確認してください！

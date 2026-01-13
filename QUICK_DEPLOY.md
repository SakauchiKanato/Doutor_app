# ゼミサーバーデプロイ - クイックガイド

## 🚀 3ステップでデプロイ

### ステップ1: config.phpを編集

[config.php](file:///Users/sakauchikanato/ishibashiken/asobi/hattyuu_app/config.php)を開き、以下を**ゼミサーバーの情報に変更**：

```php
define('DB_TYPE', 'pgsql');
define('DB_HOST', 'localhost'); // ← 変更
define('DB_PORT', '5432');
define('DB_NAME', 'doutor_db'); // ← 変更
define('DB_USER', 'your_username'); // ← 変更
define('DB_PASS', 'your_password'); // ← 変更
```

### ステップ2: ファイルをアップロード

すべてのファイルをゼミサーバーにアップロード（`db/doutor.db`は除く）

### ステップ3: データベース初期化

```bash
psql -U your_username -d doutor_db -f db/init_postgresql.sql
```

## ✅ 完了！

ブラウザでアクセスして、以下でログイン：
- ユーザー名: `admin`
- パスワード: `admin123`

---

## 📋 主な変更点

### 1. エラー修正
- ❌ `AUTOINCREMENT` → ✅ `SERIAL` (PostgreSQL用)
- ❌ `DATETIME` → ✅ `TIMESTAMP`
- ❌ `INSERT OR IGNORE` → ✅ `ON CONFLICT DO NOTHING`

### 2. 新しいファイル
- **[db/init_postgresql.sql](file:///Users/sakauchikanato/ishibashiken/asobi/hattyuu_app/db/init_postgresql.sql)** - PostgreSQL用SQLスクリプト
- **[DEPLOYMENT.md](file:///Users/sakauchikanato/ishibashiken/asobi/hattyuu_app/DEPLOYMENT.md)** - 詳細なデプロイ手順
- **[setup_postgresql.sh](file:///Users/sakauchikanato/ishibashiken/asobi/hattyuu_app/setup_postgresql.sh)** - 自動セットアップスクリプト

### 3. ログイン問題の解決

デフォルトユーザーは`init_postgresql.sql`に含まれています。
もしログインできない場合は、以下のSQLを実行：

```sql
INSERT INTO users (username, password) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
```

---

詳細は[DEPLOYMENT.md](file:///Users/sakauchikanato/ishibashiken/asobi/hattyuu_app/DEPLOYMENT.md)を参照してください。

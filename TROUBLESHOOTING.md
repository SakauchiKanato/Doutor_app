# ゼミサーバーデプロイ - トラブルシューティング

## 🔴 問題1: HTTP ERROR 500

HTTP 500エラーは、PHPのエラーが発生しています。以下を確認してください：

### 原因の特定

1. **PHPエラーログを確認**
   ```bash
   # ゼミサーバーにSSHでログイン後
   tail -f /var/log/apache2/error.log
   # または
   tail -f ~/public_html/error.log
   ```

2. **config.phpのパスを確認**
   - `config.php`が正しくアップロードされているか確認
   - パーミッションが正しいか確認（644または755）

### 一時的にエラー表示を有効化

`index.php`の**先頭**に以下を追加して、エラー内容を確認：

```php
<?php
// デバッグ用（本番環境では削除すること！）
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
// 以下、既存のコード...
```

---

## 🔴 問題2: データベース初期化エラー

### エラー内容
```
psql: error: db/init_postgresql.sql: No such file or directory
```

### 原因
相対パスが間違っています。アプリのルートディレクトリから実行する必要があります。

### 解決方法

#### 方法1: 正しいディレクトリに移動
```bash
# アプリのルートディレクトリに移動
cd ~/public_html/hattyuu_app  # ← 実際のパスに変更

# 現在のディレクトリを確認
pwd

# ファイルが存在するか確認
ls -la db/init_postgresql.sql

# SQLファイルを実行
psql -U knt416 -d knt416 -f db/init_postgresql.sql
```

#### 方法2: 絶対パスを使用
```bash
# 絶対パスで実行
psql -U knt416 -d knt416 -f /home/knt416/public_html/hattyuu_app/db/init_postgresql.sql
```

#### 方法3: psql内で実行
```bash
# PostgreSQLに接続
psql -U knt416 -d knt416

# psql内でファイルを実行
\i /home/knt416/public_html/hattyuu_app/db/init_postgresql.sql

# または相対パスで
\i db/init_postgresql.sql

# 確認
\dt  -- テーブル一覧
SELECT * FROM users;  -- ユーザー確認

# 終了
\q
```

---

## ✅ デプロイ完了チェックリスト

### ファイルアップロード確認
```bash
# ゼミサーバーで確認
cd ~/public_html/hattyuu_app
ls -la

# 以下のファイルが存在するか確認
# - index.php
# - login.php
# - config.php
# - auth.php
# - order_calculator.php
# - items.php
# - item_edit.php
# - calendar.php
# - event_edit.php
# - feedback.php
# - css/style.css
# - js/app.js
# - includes/header.php
# - includes/footer.php
# - db/init_postgresql.sql
```

### パーミッション確認
```bash
# PHPファイルのパーミッション
chmod 644 *.php

# ディレクトリのパーミッション
chmod 755 css js includes db
```

### データベース接続テスト
```bash
# PostgreSQLに接続できるか確認
psql -U knt416 -d knt416 -c "SELECT version();"
```

---

## 🔧 簡易デバッグスクリプト

以下のファイルを作成して、データベース接続をテストしてください：

**test_db.php**
```php
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>データベース接続テスト</h1>";

try {
    $dsn = 'pgsql:host=localhost;port=5432;dbname=knt416';
    $pdo = new PDO($dsn, 'knt416', 'nFb55bRP');
    echo "<p style='color: green;'>✅ データベース接続成功！</p>";
    
    // テーブル一覧を取得
    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>テーブル一覧:</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ データベース接続エラー: " . $e->getMessage() . "</p>";
}
?>
```

このファイルをアップロードして、ブラウザで `https://gms.gdl.jp/~knt416/hattyuu_app/test_db.php` にアクセスしてください。

---

## 📞 よくある問題

### 1. "Class 'PDO' not found"
→ PHPのPDO拡張がインストールされていない
```bash
# 確認
php -m | grep pdo
```

### 2. "could not find driver"
→ PostgreSQL用のPDOドライバがインストールされていない
```bash
# 確認
php -m | grep pgsql
```

### 3. パーミッションエラー
```bash
# すべてのPHPファイルを644に
find . -name "*.php" -exec chmod 644 {} \;

# すべてのディレクトリを755に
find . -type d -exec chmod 755 {} \;
```

---

## 🎯 次のステップ

1. **エラーログを確認** → 具体的なエラー内容を特定
2. **test_db.phpで接続テスト** → データベース接続が成功するか確認
3. **データベース初期化** → 正しいディレクトリから実行
4. **ログイン** → admin / admin123 でログイン

エラーメッセージを教えていただければ、さらに詳しくサポートできます！

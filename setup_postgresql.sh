#!/bin/bash
# ゼミサーバー用クイックセットアップスクリプト

echo "========================================="
echo "ドトール発注管理アプリ - PostgreSQL設定"
echo "========================================="
echo ""

# データベース情報の入力
read -p "データベースホスト名 [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "データベースポート [5432]: " DB_PORT
DB_PORT=${DB_PORT:-5432}

read -p "データベース名: " DB_NAME
read -p "データベースユーザー名: " DB_USER
read -sp "データベースパスワード: " DB_PASS
echo ""

# config.phpを更新
echo ""
echo "config.phpを更新しています..."

cat > config.php << EOF
<?php
/**
 * データベース接続設定
 * PostgreSQL用（ゼミサーバー）
 */

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// データベース設定
define('DB_TYPE', 'pgsql');
define('DB_HOST', '$DB_HOST');
define('DB_PORT', '$DB_PORT');
define('DB_NAME', '$DB_NAME');
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');

// SQLite用（ローカル開発用）
define('DB_PATH', __DIR__ . '/db/doutor.db');

function getDB() {
    try {
        if (DB_TYPE === 'pgsql') {
            \$dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS);
        } else {
            \$pdo = new PDO('sqlite:' . DB_PATH);
            \$pdo->exec('PRAGMA foreign_keys = ON');
        }
        
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return \$pdo;
    } catch (PDOException \$e) {
        die('データベース接続エラー: ' . \$e->getMessage());
    }
}

function h(\$str) {
    return htmlspecialchars(\$str, ENT_QUOTES, 'UTF-8');
}

function formatDate(\$date) {
    return date('Y年m月d日', strtotime(\$date));
}

function displayStars(\$level) {
    \$stars = '';
    for (\$i = 0; \$i < \$level; \$i++) {
        \$stars .= '⭐️';
    }
    return \$stars;
}
EOF

echo "✅ config.phpを更新しました"

# データベース初期化の確認
echo ""
read -p "データベースを初期化しますか？ (y/n): " INIT_DB

if [ "$INIT_DB" = "y" ]; then
    echo "データベースを初期化しています..."
    PGPASSWORD=$DB_PASS psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f db/init_postgresql.sql
    
    if [ $? -eq 0 ]; then
        echo "✅ データベースの初期化が完了しました"
    else
        echo "❌ データベースの初期化に失敗しました"
        echo "手動で実行してください: psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f db/init_postgresql.sql"
    fi
fi

echo ""
echo "========================================="
echo "セットアップ完了！"
echo "========================================="
echo ""
echo "ログイン情報:"
echo "  ユーザー名: admin"
echo "  パスワード: admin123"
echo ""
echo "ブラウザでアクセスしてください"
echo ""

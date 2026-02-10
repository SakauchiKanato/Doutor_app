<?php
/**
 * データベース接続設定
 * PostgreSQL用（ゼミサーバー）
 * 既存のusersテーブル対応版
 */

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================
// データベース設定
// ========================================
// ゼミサーバーのPostgreSQL接続情報
define('DB_TYPE', 'pgsql'); // 'pgsql' または 'sqlite'
define('DB_HOST', 'localhost'); // サーバー名
define('DB_PORT', '5432'); // PostgreSQLのポート（通常5432）
define('DB_NAME', 'knt416'); // データベース名
define('DB_USER', 'knt416'); // ユーザー名
define('DB_PASS', 'nFb55bRP'); // パスワード

// SQLite用（ローカル開発用）
define('DB_PATH', __DIR__ . '/db/doutor.db');

/**
 * データベース接続を取得
 * @return PDO
 */
function getDB() {
    try {
        if (DB_TYPE === 'pgsql') {
            // PostgreSQL接続
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
        } else {
            // SQLite接続（ローカル開発用）
            $pdo = new PDO('sqlite:' . DB_PATH);
            // 外部キー制約を有効化（SQLiteのみ）
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $pdo;
    } catch (PDOException $e) {
        die('データベース接続エラー: ' . $e->getMessage());
    }
}

/**
 * XSS対策用エスケープ関数
 * @param string $str
 * @return string
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 日付フォーマット関数
 * @param string $date
 * @return string
 */
function formatDate($date) {
    return date('Y年m月d日', strtotime($date));
}

/**
 * 星を表示する関数
 * @param int $level
 * @return string
 */
function displayStars($level) {
    $stars = '';
    for ($i = 0; $i < $level; $i++) {
        $stars .= '⭐️';
    }
    return $stars;
}

// ========================================
// API設定
// ========================================

/**
 * Gemini API キー設定
 * 
 * 設定方法（優先順位）:
 * 1. 以下の GEMINI_API_KEY_CONFIG に直接記述（推奨）
 * 2. 環境変数 GEMINI_API_KEY を設定
 * 
 * APIキーの取得方法:
 * https://aistudio.google.com/app/apikey （無料）
 */

// ここにAPIキーを直接記述できます（記述した場合、環境変数より優先されます）
define('GEMINI_API_KEY_CONFIG', 'AIzaSyCKI7nAksI_p27DI0s6Eg2NglNkYOw8Abc');  // 例: 'AIzaSyC...'

// 実際に使用するAPIキー（config設定 > 環境変数の優先順位）
if (!defined('GEMINI_API_KEY')) {
    if (!empty(GEMINI_API_KEY_CONFIG)) {
        define('GEMINI_API_KEY', GEMINI_API_KEY_CONFIG);
    } else {
        define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
    }
}

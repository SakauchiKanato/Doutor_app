<?php
/**
 * 認証チェック
 * ログインしていない場合はログイン画面にリダイレクト
 */

require_once __DIR__ . '/config.php';

// ログインページ自体では認証チェックをスキップ
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'login.php') {
    return;
}

// セッションにユーザーIDがない場合はログインページへ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ユーザー情報がセッションに保存されていない場合は、DBから取得して保存
if (!isset($_SESSION['role'])) {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['role'] = $user['role'] ?? 'user'; // デフォルトはuser
    } else {
        // ユーザーが見つからない場合はログアウト
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

/**
 * 管理者権限チェック
 * 管理者でない場合はダッシュボードにリダイレクト
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * 現在のユーザーが管理者かどうかをチェック
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * 現在のユーザーのロールを取得
 * @return string 'admin' または 'user'
 */
function getUserRole() {
    return $_SESSION['role'] ?? 'user';
}


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

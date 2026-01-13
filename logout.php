<?php
require_once 'config.php';

// セッション破棄
session_destroy();

// ログインページへリダイレクト
header('Location: login.php');
exit;

<?php
require_once 'config.php';

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $pdo = getDB();
        
        // 既存のusersテーブルはpassword_hashカラムとuser_idカラムを使用
        $stmt = $pdo->prepare('SELECT user_id, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // ログイン成功
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $username;
            header('Location: index.php');
            exit;
        } else {
            $error = 'ユーザー名またはパスワードが正しくありません。';
        }
    } else {
        $error = 'ユーザー名とパスワードを入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - ドトール発注管理</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1>☕ ドトール発注管理</h1>
            <p class="text-center mb-3">海浜幕張店</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">ユーザー名</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">パスワード</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">ログイン</button>
            </form>
            
            <div class="mt-3 text-center" style="font-size: 0.9rem; color: #7F8C8D;">
                <p>デフォルトログイン情報:</p>
                <p>ユーザー名: <strong>admin</strong></p>
                <p>パスワード: <strong>admin123</strong></p>
            </div>
        </div>
    </div>
</body>
</html>

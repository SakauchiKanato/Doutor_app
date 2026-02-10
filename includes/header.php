<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) : 'ドトール発注管理'; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <h1 class="logo">☕ ドトール発注管理</h1>
            <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="main-nav">
                <a href="index.php">🏠 ダッシュボード</a>
                <a href="order_calculator.php">📝 発注計算</a>
                <?php if (isAdmin()): ?>
                <a href="order_history.php">📦 発注履歴</a>
                <?php endif; ?>
                <a href="feedback.php">✅ 実績入力</a>
                <?php if (isAdmin()): ?>
                <a href="analytics.php">📊 精度分析</a>
                <?php endif; ?>
                <a href="calendar.php">📅 イベント</a>
                <a href="genre_analytics.php">📈 ジャンル分析</a>
                <?php if (isAdmin()): ?>
                <a href="items.php">📦 商品管理</a>
                <a href="genre_manage.php">🏷️ ジャンル管理</a>
                <a href="star_criteria_manage.php">⭐ 評価基準</a>
                <a href="ai_analysis.php">🤖 AI分析</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">ログアウト</a>
            </nav>
            <?php endif; ?>
        </div>
    </header>
    <main class="container">


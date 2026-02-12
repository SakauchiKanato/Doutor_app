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
            <a href="logout.php" class="logout-btn">ログアウト</a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="main-nav" style="flex-wrap: wrap;">
                <a href="index.php">🏠 ダッシュボード</a>
                <a href="order_calculator.php">📝 発注計算</a>
                <a href="feedback.php">✅ 実績入力</a>
                <?php if (isAdmin()): ?>
                <a href="analytics.php">📊 精度分析</a>
                <?php endif; ?>
                <a href="calendar.php">📅 イベント</a>
                <a href="genre_analytics.php">📈 ジャンル分析</a>
                <a href="analytics_dashboard.php">📊 総合分析</a>
                <?php if (isAdmin()): ?>
                <a href="ai_analysis.php">🤖 AI分析</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </header>
    <main class="container">


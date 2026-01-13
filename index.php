<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = 'ダッシュボード';

$pdo = getDB();

// 今日のイベントを取得
$today = date('Y-m-d');
$stmt = $pdo->prepare('SELECT * FROM events WHERE event_date >= ? ORDER BY event_date ASC LIMIT 5');
$stmt->execute([$today]);
$upcoming_events = $stmt->fetchAll();

// 商品数を取得
$item_count = $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

// 今日の在庫記録数
$today_logs = $pdo->prepare('SELECT COUNT(*) FROM inventory_logs WHERE log_date = ?');
$today_logs->execute([$today]);
$log_count = $today_logs->fetchColumn();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📊 ダッシュボード</h2>
    </div>
    
    <div class="flex-between mb-3">
        <div>
            <p>ようこそ、<strong><?php echo h($_SESSION['username']); ?></strong> さん</p>
            <p style="color: #7F8C8D;">海浜幕張駅ナカ店 - <?php echo date('Y年m月d日'); ?></p>
        </div>
    </div>
    
    <!-- 統計情報 -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 2rem 0;">
        <div style="background: linear-gradient(135deg, #3498DB, #2980B9); color: white; padding: 1.5rem; border-radius: 10px;">
            <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo $item_count; ?></h3>
            <p>登録商品数</p>
        </div>
        <div style="background: linear-gradient(135deg, #E67E22, #D35400); color: white; padding: 1.5rem; border-radius: 10px;">
            <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo count($upcoming_events); ?></h3>
            <p>今後のイベント</p>
        </div>
        <div style="background: linear-gradient(135deg, #27AE60, #229954); color: white; padding: 1.5rem; border-radius: 10px;">
            <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo $log_count; ?></h3>
            <p>本日の在庫記録</p>
        </div>
    </div>
    
    <!-- クイックアクション -->
    <div class="card-header">
        <h3>🚀 クイックアクション</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
        <a href="order_calculator.php" class="btn btn-primary" style="padding: 2rem; text-align: center; font-size: 1.1rem;">
            📝 発注計算を開始
        </a>
        <a href="items.php" class="btn btn-secondary" style="padding: 2rem; text-align: center; font-size: 1.1rem;">
            📦 商品管理
        </a>
        <a href="calendar.php" class="btn btn-secondary" style="padding: 2rem; text-align: center; font-size: 1.1rem;">
            📅 イベント確認
        </a>
        <a href="feedback.php" class="btn btn-secondary" style="padding: 2rem; text-align: center; font-size: 1.1rem;">
            ✅ 実績入力
        </a>
    </div>
</div>

<!-- 今後のイベント -->
<?php if (count($upcoming_events) > 0): ?>
<div class="card">
    <div class="card-header">
        <h3>📅 今後のイベント</h3>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>日付</th>
                <th>イベント名</th>
                <th>会場</th>
                <th>推奨星ランク</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($upcoming_events as $event): ?>
            <tr>
                <td><?php echo formatDate($event['event_date']); ?></td>
                <td><?php echo h($event['event_name']); ?></td>
                <td><?php echo h($event['venue']); ?></td>
                <td><?php echo displayStars($event['recommended_star']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- お知らせ -->
<div class="card">
    <div class="card-header">
        <h3>💡 使い方のヒント</h3>
    </div>
    <div class="alert alert-warning">
        <strong>発注のタイミング:</strong> 発注から納品まで3日かかります。今日発注すると、3日後に届きます。
    </div>
    <div class="alert alert-success">
        <strong>星ランクの目安:</strong>
        <ul style="margin-top: 0.5rem;">
            <li>⭐️ - 平日の閑散期</li>
            <li>⭐️⭐️ - 通常の平日</li>
            <li>⭐️⭐️⭐️ - 週末や小規模イベント</li>
            <li>⭐️⭐️⭐️⭐️ - 中規模イベント（幕張メッセ展示会など）</li>
            <li>⭐️⭐️⭐️⭐️⭐️ - 大規模イベント（コンサート、スタジアム満員など）</li>
        </ul>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
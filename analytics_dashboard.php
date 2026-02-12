<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = '総合分析ダッシュボード';
$pdo = getDB();

// 1. 月別消費量推移 (過去6ヶ月)
$monthly_query = "
    SELECT to_char(target_date, 'YYYY-MM') as month, SUM(actual_consumption) as total
    FROM forecasts
    WHERE actual_consumption IS NOT NULL 
    AND target_date >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '5 months')
    GROUP BY month
    ORDER BY month ASC
";
$monthly_data = $pdo->query($monthly_query)->fetchAll();

$monthly_labels = [];
$monthly_values = [];
foreach ($monthly_data as $row) {
    $monthly_labels[] = $row['month'];
    $monthly_values[] = round($row['total'], 1);
}

// 2. 曜日別平均消費量
$dow_query = "
    SELECT EXTRACT(DOW FROM target_date) as dow, AVG(actual_consumption) as avg_val
    FROM forecasts
    WHERE actual_consumption IS NOT NULL
    GROUP BY dow
    ORDER BY dow ASC
";
$dow_data = $pdo->query($dow_query)->fetchAll();

$dow_map = [0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土'];
$dow_labels = [];
$dow_values = array_fill(0, 7, 0);

foreach ($dow_data as $row) {
    $dow_values[(int)$row['dow']] = round($row['avg_val'], 1);
}
foreach ($dow_map as $dow => $label) {
    $dow_labels[] = $label;
}

// 3. 商品別消費ランキング (過去30日)
$ranking_query = "
    SELECT i.name, SUM(f.actual_consumption) as total
    FROM forecasts f
    JOIN items i ON f.item_id = i.id
    WHERE f.actual_consumption IS NOT NULL 
    AND f.target_date >= CURRENT_DATE - INTERVAL '30 days'
    GROUP BY i.name
    ORDER BY total DESC
    LIMIT 10
";
$ranking_data = $pdo->query($ranking_query)->fetchAll();

$rank_labels = [];
$rank_values = [];
foreach ($ranking_data as $row) {
    $rank_labels[] = $row['name'];
    $rank_values[] = round($row['total'], 1);
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📊 総合分析ダッシュボード</h2>
    </div>
    
    <div class="alert alert-warning">
        <strong>💡 ダッシュボードについて:</strong><br>
        AIを使用せず、蓄積された実績データを統計的に可視化しています。
    </div>

    <!-- 月別推移 & 曜日別 (2カラム) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
        
        <!-- 月別推移 -->
        <div style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #ddd;">
            <h3 style="text-align: center; margin-bottom: 1rem;">📅 月別消費トレンド (過去6ヶ月)</h3>
            <canvas id="monthlyChart"></canvas>
        </div>

        <!-- 曜日別 -->
        <div style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #ddd;">
            <h3 style="text-align: center; margin-bottom: 1rem;">📅 曜日別平均消費量</h3>
            <canvas id="dowChart"></canvas>
        </div>
    </div>

    <!-- ランキング (全幅) -->
    <div style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #ddd;">
        <h3 style="text-align: center; margin-bottom: 1rem;">🏆 商品別消費ランキング (過去30日)</h3>
        <canvas id="rankingChart" style="max-height: 400px;"></canvas>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// 共通の色設定
const chartColors = {
    red: 'rgba(255, 99, 132, 0.6)',
    blue: 'rgba(54, 162, 235, 0.6)',
    yellow: 'rgba(255, 206, 86, 0.6)',
    green: 'rgba(75, 192, 192, 0.6)',
    purple: 'rgba(153, 102, 255, 0.6)',
    orange: 'rgba(255, 159, 64, 0.6)'
};
const borderColors = {
    red: 'rgba(255, 99, 132, 1)',
    blue: 'rgba(54, 162, 235, 1)',
    yellow: 'rgba(255, 206, 86, 1)',
    green: 'rgba(75, 192, 192, 1)',
    purple: 'rgba(153, 102, 255, 1)',
    orange: 'rgba(255, 159, 64, 1)'
};

// 1. 月別チャート
const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctxMonthly, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($monthly_labels); ?>,
        datasets: [{
            label: '総消費量',
            data: <?php echo json_encode($monthly_values); ?>,
            borderColor: borderColors.blue,
            backgroundColor: chartColors.blue,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } }
    }
});

// 2. 曜日別チャート
const ctxDow = document.getElementById('dowChart').getContext('2d');
new Chart(ctxDow, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($dow_labels); ?>,
        datasets: [{
            label: '平均消費量',
            data: <?php echo json_encode($dow_values); ?>,
            backgroundColor: [
                chartColors.red, chartColors.orange, chartColors.yellow, 
                chartColors.green, chartColors.blue, chartColors.purple, chartColors.red
            ],
            borderColor: [
                borderColors.red, borderColors.orange, borderColors.yellow, 
                borderColors.green, borderColors.blue, borderColors.purple, borderColors.red
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } }
    }
});

// 3. ランキングチャート
const ctxRank = document.getElementById('rankingChart').getContext('2d');
new Chart(ctxRank, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($rank_labels); ?>,
        datasets: [{
            label: '総消費量',
            data: <?php echo json_encode($rank_values); ?>,
            backgroundColor: chartColors.orange,
            borderColor: borderColors.orange,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y', // 横棒グラフ
        plugins: { legend: { display: false } }
    }
});
</script>

<?php include 'includes/footer.php'; ?>

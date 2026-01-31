<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = 'ジャンル別データ分析';
$pdo = getDB();

// フィルター条件
$filter_genre = isset($_GET['genre']) ? (int)$_GET['genre'] : 0;
$filter_date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d');

// ジャンル一覧を取得
$genres = $pdo->query('SELECT * FROM event_genres ORDER BY genre_name ASC')->fetchAll();

// ジャンル別の平均出数サマリーを取得
$genre_summary_query = '
    SELECT eg.id, eg.genre_name, 
           COUNT(DISTINCT e.id) as event_count,
           AVG(f.actual_consumption) as avg_consumption,
           SUM(f.actual_consumption) as total_consumption
    FROM event_genres eg
    LEFT JOIN events e ON eg.id = e.genre_id
    LEFT JOIN forecasts f ON e.event_date = f.target_date
    WHERE f.actual_consumption IS NOT NULL
    GROUP BY eg.id, eg.genre_name
    ORDER BY avg_consumption DESC
';
$genre_summary = $pdo->query($genre_summary_query)->fetchAll();

// イベントあり/なしの比較データ
$event_comparison_query = '
    SELECT 
        CASE WHEN e.id IS NOT NULL THEN \'イベントあり\' ELSE \'イベントなし\' END as event_status,
        COUNT(DISTINCT f.target_date) as day_count,
        AVG(f.actual_consumption) as avg_consumption
    FROM forecasts f
    LEFT JOIN events e ON f.target_date = e.event_date
    WHERE f.actual_consumption IS NOT NULL
    GROUP BY event_status
';
$event_comparison = $pdo->query($event_comparison_query)->fetchAll();

// フィルター適用: ジャンル別・日付範囲での詳細データ
$detail_query = '
    SELECT e.event_date, e.event_name, eg.genre_name, e.expected_visitors,
           i.name as item_name, f.actual_consumption, f.predicted_consumption
    FROM events e
    LEFT JOIN event_genres eg ON e.genre_id = eg.id
    LEFT JOIN forecasts f ON e.event_date = f.target_date
    LEFT JOIN items i ON f.item_id = i.id
    WHERE f.actual_consumption IS NOT NULL
';

$params = [];
if ($filter_genre > 0) {
    $detail_query .= ' AND e.genre_id = ?';
    $params[] = $filter_genre;
}
if ($filter_date_from) {
    $detail_query .= ' AND e.event_date >= ?';
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $detail_query .= ' AND e.event_date <= ?';
    $params[] = $filter_date_to;
}

$detail_query .= ' ORDER BY e.event_date DESC, i.name ASC LIMIT 100';

$stmt = $pdo->prepare($detail_query);
$stmt->execute($params);
$detail_data = $stmt->fetchAll();

// グラフ用データ: ジャンル別平均出数
$chart_labels = [];
$chart_data = [];
foreach ($genre_summary as $row) {
    $chart_labels[] = $row['genre_name'];
    $chart_data[] = round($row['avg_consumption'], 1);
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📊 ジャンル別データ分析</h2>
    </div>
    
    <div class="alert alert-warning">
        <strong>💡 ジャンル別分析について:</strong><br>
        イベントのジャンルごとに出数の傾向を分析します。ジャンルによって時間帯や商品の売れ行きが異なるため、より正確な発注計画が立てられます。
    </div>
    
    <!-- ジャンル別サマリー -->
    <h3>🏷️ ジャンル別サマリー</h3>
    
    <?php if (count($genre_summary) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>ジャンル</th>
                <th>イベント数</th>
                <th>平均出数</th>
                <th>合計出数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($genre_summary as $row): ?>
            <tr>
                <td>
                    <span style="padding: 0.25rem 0.5rem; background: #e3f2fd; color: #1976d2; border-radius: 3px;">
                        🏷️ <?php echo h($row['genre_name']); ?>
                    </span>
                </td>
                <td><?php echo $row['event_count']; ?> 件</td>
                <td><strong><?php echo round($row['avg_consumption'], 1); ?></strong></td>
                <td><?php echo round($row['total_consumption'], 1); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-warning">
        ジャンル別のデータがまだありません。イベントにジャンルを設定し、実績を入力してください。
    </div>
    <?php endif; ?>
    
    <!-- イベントあり/なし比較 -->
    <h3 style="margin-top: 2rem;">📈 イベントあり/なし比較</h3>
    
    <?php if (count($event_comparison) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>状態</th>
                <th>日数</th>
                <th>平均出数</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($event_comparison as $row): ?>
            <tr>
                <td><strong><?php echo h($row['event_status']); ?></strong></td>
                <td><?php echo $row['day_count']; ?> 日</td>
                <td style="font-weight: bold; color: var(--doutor-brown);">
                    <?php echo round($row['avg_consumption'], 1); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <!-- グラフ表示 -->
    <?php if (count($genre_summary) > 0): ?>
    <h3 style="margin-top: 2rem;">📊 ジャンル別平均出数グラフ</h3>
    <div style="max-width: 800px; margin: 2rem auto;">
        <canvas id="genreChart"></canvas>
    </div>
    <?php endif; ?>
    
    <!-- 詳細データフィルター -->
    <h3 style="margin-top: 2rem;">🔍 詳細データ検索</h3>
    
    <form method="GET" action="" style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="genre">ジャンル</label>
                <select id="genre" name="genre" class="form-control">
                    <option value="0">-- すべて --</option>
                    <?php foreach ($genres as $genre): ?>
                    <option value="<?php echo $genre['id']; ?>" <?php echo $filter_genre == $genre['id'] ? 'selected' : ''; ?>>
                        <?php echo h($genre['genre_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="date_from">開始日</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo h($filter_date_from); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="date_to">終了日</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo h($filter_date_to); ?>">
            </div>
            
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 検索</button>
            </div>
        </div>
    </form>
    
    <!-- 詳細データ表示 -->
    <?php if (count($detail_data) > 0): ?>
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>イベント名</th>
                    <th>ジャンル</th>
                    <th>来場予想数</th>
                    <th>商品名</th>
                    <th>予測出数</th>
                    <th>実際出数</th>
                    <th>差分</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail_data as $row): ?>
                <?php 
                    $diff = $row['actual_consumption'] - $row['predicted_consumption'];
                    $diff_color = $diff > 0 ? 'var(--danger)' : ($diff < 0 ? 'var(--success)' : 'inherit');
                ?>
                <tr>
                    <td><?php echo formatDate($row['event_date']); ?></td>
                    <td><?php echo h($row['event_name']); ?></td>
                    <td>
                        <?php if ($row['genre_name']): ?>
                            <span style="padding: 0.25rem 0.5rem; background: #e3f2fd; color: #1976d2; border-radius: 3px; font-size: 0.85rem;">
                                <?php echo h($row['genre_name']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['expected_visitors']): ?>
                            <?php echo number_format($row['expected_visitors']); ?> 人
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($row['item_name']); ?></td>
                    <td><?php echo $row['predicted_consumption']; ?></td>
                    <td><strong><?php echo $row['actual_consumption']; ?></strong></td>
                    <td style="color: <?php echo $diff_color; ?>;">
                        <?php echo ($diff > 0 ? '+' : '') . $diff; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">
        検索条件に一致するデータがありません。
    </div>
    <?php endif; ?>
</div>

<?php if (count($genre_summary) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('genreChart').getContext('2d');
const genreChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: '平均出数',
            data: <?php echo json_encode($chart_data); ?>,
            backgroundColor: [
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 99, 132, 0.6)',
                'rgba(255, 206, 86, 0.6)',
                'rgba(75, 192, 192, 0.6)',
                'rgba(153, 102, 255, 0.6)',
                'rgba(255, 159, 64, 0.6)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            title: {
                display: true,
                text: 'ジャンル別平均出数',
                font: {
                    size: 16
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: '平均出数'
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

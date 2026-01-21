<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = '実績データ分析';
$pdo = getDB();
$message = '';

// 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_avg'])) {
    try {
        $pdo->beginTransaction();
        $count = 0;
        foreach ($_POST['updates'] as $item_id => $stars) {
            foreach ($stars as $level => $avg_val) {
                if ($avg_val === '') continue;
                
                $stmt = $pdo->prepare('UPDATE star_definitions SET consumption_per_day = ? WHERE item_id = ? AND star_level = ?');
                $stmt->execute([(int)$avg_val, (int)$item_id, (int)$level]);
                $count++;
            }
        }
        $pdo->commit();
        $message = "<div class='alert alert-success'>✅ {$count} 件の設定を更新しました。</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">❌ 更新に失敗しました: ' . h($e->getMessage()) . '</div>';
    }
}

// 星ランク別・商品別の平均消費量を取得
// 少なくとも1回は実績が入力されているデータのみを対象とする
$analytics_query = '
    SELECT i.id as item_id, i.name as item_name, f.star_level, 
           COUNT(f.id) as sample_count,
           AVG(f.actual_consumption) as avg_actual,
           COALESCE(sd.consumption_per_day, 0) as current_setting
    FROM items i
    JOIN forecasts f ON i.id = f.item_id
    LEFT JOIN star_definitions sd ON i.id = sd.item_id AND f.star_level = sd.star_level
    WHERE f.actual_consumption IS NOT NULL
    GROUP BY i.id, i.name, f.star_level, sd.consumption_per_day
    ORDER BY i.name ASC, f.star_level ASC
';
$stats = $pdo->query($analytics_query)->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📊 実績データ分析と最適化</h2>
    </div>

    <?php echo $message; ?>

    <p class="mb-3">これまでの実績入力データを分析し、各星ランクごとの最適な消費量を算出しました。<br>
    「現在の設定」と「実績平均」にズレがある場合、反映ボタンで設定を最新の状態に更新できます。</p>

    <form method="POST" action="">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th>星ランク</th>
                        <th>サンプル数</th>
                        <th>現在の設定</th>
                        <th style="background: #fdf2e9; color: #6B4423;" >実績平均</th>
                        <th>ズレ</th>
                        <th>反映</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats)): ?>
                    <tr>
                        <td colspan="7" class="text-center">分析に十分な実績データがまだありません。まずは「実績入力」を数日分行ってください。</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($stats as $row): ?>
                    <?php 
                        $avg = round($row['avg_actual'], 1);
                        $diff = $avg - $row['current_setting'];
                        $diff_color = $diff > 0 ? 'var(--danger)' : ($diff < 0 ? 'var(--success)' : 'inherit');
                    ?>
                    <tr>
                        <td><strong><?php echo h($row['item_name']); ?></strong></td>
                        <td><?php echo displayStars($row['star_level']); ?></td>
                        <td><?php echo $row['sample_count']; ?> 日分</td>
                        <td><?php echo $row['current_setting']; ?></td>
                        <td style="background: #fdf2e9; font-weight: bold;"><?php echo $avg; ?></td>
                        <td style="color: <?php echo $diff_color; ?>;">
                            <?php echo ($diff > 0 ? '+' : '') . $diff; ?>
                        </td>
                        <td>
                            <div class="flex gap-1" style="align-items: center;">
                                <input type="checkbox" name="apply_row[]" class="apply-check" data-item="<?php echo $row['item_id']; ?>" data-level="<?php echo $row['star_level']; ?>" data-val="<?php echo round($avg); ?>">
                                <input type="hidden" name="updates[<?php echo $row['item_id']; ?>][<?php echo $row['star_level']; ?>]" value="" class="update-val">
                                <span>適用</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" name="apply_avg" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;" id="submit-btn" disabled>
                🚀 選択した実績平均を設定に反映する
            </button>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.apply-check').forEach(check => {
    check.addEventListener('change', function() {
        const valInput = this.nextElementSibling;
        valInput.value = this.checked ? this.dataset.val : '';
        
        // ボタンの有効化制御
        const anyChecked = document.querySelectorAll('.apply-check:checked').length > 0;
        document.getElementById('submit-btn').disabled = !anyChecked;
    });
});
</script>

<?php include 'includes/footer.php'; ?>

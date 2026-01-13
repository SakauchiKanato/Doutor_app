<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = '一括実績入力';
$pdo = getDB();

$target_date = $_GET['date'] ?? date('Y-m-d');
$message = '';

// 保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    try {
        $pdo->beginTransaction();
        $target_date = $_POST['target_date'];
        
        // 当日の星ランクを取得（予測がない場合のため）
        $stmt = $pdo->prepare('SELECT star_level FROM daily_stars WHERE target_date = ?');
        $stmt->execute([$target_date]);
        $day_star = $stmt->fetchColumn() ?: 1;

        foreach ($_POST['actual'] as $item_id => $actual) {
            if ($actual === '') continue;
            
            $item_id = (int)$item_id;
            $actual_val = (int)$actual;
            
            // 実績を保存。予測がない場合は、当日の星ランクと予測消費量0で新規作成する。
            $stmt = $pdo->prepare('
                INSERT INTO forecasts (item_id, forecast_date, target_date, star_level, predicted_consumption, actual_consumption)
                VALUES (?, ?, ?, ?, 0, ?)
                ON CONFLICT (item_id, target_date) DO UPDATE SET 
                    actual_consumption = EXCLUDED.actual_consumption,
                    star_level = COALESCE(forecasts.star_level, EXCLUDED.star_level)
            ');
            $stmt->execute([$item_id, $target_date, $target_date, $day_star, $actual_val]);
        }
        
        $pdo->commit();
        $message = '<div class="alert alert-success">✅ 実績を保存しました。</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">❌ 保存に失敗しました: ' . h($e->getMessage()) . '</div>';
    }
}

// 該当日の全商品、予測データ、および日の星ランク設定を取得
$stmt = $pdo->prepare('
    SELECT i.id, i.name, i.unit, 
           f.predicted_consumption, f.actual_consumption, 
           COALESCE(f.star_level, ds.star_level, 1) as star_level
    FROM items i
    LEFT JOIN forecasts f ON i.id = f.item_id AND f.target_date = ?
    LEFT JOIN daily_stars ds ON ds.target_date = ?
    ORDER BY i.name ASC
');
$stmt->execute([$target_date, $target_date]);
$data = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header flex-between">
        <h2>✅ 一括実績入力</h2>
        <form method="GET" action="" class="flex gap-1">
            <input type="date" name="date" class="form-control" value="<?php echo h($target_date); ?>" onchange="this.form.submit()">
        </form>
    </div>

    <?php echo $message; ?>

    <div class="alert alert-warning">
        <strong>📊 振り返りの重要性</strong><br>
        「<?php echo formatDate($target_date); ?>」の実績を入力してください。<br>
        予測と実績のズレを確認することで、今後の星ランク設定の精度が高まります。
    </div>

    <form method="POST" action="">
        <input type="hidden" name="target_date" value="<?php echo h($target_date); ?>">
        
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 150px;">商品名</th>
                        <th>予測星</th>
                        <th>予測消費量</th>
                        <th style="background: #e9f7ef;">実際の消費量</th>
                        <th>差分</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <?php 
                        $has_forecast = ($row['predicted_consumption'] !== null);
                        $diff = null;
                        if ($has_forecast && $row['actual_consumption'] !== null) {
                            $diff = $row['actual_consumption'] - $row['predicted_consumption'];
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo h($row['name']); ?></strong></td>
                        <td><?php echo $has_forecast ? displayStars($row['star_level']) : '<small style="color:#ccc;">予測なし</small>'; ?></td>
                        <td><?php echo $has_forecast ? $row['predicted_consumption'] . ' ' . h($row['unit']) : '-'; ?></td>
                        <td style="background: #e9f7ef;">
                            <input type="number" name="actual[<?php echo $row['id']; ?>]" 
                                   class="form-control" style="width: 80px;" 
                                   value="<?php echo $row['actual_consumption']; ?>" min="0">
                        </td>
                        <td>
                            <?php if ($diff !== null): ?>
                                <span style="color: <?php echo $diff > 0 ? 'var(--danger)' : ($diff < 0 ? 'var(--success)' : 'inherit'); ?>;">
                                    <?php echo ($diff > 0 ? '+' : '') . $diff; ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" name="save" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
                💾 実績を一括保存
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

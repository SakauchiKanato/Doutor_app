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

        foreach ($_POST['remaining_stock'] as $item_id => $remaining) {
            if ($remaining === '') continue;
            
            $item_id = (int)$item_id;
            $remaining_val = (int)$remaining;
            
            // 予測消費量の取得（保存用：既存があればそれ、なければ今回のデフォルト）
            $stmt = $pdo->prepare('
                SELECT COALESCE(f.predicted_consumption, sd.consumption_per_day, 0)
                FROM items i
                LEFT JOIN forecasts f ON i.id = f.item_id AND f.target_date = ?
                LEFT JOIN daily_stars ds ON ds.target_date = ?
                LEFT JOIN star_definitions sd ON i.id = sd.item_id AND sd.star_level = COALESCE(f.star_level, ds.star_level, ?)
                WHERE i.id = ?
            ');
            $stmt->execute([$target_date, $target_date, $day_star, $item_id]);
            $pred_val = (int)$stmt->fetchColumn();

            // 実際の消費量を計算: (朝の在庫 + 今日の入荷) - 閉店時の在庫
            // 1. 朝の在庫 (今日の開始在庫、または昨日の閉店在庫)
            $stmt = $pdo->prepare('SELECT quantity FROM inventory_logs WHERE item_id = ? AND log_date = ?');
            $stmt->execute([$item_id, $target_date]);
            $morning_stock = $stmt->fetchColumn();
            
            if ($morning_stock === false) {
                // 昨日の閉店在庫をチェック
                $yesterday = date('Y-m-d', strtotime($target_date . ' -1 day'));
                $stmt = $pdo->prepare('SELECT remaining_stock FROM forecasts WHERE item_id = ? AND target_date = ?');
                $stmt->execute([$item_id, $yesterday]);
                $morning_stock = $stmt->fetchColumn() ?: 0;
            }

            // 2. 今日の入荷
            $stmt = $pdo->prepare('SELECT quantity FROM orders WHERE item_id = ? AND delivery_date = ?');
            $stmt->execute([$item_id, $target_date]);
            $arrival_qty = $stmt->fetchColumn() ?: 0;

            // 3. 消費量計算
            $actual_val = ($morning_stock + $arrival_qty) - $remaining_val;
            if ($actual_val < 0) $actual_val = 0; // 念のため

            // 実績を保存
            $stmt = $pdo->prepare('
                INSERT INTO forecasts (item_id, forecast_date, target_date, star_level, predicted_consumption, actual_consumption, remaining_stock)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (item_id, target_date) DO UPDATE SET 
                    actual_consumption = EXCLUDED.actual_consumption,
                    remaining_stock = EXCLUDED.remaining_stock,
                    star_level = COALESCE(forecasts.star_level, EXCLUDED.star_level),
                    predicted_consumption = CASE 
                        WHEN forecasts.predicted_consumption IS NULL OR forecasts.predicted_consumption = 0 THEN EXCLUDED.predicted_consumption 
                        ELSE forecasts.predicted_consumption 
                    END
            ');
            $stmt->execute([$item_id, $target_date, $target_date, $day_star, $pred_val, $actual_val, $remaining_val]);
        }
        
        $pdo->commit();
        $message = '<div class="alert alert-success">✅ 実績を保存しました。</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">❌ 保存に失敗しました: ' . h($e->getMessage()) . '</div>';
    }
}

// 該当日の全商品、予測データ、および日の星ランク設定を取得
try {
    $yesterday = date('Y-m-d', strtotime($target_date . ' -1 day'));
    $stmt = $pdo->prepare('
        SELECT i.id, i.name, i.unit, 
               f.predicted_consumption, f.actual_consumption, f.remaining_stock,
               COALESCE(f.star_level, ds.star_level) as star_level,
               sd.consumption_per_day as def_predicted,
               COALESCE(inv.quantity, f_yest.remaining_stock, 0) as start_stock,
               COALESCE(ord.quantity, 0) as arrival_qty
        FROM items i
        LEFT JOIN forecasts f ON i.id = f.item_id AND f.target_date = ?
        LEFT JOIN daily_stars ds ON ds.target_date = ?
        LEFT JOIN star_definitions sd ON i.id = sd.item_id AND sd.star_level = COALESCE(f.star_level, ds.star_level)
        LEFT JOIN inventory_logs inv ON i.id = inv.item_id AND inv.log_date = ?
        LEFT JOIN forecasts f_yest ON i.id = f_yest.item_id AND f_yest.target_date = ?
        LEFT JOIN orders ord ON i.id = ord.item_id AND ord.delivery_date = ?
        ORDER BY i.name ASC
    ');
    $stmt->execute([$target_date, $target_date, $target_date, $yesterday, $target_date]);
    $data = $stmt->fetchAll();
} catch (PDOException $e) {
    // カラムが存在しない場合に備えてエラーハンドリング
    include 'includes/header.php';
    echo '<div class="alert alert-danger">';
    echo '<h3>❌ データベースの更新が必要です</h3>';
    echo '<p>新機能（残り在庫入力）に必要なデータベースの更新が完了していない可能性があります。</p>';
    echo '<p><a href="db_fix.php" class="btn btn-primary">ここをクリックして自動修復を実行してください</a></p>';
    echo '<hr><small>詳細なエラー: ' . h($e->getMessage()) . '</small>';
    echo '</div>';
    include 'includes/footer.php';
    exit;
}

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
        <strong>📊 閉店時の残り在庫入力</strong><br>
        「<?php echo formatDate($target_date); ?>」の閉店直後（片付け後）の在庫数を入力してください。<br>
        朝の在庫と入荷数から消費量を自動計算し、予測とのズレを分析します。
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
                        <th style="background: #e9f7ef; color: #6B4423;">閉店時の在庫数</th>
                        <th>（開始量）</th>
                        <th>計算された消費量</th>
                        <th>差分</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <?php 
                        // 予測消費量は、forecastsにあればそれ、なければ定義から（ただし星ランクがない、または定義がない場合は0）
                        $predicted = ($row['predicted_consumption'] !== null && $row['predicted_consumption'] > 0) 
                                     ? $row['predicted_consumption'] 
                                     : ($row['def_predicted'] ?? 0);
                        
                        $diff = null;
                        if ($predicted > 0 && $row['actual_consumption'] !== null) {
                            $diff = $row['actual_consumption'] - $predicted;
                        }
                        
                        // 星ランクの表示判定
                        $has_star = ($row['star_level'] !== null);
                        
                        // 開始の合計 (朝の在庫 + 納品)
                        $total_start = $row['start_stock'] + $row['arrival_qty'];
                    ?>
                    <tr>
                        <td><strong><?php echo h($row['name']); ?></strong></td>
                        <td><?php echo $has_star ? displayStars($row['star_level']) : '<small style="color:#ccc;">設定なし</small>'; ?></td>
                        <td><?php echo $predicted > 0 ? $predicted . ' ' . h($row['unit']) : '-'; ?></td>
                        <td style="background: #e9f7ef;">
                            <input type="number" name="remaining_stock[<?php echo $row['id']; ?>]" 
                                   class="form-control" style="width: 80px;" 
                                   value="<?php echo $row['remaining_stock']; ?>" min="0">
                        </td>
                        <td>
                            <small style="color: #666;" title="朝の在庫: <?php echo $row['start_stock']; ?> + 今日の入荷: <?php echo $row['arrival_qty']; ?>">
                                <?php echo $total_start; ?>
                            </small>
                        </td>
                        <td><?php echo $row['actual_consumption'] !== null ? $row['actual_consumption'] . ' ' . h($row['unit']) : '-'; ?></td>
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

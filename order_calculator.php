<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = '精密発注計算';
$pdo = getDB();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$day2 = date('Y-m-d', strtotime('+2 days'));
$day3 = date('Y-m-d', strtotime('+3 days'));

// 1. 保存処理
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    try {
        $pdo->beginTransaction();
        
        // 星ランクの保存
        $stars = [
            $today => (int)$_POST['star_today'],
            $tomorrow => (int)$_POST['star_tomorrow'],
            $day2 => (int)$_POST['star_day2'],
            $day3 => (int)$_POST['star_day3']
        ];
        foreach ($stars as $date => $level) {
            $stmt = $pdo->prepare('INSERT INTO daily_stars (target_date, star_level) VALUES (?, ?) 
                                 ON CONFLICT (target_date) DO UPDATE SET star_level = EXCLUDED.star_level');
            $stmt->execute([$date, $level]);
        }

        // 各商品のデータ保存
        foreach ($_POST['current_stock'] as $item_id => $stock) {
            $item_id = (int)$item_id;
            $current_stock = ($stock !== '') ? (int)$stock : null;
            $arrival_tomorrow = (int)($_POST['arrival_tomorrow'][$item_id] ?? 0);
            $arrival_day2 = (int)($_POST['arrival_day2'][$item_id] ?? 0);
            $order_qty = (int)($_POST['order_qty'][$item_id] ?? 0);
            
            // 在庫記録（今日）
            if ($current_stock !== null) {
                $stmt = $pdo->prepare('
                    INSERT INTO inventory_logs (item_id, log_date, quantity) VALUES (?, ?, ?)
                    ON CONFLICT (item_id, log_date) DO UPDATE SET quantity = EXCLUDED.quantity
                ');
                $stmt->execute([$item_id, $today, $current_stock]);
            }
            
            // 入荷予定（明日・明後日）の更新
            $arrivals = [
                $tomorrow => $arrival_tomorrow,
                $day2 => $arrival_day2,
                $day3 => $order_qty // 今回の発注 = 3日後の到着として保存
            ];
            foreach ($arrivals as $date => $qty) {
                $stmt = $pdo->prepare('INSERT INTO orders (item_id, order_date, delivery_date, quantity) VALUES (?, ?, ?, ?)
                                     ON CONFLICT (item_id, delivery_date) DO UPDATE SET quantity = EXCLUDED.quantity');
                $stmt->execute([$item_id, $today, $date, $qty]);
            }

            // 予測記録の保存 (3日後のフィードバック用)
            // 重複を避けるため ON CONFLICT を追加。消費量定義がない場合は0をデフォルトにする。
            $stmt = $pdo->prepare('
                INSERT INTO forecasts (item_id, forecast_date, target_date, star_level, predicted_consumption) 
                VALUES (?, ?, ?, ?, COALESCE((SELECT consumption_per_day FROM star_definitions WHERE item_id = ? AND star_level = ?), 0))
                ON CONFLICT (item_id, target_date) DO UPDATE SET 
                    forecast_date = EXCLUDED.forecast_date,
                    star_level = EXCLUDED.star_level,
                    predicted_consumption = EXCLUDED.predicted_consumption
            ');
            $stmt->execute([$item_id, $today, $day3, $stars[$day3], $item_id, $stars[$day3]]);
        }
        
        $pdo->commit();
        $message = '<div class="alert alert-success">✅ 設定と発注データを保存しました。</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="alert alert-danger">❌ 保存に失敗しました: ' . h($e->getMessage()) . '</div>';
    }
}

// 2. データの取得
// 保存された星ランクの取得
$saved_stars = [];
$stmt = $pdo->prepare('SELECT target_date, star_level FROM daily_stars WHERE target_date BETWEEN ? AND ?');
$stmt->execute([$today, $day3]);
while ($row = $stmt->fetch()) {
    $saved_stars[$row['target_date']] = $row['star_level'];
}

// イベント情報の取得
$upcoming_events = [];
for ($i = 0; $i <= 3; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $stmt = $pdo->prepare('SELECT * FROM events WHERE event_date = ?');
    $stmt->execute([$date]);
    $upcoming_events[$date] = $stmt->fetch();
}

// 商品情報の取得（入荷予定と当日の入力済みデータを優先）
$items = $pdo->query('
    SELECT i.*, 
    s1.consumption_per_day as s1, s2.consumption_per_day as s2, 
    s3.consumption_per_day as s3, s4.consumption_per_day as s4, 
    s5.consumption_per_day as s5,
    o1.quantity as arrival1, o2.quantity as arrival2,
    o_today.quantity as saved_order_qty,
    inv.quantity as saved_stock
    FROM items i
    LEFT JOIN star_definitions s1 ON i.id = s1.item_id AND s1.star_level = 1
    LEFT JOIN star_definitions s2 ON i.id = s2.item_id AND s2.star_level = 2
    LEFT JOIN star_definitions s3 ON i.id = s3.item_id AND s3.star_level = 3
    LEFT JOIN star_definitions s4 ON i.id = s4.item_id AND s4.star_level = 4
    LEFT JOIN star_definitions s5 ON i.id = s5.item_id AND s5.star_level = 5
    LEFT JOIN orders o1 ON i.id = o1.item_id AND o1.delivery_date = \'' . $tomorrow . '\'
    LEFT JOIN orders o2 ON i.id = o2.item_id AND o2.delivery_date = \'' . $day2 . '\'
    LEFT JOIN orders o_today ON i.id = o_today.item_id AND o_today.delivery_date = \'' . $day3 . '\' AND o_today.order_date = \'' . $today . '\'
    LEFT JOIN inventory_logs inv ON i.id = inv.item_id AND inv.log_date = \'' . $today . '\'
    ORDER BY i.name ASC
')->fetchAll();

// 直近の発注記録を取得（下部表示用）
$recent_orders = $pdo->query('
    SELECT o.*, i.name as item_name
    FROM orders o
    JOIN items i ON o.item_id = i.id
    ORDER BY o.created_at DESC
    LIMIT 10
')->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header flex-between">
        <h2>📝 精密発注計算</h2>
        <div class="flex gap-1" style="flex-wrap: wrap;">
            <?php if (isAdmin()): ?>
            <a href="items.php" class="btn btn-secondary">📦 商品管理</a>
            <a href="order_history.php" class="btn btn-secondary">📦 発注履歴</a>
            <a href="star_criteria_manage.php" class="btn btn-primary">⭐ 評価基準</a>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $message; ?>
    
    <div class="alert alert-warning">
        <strong>⏰ 納品リードタイム考慮中</strong><br>
        今日の発注分は <strong><?php echo date('m/d', strtotime('+3 days')); ?></strong> に届きます。<br>
        それまでの入荷予定（明日・明後日の納品）も計算に含めています。
    </div>

    <form method="POST" action="" id="bulk-order-form">
        <!-- 共通星ランク選択エリア -->
        <div style="background: #fdf2e9; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; border: 1px solid #e67e22;">
            <h3 style="color: var(--doutor-brown); margin-bottom: 1rem;">⭐️ 全商品共通・星ランク予測</h3>
            
            <div class="bulk-star-selector" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <?php 
                $date_keys = [$today, $tomorrow, $day2, $day3];
                $labels = ['今日', '明日', '明後日', '3日後'];
                foreach ($date_keys as $idx => $date): 
                    $label = $labels[$idx] . ' (' . date('m/d', strtotime($date)) . ')';
                    // 優先順位: 1. 保存された値, 2. イベント推奨値, 3. デフォルト1
                    $val = $saved_stars[$date] ?? ($upcoming_events[$date]['recommended_star'] ?? 1);
                    $key_name = ($idx == 0 ? 'today' : ($idx == 1 ? 'tomorrow' : ($idx == 2 ? 'day2' : 'day3')));
                ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-weight: bold;"><?php echo $label; ?></label>
                    <?php if ($upcoming_events[$date]): ?>
                        <div style="font-size: 0.8rem; color: #e67e22; margin-bottom: 0.3rem;">
                            🚩 <?php echo h($upcoming_events[$date]['event_name']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="star-selector small">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-btn <?php echo ($val == $i) ? 'active' : ''; ?>" 
                                data-group="bulk_<?php echo $key_name; ?>" data-value="<?php echo $i; ?>" data-input="star_<?php echo $key_name; ?>_input">
                            <?php echo $i; ?>
                        </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="star_<?php echo $key_name; ?>_input" class="bulk-star-input" name="star_<?php echo $key_name; ?>" value="<?php echo $val; ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="table" id="calc-table">
                <thead>
                    <tr>
                        <th style="min-width: 150px;">商品名</th>
                        <th>安全在庫</th>
                        <th>現在庫 <span style="color: var(--danger);">*</span></th>
                        <th title="明日届く予定数">🛬明日</th>
                        <th title="明後日届く予定数">🛬明後日</th>
                        <th style="background: #fff5e6; color: var(--doutor-brown);">今回発注量</th>
                        <th>単位</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr class="calc-row" 
                        data-id="<?php echo $item['id']; ?>"
                        data-s1="<?php echo $item['s1'] ?? 0; ?>"
                        data-s2="<?php echo $item['s2'] ?? 0; ?>"
                        data-s3="<?php echo $item['s3'] ?? 0; ?>"
                        data-s4="<?php echo $item['s4'] ?? 0; ?>"
                        data-s5="<?php echo $item['s5'] ?? 0; ?>"
                        data-safety="<?php echo $item['safety_stock']; ?>">
                        <td><strong><?php echo h($item['name']); ?></strong></td>
                        <td><?php echo $item['safety_stock']; ?></td>
                        <td>
                            <input type="number" name="current_stock[<?php echo $item['id']; ?>]" 
                                   class="form-control stock-input q-input" style="width: 70px;" 
                                   value="<?php echo $item['saved_stock']; ?>" placeholder="0" min="0">
                        </td>
                        <td>
                            <input type="number" name="arrival_tomorrow[<?php echo $item['id']; ?>]" 
                                   class="form-control arrival-1 q-input" style="width: 60px;" 
                                   value="<?php echo $item['arrival1'] ?? 0; ?>" min="0">
                        </td>
                        <td>
                            <input type="number" name="arrival_day2[<?php echo $item['id']; ?>]" 
                                   class="form-control arrival-2 q-input" style="width: 60px;" 
                                   value="<?php echo $item['arrival2'] ?? 0; ?>" min="0">
                        </td>
                        <td style="background: #fff5e6;">
                            <input type="number" name="order_qty[<?php echo $item['id']; ?>]" 
                                   class="form-control final-order-qty" style="width: 70px; font-weight: bold; font-size: 1.1rem;" 
                                   value="<?php echo $item['saved_order_qty'] ?? 0; ?>" min="0">
                        </td>
                        <td><small><?php echo h($item['unit']); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; align-items: center;">
            <button type="submit" name="save" class="btn btn-primary" style="flex-grow: 1; padding: 1rem; font-size:1.1rem;">
                💾 この内容で保存・発注確定
            </button>
            <button type="button" class="btn btn-outline" onclick="window.print()" style="padding: 1rem;">🖨 印刷用</button>
        </div>
    </form>

    <!-- 直近の発注履歴プレビュー -->
    <div style="margin-top: 4rem; border-top: 2px solid #eee; padding-top: 2rem;">
        <div class="flex-between">
            <h3>📦 直近の発注・保存記録</h3>
            <a href="order_history.php" style="font-size: 0.9rem;">すべて表示 →</a>
        </div>
        <table class="table" style="font-size: 0.9rem; margin-top: 1rem;">
            <thead>
                <tr>
                    <th>保存日時</th>
                    <th>商品名</th>
                    <th>納品予定日</th>
                    <th>数量</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_orders)): ?>
                <tr><td colspan="4" class="text-center">記録がまだありません。</td></tr>
                <?php endif; ?>
                <?php foreach ($recent_orders as $ro): ?>
                <tr>
                    <td><?php echo date('m/d H:i', strtotime($ro['created_at'])); ?></td>
                    <td><?php echo h($ro['item_name']); ?></td>
                    <td><?php echo date('m/d', strtotime($ro['delivery_date'])); ?></td>
                    <td><strong><?php echo $ro['quantity']; ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.q-input { padding: 0.3rem !important; text-align: center; }
.star-selector.small .star-btn { padding: 5px 8px; font-size: 0.85rem; min-width: 30px; }
.final-order-qty { padding: 0.3rem !important; text-align: center; border-color: #e67e22; }
.final-order-qty.manual { background-color: #fffaf0; border-style: dashed; }
@media print { .header, .footer, .btn, .bulk-star-selector, .alert { display: none !important; } .card { border: none; box-shadow: none; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateCalculations(e) {
        // もし「今回発注量」自体を変更した場合は、自動計算をスキップしてスタイルを変える
        if (e && e.target.classList.contains('final-order-qty')) {
            e.target.classList.add('manual');
            return;
        }

        const s0 = parseInt(document.getElementById('star_today_input').value);
        const s1 = parseInt(document.getElementById('star_tomorrow_input').value);
        const s2 = parseInt(document.getElementById('star_day2_input').value);

        document.querySelectorAll('.calc-row').forEach(row => {
            const orderInput = row.querySelector('.final-order-qty');
            
            // ユーザーが手動で書き換えた(manualクラスがある)場合は自動計算で上書きしない
            if (orderInput.classList.contains('manual')) return;

            const safety = parseInt(row.dataset.safety);
            const stockStr = row.querySelector('.stock-input').value;
            const stock = (stockStr === '') ? null : parseInt(stockStr);
            const a1 = parseInt(row.querySelector('.arrival-1').value) || 0;
            const a2 = parseInt(row.querySelector('.arrival-2').value) || 0;
            
            // 在庫が未入力の場合は計算しない
            if (stock === null) {
                orderInput.value = 0;
                orderInput.style.color = '#BDC3C7';
                return;
            }
            
            const c0 = parseInt(row.dataset['s' + s0]) || 0;
            const c1 = parseInt(row.dataset['s' + s1]) || 0;
            const c2 = parseInt(row.dataset['s' + s2]) || 0;
            
            const totalConsum = c0 + c1 + c2;
            
            // 計算：3日後(納品時)の予測在庫 = 現在庫 + 入荷予定1 + 入荷予定2 - 消費3日間
            const predictedStockAtDelivery = stock + a1 + a2 - totalConsum;
            const orderQty = Math.max(0, safety - predictedStockAtDelivery);
            
            orderInput.value = orderQty;
            orderInput.style.color = orderQty > 0 ? '#E67E22' : '#BDC3C7';
        });
    }

    document.querySelectorAll('.q-input, .final-order-qty').forEach(input => {
        input.addEventListener('input', updateCalculations);
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('star-btn')) {
            setTimeout(updateCalculations, 50);
        }
    });

    // 初期表示。保存された値がある場合は 'manual' 扱いにする（自動計算で上書きさせないため）
    document.querySelectorAll('.final-order-qty').forEach(input => {
        if (input.value > 0) {
            input.classList.add('manual');
        }
    });

    // 在庫が入力されている場合は初期計算を実行
    updateCalculations();
});
</script>

<?php include 'includes/footer.php'; ?>

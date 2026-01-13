<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = '発注・納品履歴';
$pdo = getDB();

// 履歴データを取得（直近50件）
$stmt = $pdo->query('
    SELECT o.*, i.name as item_name, i.unit
    FROM orders o
    JOIN items i ON o.item_id = i.id
    ORDER BY o.order_date DESC, o.delivery_date DESC, i.name ASC
    LIMIT 50
');
$history = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header flex-between">
        <h2>📦 発注・納品履歴</h2>
        <a href="order_calculator.php" class="btn btn-outline">📝 発注計算に戻る</a>
    </div>

    <div class="alert alert-info">
        過去の発注記録と、今後の納品予定一覧です。
    </div>

    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>発注日</th>
                    <th>納品予定日</th>
                    <th>商品名</th>
                    <th>数量</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr>
                    <td colspan="5" class="text-center">履歴がまだありません。</td>
                </tr>
                <?php endif; ?>

                <?php foreach ($history as $row): ?>
                <?php 
                    $is_future = ($row['delivery_date'] > date('Y-m-d'));
                    $is_today = ($row['delivery_date'] == date('Y-m-d'));
                ?>
                <tr>
                    <td><?php echo formatDate($row['order_date']); ?></td>
                    <td>
                        <strong><?php echo formatDate($row['delivery_date']); ?></strong>
                        <?php if ($is_today): ?>
                            <span class="badge badge-warning">本日納品</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($row['item_name']); ?></td>
                    <td><?php echo $row['quantity']; ?> <?php echo h($row['unit']); ?></td>
                    <td>
                        <?php if ($is_future): ?>
                            <span style="color: var(--doutor-orange);">⏳ 入荷待ち</span>
                        <?php else: ?>
                            <span style="color: #7f8c8d;">✅ 納品済み</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

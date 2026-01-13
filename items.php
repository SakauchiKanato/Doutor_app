<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = '商品管理';
$pdo = getDB();

// 削除処理
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: items.php?msg=deleted');
    exit;
}

// 商品一覧を取得
$items = $pdo->query('SELECT * FROM items ORDER BY id ASC')->fetchAll();

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $message = '<div class="alert alert-success">商品を保存しました。</div>';
    } elseif ($_GET['msg'] === 'deleted') {
        $message = '<div class="alert alert-success">商品を削除しました。</div>';
    }
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header flex-between">
        <h2>📦 商品管理</h2>
        <a href="item_edit.php" class="btn btn-primary">➕ 新規商品追加</a>
    </div>
    
    <?php echo $message; ?>
    
    <?php if (count($items) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>商品名</th>
                <th>単位</th>
                <th>安全在庫数</th>
                <th>星ランク設定</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <?php
                // この商品の星ランク定義数を取得
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM star_definitions WHERE item_id = ?');
                $stmt->execute([$item['id']]);
                $star_count = $stmt->fetchColumn();
            ?>
            <tr>
                <td><?php echo $item['id']; ?></td>
                <td><strong><?php echo h($item['name']); ?></strong></td>
                <td><?php echo h($item['unit']); ?></td>
                <td><?php echo $item['safety_stock']; ?></td>
                <td>
                    <?php if ($star_count == 5): ?>
                        <span style="color: var(--success);">✅ 設定済み</span>
                    <?php else: ?>
                        <span style="color: var(--warning);">⚠️ 未設定 (<?php echo $star_count; ?>/5)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="item_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary btn-small">編集</a>
                    <a href="items.php?delete=<?php echo $item['id']; ?>" 
                       class="btn btn-danger btn-small delete-btn" 
                       data-item-name="<?php echo h($item['name']); ?>"
                       onclick="return confirm('「<?php echo h($item['name']); ?>」を削除してもよろしいですか？');">削除</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-warning">
        商品が登録されていません。「新規商品追加」ボタンから商品を追加してください。
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3>💡 商品管理のポイント</h3>
    </div>
    <ul>
        <li><strong>安全在庫数:</strong> 最低限キープしたい在庫量を設定します。</li>
        <li><strong>星ランク設定:</strong> 各商品について、星1〜5それぞれの1日あたり消費量を設定する必要があります。</li>
        <li><strong>発注計算:</strong> 星ランクが設定されていない商品は、発注計算で正確な結果が得られません。</li>
    </ul>
</div>

<?php include 'includes/footer.php'; ?>

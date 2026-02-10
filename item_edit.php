<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = '商品編集';
$pdo = getDB();

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $item_id > 0;

// 編集の場合、既存データを取得
if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        header('Location: items.php');
        exit;
    }
    
    // 星ランク定義を取得
    $stmt = $pdo->prepare('SELECT star_level, consumption_per_day FROM star_definitions WHERE item_id = ? ORDER BY star_level');
    $stmt->execute([$item_id]);
    $star_defs = [];
    while ($row = $stmt->fetch()) {
        $star_defs[$row['star_level']] = $row['consumption_per_day'];
    }
} else {
    $item = ['name' => '', 'unit' => '個', 'safety_stock' => 10];
    $star_defs = [];
}

// 保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $unit = $_POST['unit'] ?? '個';
    $safety_stock = (int)($_POST['safety_stock'] ?? 10);
    
    if ($name) {
        $pdo->beginTransaction();
        
        try {
            if ($is_edit) {
                // 更新
                $stmt = $pdo->prepare('UPDATE items SET name = ?, unit = ?, safety_stock = ? WHERE id = ?');
                $stmt->execute([$name, $unit, $safety_stock, $item_id]);
            } else {
                // 新規追加
                $stmt = $pdo->prepare('INSERT INTO items (name, unit, safety_stock) VALUES (?, ?, ?)');
                $stmt->execute([$name, $unit, $safety_stock]);
                $item_id = $pdo->lastInsertId();
            }
            
            // 星ランク定義を保存
            for ($star = 1; $star <= 5; $star++) {
                $consumption = (int)($_POST["star_{$star}"] ?? 0);
                
                // 既存の定義を削除して再挿入
                $stmt = $pdo->prepare('DELETE FROM star_definitions WHERE item_id = ? AND star_level = ?');
                $stmt->execute([$item_id, $star]);
                
                if ($consumption > 0) {
                    $stmt = $pdo->prepare('INSERT INTO star_definitions (item_id, star_level, consumption_per_day) VALUES (?, ?, ?)');
                    $stmt->execute([$item_id, $star, $consumption]);
                }
            }
            
            $pdo->commit();
            header('Location: items.php?msg=saved');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '保存中にエラーが発生しました: ' . $e->getMessage();
        }
    } else {
        $error = '商品名を入力してください。';
    }
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?php echo $is_edit ? '✏️ 商品編集' : '➕ 新規商品追加'; ?></h2>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="name">商品名 <span style="color: var(--danger);">*</span></label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo h($item['name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="unit">単位</label>
            <input type="text" id="unit" name="unit" class="form-control" 
                   value="<?php echo h($item['unit']); ?>" placeholder="例: 個、本、kg、L">
        </div>
        
        <div class="form-group">
            <label for="safety_stock">安全在庫数</label>
            <input type="number" id="safety_stock" name="safety_stock" class="form-control" 
                   value="<?php echo $item['safety_stock']; ?>" min="0">
            <small style="color: #7F8C8D;">最低限キープしたい在庫量を設定します。</small>
        </div>
        
        <hr style="margin: 2rem 0;">
        
        <h3 style="color: var(--doutor-brown); margin-bottom: 1rem;">⭐️ 星ランク別消費量設定</h3>
        <p style="color: #7F8C8D; margin-bottom: 1.5rem;">
            各星ランクにおける1日あたりの消費量を設定してください。発注計算に使用されます。
        </p>
        
        <?php for ($star = 1; $star <= 5; $star++): ?>
        <div class="form-group">
            <label for="star_<?php echo $star; ?>">
                <?php echo displayStars($star); ?> (星<?php echo $star; ?>) - 1日あたり消費量
            </label>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="number" id="star_<?php echo $star; ?>" name="star_<?php echo $star; ?>" 
                       class="form-control" style="max-width: 200px;"
                       value="<?php echo $star_defs[$star] ?? ''; ?>" min="0" placeholder="0">
                <span><?php echo h($item['unit']); ?></span>
            </div>
        </div>
        <?php endfor; ?>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">💾 保存</button>
            <a href="items.php" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>💡 星ランク設定のヒント</h3>
    </div>
    <ul>
        <li><strong>⭐️ (星1):</strong> 平日の閑散期 - 最も少ない消費量</li>
        <li><strong>⭐️⭐️ (星2):</strong> 通常の平日 - 平均的な消費量</li>
        <li><strong>⭐️⭐️⭐️ (星3):</strong> 週末や小規模イベント - やや多めの消費量</li>
        <li><strong>⭐️⭐️⭐️⭐️ (星4):</strong> 中規模イベント - かなり多い消費量</li>
        <li><strong>⭐️⭐️⭐️⭐️⭐️ (星5):</strong> 大規模イベント - 最大の消費量</li>
    </ul>
    <p style="margin-top: 1rem; color: #7F8C8D;">
        実際の消費データを元に、徐々に精度を上げていきましょう。
    </p>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = '星ランク評価基準管理';
$pdo = getDB();

$message = '';
$error = '';

// 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['consumption'] as $item_id => $stars) {
            foreach ($stars as $star_level => $new_value) {
                $new_value = (int)$new_value;
                
                // 現在の値を取得
                $stmt = $pdo->prepare('SELECT consumption_per_day FROM star_definitions WHERE item_id = ? AND star_level = ?');
                $stmt->execute([$item_id, $star_level]);
                $current = $stmt->fetch();
                $old_value = $current ? (int)$current['consumption_per_day'] : null;
                
                if ($old_value !== $new_value) {
                    // 値が変更された場合のみ更新
                    if ($current) {
                        // 既存レコードを更新
                        $stmt = $pdo->prepare('UPDATE star_definitions SET consumption_per_day = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE item_id = ? AND star_level = ?');
                        $stmt->execute([$new_value, $_SESSION['user_id'], $item_id, $star_level]);
                    } else {
                        // 新規レコードを作成
                        $stmt = $pdo->prepare('INSERT INTO star_definitions (item_id, star_level, consumption_per_day, updated_by) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$item_id, $star_level, $new_value, $_SESSION['user_id']]);
                    }
                    
                    // 変更履歴を記録
                    $stmt = $pdo->prepare('INSERT INTO star_criteria_history (item_id, star_level, old_consumption, new_consumption, changed_by) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$item_id, $star_level, $old_value, $new_value, $_SESSION['user_id']]);
                }
            }
        }
        
        $pdo->commit();
        $message = '<div class="alert alert-success">✅ 評価基準を更新しました。</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = '更新に失敗しました: ' . h($e->getMessage());
    }
}

// リセット処理（デフォルト値に戻す）
if (isset($_GET['reset']) && isset($_GET['item_id'])) {
    $item_id = (int)$_GET['item_id'];
    
    // デフォルト値（例）
    $defaults = [
        1 => 10,  // ⭐1
        2 => 15,  // ⭐2
        3 => 25,  // ⭐3
        4 => 40,  // ⭐4
        5 => 70   // ⭐5
    ];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($defaults as $star => $value) {
            $stmt = $pdo->prepare('SELECT consumption_per_day FROM star_definitions WHERE item_id = ? AND star_level = ?');
            $stmt->execute([$item_id, $star]);
            $old = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare('UPDATE star_definitions SET consumption_per_day = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE item_id = ? AND star_level = ?');
            $stmt->execute([$value, $_SESSION['user_id'], $item_id, $star]);
            
            // 履歴記録
            $stmt = $pdo->prepare('INSERT INTO star_criteria_history (item_id, star_level, old_consumption, new_consumption, changed_by, change_reason) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$item_id, $star, $old, $value, $_SESSION['user_id'], 'デフォルト値にリセット']);
        }
        
        $pdo->commit();
        header('Location: star_criteria_manage.php?msg=reset');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'リセットに失敗しました: ' . h($e->getMessage());
    }
}

// 商品と星ランク定義を取得
$items = $pdo->query('
    SELECT i.*, 
    s1.consumption_per_day as s1, s2.consumption_per_day as s2, 
    s3.consumption_per_day as s3, s4.consumption_per_day as s4, 
    s5.consumption_per_day as s5
    FROM items i
    LEFT JOIN star_definitions s1 ON i.id = s1.item_id AND s1.star_level = 1
    LEFT JOIN star_definitions s2 ON i.id = s2.item_id AND s2.star_level = 2
    LEFT JOIN star_definitions s3 ON i.id = s3.item_id AND s3.star_level = 3
    LEFT JOIN star_definitions s4 ON i.id = s4.item_id AND s4.star_level = 4
    LEFT JOIN star_definitions s5 ON i.id = s5.item_id AND s5.star_level = 5
    ORDER BY i.name ASC
')->fetchAll();

// メッセージ表示
if (isset($_GET['msg']) && $_GET['msg'] === 'reset') {
    $message = '<div class="alert alert-success">✅ デフォルト値にリセットしました。</div>';
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>⭐ 星ランク評価基準管理</h2>
    </div>
    
    <?php echo $message; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?php echo h($error); ?></div>
    <?php endif; ?>
    
    <div class="alert alert-warning">
        <strong>💡 評価基準について:</strong><br>
        各商品について、星ランクごとの1日あたり消費量を設定します。<br>
        この設定値は発注計算に使用されます。実績データに基づいて調整してください。
    </div>
    
    <form method="POST" action="">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 150px;">商品名</th>
                        <th>単位</th>
                        <th style="background: #f8f9fa;">⭐1<br><small>閑散期</small></th>
                        <th style="background: #f8f9fa;">⭐⭐2<br><small>通常</small></th>
                        <th style="background: #f8f9fa;">⭐⭐⭐3<br><small>週末</small></th>
                        <th style="background: #ffeaa7;">⭐⭐⭐⭐4<br><small>中規模</small></th>
                        <th style="background: #ffeaa7;">⭐⭐⭐⭐⭐5<br><small>大規模</small></th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?php echo h($item['name']); ?></strong></td>
                        <td><?php echo h($item['unit']); ?></td>
                        
                        <?php for ($star = 1; $star <= 5; $star++): ?>
                        <td>
                            <input type="number" 
                                   name="consumption[<?php echo $item['id']; ?>][<?php echo $star; ?>]" 
                                   class="form-control" 
                                   style="width: 80px; text-align: center;"
                                   value="<?php echo $item['s' . $star] ?? 0; ?>" 
                                   min="0" 
                                   required>
                        </td>
                        <?php endfor; ?>
                        
                        <td>
                            <a href="star_criteria_manage.php?reset=1&item_id=<?php echo $item['id']; ?>" 
                               class="btn btn-secondary btn-small"
                               onclick="return confirm('この商品の設定をデフォルト値にリセットしますか？');">
                                リセット
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 2rem; text-align: center;">
            <button type="submit" name="update" class="btn btn-primary" style="padding: 1rem 3rem;">
                💾 すべての変更を保存
            </button>
        </div>
    </form>
    
    <!-- 変更履歴 -->
    <div style="margin-top: 3rem; border-top: 2px solid #eee; padding-top: 2rem;">
        <h3>📝 最近の変更履歴</h3>
        <?php
        $history = $pdo->query('
            SELECT h.*, i.name as item_name, u.username
            FROM star_criteria_history h
            JOIN items i ON h.item_id = i.id
            LEFT JOIN users u ON h.changed_by = u.user_id
            ORDER BY h.changed_at DESC
            LIMIT 10
        ')->fetchAll();
        ?>
        
        <?php if (count($history) > 0): ?>
        <table class="table" style="font-size: 0.9rem;">
            <thead>
                <tr>
                    <th>変更日時</th>
                    <th>商品名</th>
                    <th>星ランク</th>
                    <th>変更前</th>
                    <th>変更後</th>
                    <th>変更者</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><?php echo date('Y/m/d H:i', strtotime($h['changed_at'])); ?></td>
                    <td><?php echo h($h['item_name']); ?></td>
                    <td><?php echo displayStars($h['star_level']); ?></td>
                    <td><?php echo $h['old_consumption'] ?? '-'; ?></td>
                    <td><strong><?php echo $h['new_consumption']; ?></strong></td>
                    <td><?php echo h($h['username'] ?? '不明'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-warning">まだ変更履歴がありません。</div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

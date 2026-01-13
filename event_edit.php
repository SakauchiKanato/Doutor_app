<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = 'イベント編集';
$pdo = getDB();

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $event_id > 0;

// 編集の場合、既存データを取得
if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: calendar.php');
        exit;
    }
} else {
    $event = [
        'event_date' => date('Y-m-d'),
        'event_name' => '',
        'venue' => '',
        'recommended_star' => 3,
        'memo' => ''
    ];
}

// 保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_date = $_POST['event_date'] ?? '';
    $event_name = $_POST['event_name'] ?? '';
    $venue = $_POST['venue'] ?? '';
    $recommended_star = (int)($_POST['recommended_star'] ?? 3);
    $memo = $_POST['memo'] ?? '';
    
    if ($event_date && $event_name) {
        try {
            if ($is_edit) {
                // 更新
                $stmt = $pdo->prepare('UPDATE events SET event_date = ?, event_name = ?, venue = ?, recommended_star = ?, memo = ? WHERE id = ?');
                $stmt->execute([$event_date, $event_name, $venue, $recommended_star, $memo, $event_id]);
            } else {
                // 新規追加
                $stmt = $pdo->prepare('INSERT INTO events (event_date, event_name, venue, recommended_star, memo) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$event_date, $event_name, $venue, $recommended_star, $memo]);
            }
            
            header('Location: calendar.php?msg=saved');
            exit;
        } catch (Exception $e) {
            $error = '保存中にエラーが発生しました: ' . $e->getMessage();
        }
    } else {
        $error = '日付とイベント名を入力してください。';
    }
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?php echo $is_edit ? '✏️ イベント編集' : '➕ イベント追加'; ?></h2>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="event_date">日付 <span style="color: var(--danger);">*</span></label>
            <input type="date" id="event_date" name="event_date" class="form-control" 
                   value="<?php echo h($event['event_date']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="event_name">イベント名 <span style="color: var(--danger);">*</span></label>
            <input type="text" id="event_name" name="event_name" class="form-control" 
                   value="<?php echo h($event['event_name']); ?>" 
                   placeholder="例: 幕張メッセ 展示会" required>
        </div>
        
        <div class="form-group">
            <label for="venue">会場</label>
            <select id="venue" name="venue" class="form-control">
                <option value="">-- 選択してください --</option>
                <option value="幕張メッセ" <?php echo $event['venue'] === '幕張メッセ' ? 'selected' : ''; ?>>幕張メッセ</option>
                <option value="ZOZOマリンスタジアム" <?php echo $event['venue'] === 'ZOZOマリンスタジアム' ? 'selected' : ''; ?>>ZOZOマリンスタジアム</option>
                <option value="その他" <?php echo $event['venue'] === 'その他' ? 'selected' : ''; ?>>その他</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>推奨星ランク</label>
            <div class="star-selector">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" class="star-btn <?php echo $event['recommended_star'] == $i ? 'active' : ''; ?>" 
                        data-group="recommended" data-value="<?php echo $i; ?>" data-input="recommended_star_input">
                    <?php echo displayStars($i); ?>
                </button>
                <?php endfor; ?>
            </div>
            <input type="hidden" id="recommended_star_input" name="recommended_star" value="<?php echo $event['recommended_star']; ?>">
        </div>
        
        <div class="form-group">
            <label for="memo">メモ</label>
            <textarea id="memo" name="memo" class="form-control" rows="4" 
                      placeholder="例: 全館使用、スタジアム満員予想、前回は⭐️5で対応"><?php echo h($event['memo']); ?></textarea>
        </div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">💾 保存</button>
            <a href="calendar.php" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = 'イベントジャンル管理';
$pdo = getDB();

// 削除処理
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // このジャンルを使用しているイベントがあるかチェック
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE genre_id = ?');
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $error = "このジャンルは {$count} 件のイベントで使用されているため削除できません。";
    } else {
        $stmt = $pdo->prepare('DELETE FROM event_genres WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: genre_manage.php?msg=deleted');
        exit;
    }
}

// 保存処理（新規追加・編集）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $genre_id = isset($_POST['genre_id']) ? (int)$_POST['genre_id'] : 0;
    $genre_name = trim($_POST['genre_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($genre_name) {
        try {
            if ($genre_id > 0) {
                // 編集
                $stmt = $pdo->prepare('UPDATE event_genres SET genre_name = ?, description = ? WHERE id = ?');
                $stmt->execute([$genre_name, $description, $genre_id]);
                $message = '<div class="alert alert-success">✅ ジャンルを更新しました。</div>';
            } else {
                // 新規追加
                $stmt = $pdo->prepare('INSERT INTO event_genres (genre_name, description) VALUES (?, ?)');
                $stmt->execute([$genre_name, $description]);
                $message = '<div class="alert alert-success">✅ ジャンルを追加しました。</div>';
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                $error = '同じ名前のジャンルが既に存在します。';
            } else {
                $error = '保存中にエラーが発生しました: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'ジャンル名を入力してください。';
    }
}

// 編集モード
$edit_genre = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM event_genres WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_genre = $stmt->fetch();
}

// ジャンル一覧を取得
$stmt = $pdo->query('
    SELECT eg.*, COUNT(e.id) as event_count 
    FROM event_genres eg 
    LEFT JOIN events e ON eg.id = e.genre_id 
    GROUP BY eg.id 
    ORDER BY eg.created_at ASC
');
$genres = $stmt->fetchAll();

// メッセージ表示
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = '<div class="alert alert-success">✅ ジャンルを削除しました。</div>';
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>🏷️ イベントジャンル管理</h2>
    </div>
    
    <?php if (isset($message)) echo $message; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">❌ <?php echo h($error); ?></div>
    <?php endif; ?>
    
    <div class="alert alert-warning">
        <strong>💡 ジャンル管理について:</strong><br>
        イベントのジャンルを管理します。ジャンルごとに出数の傾向を分析できるようになります。<br>
        例: 音楽イベントは昼間、ビジネスイベントは朝に売り上げが伸びる傾向など。
    </div>
    
    <!-- ジャンル追加・編集フォーム -->
    <form method="POST" action="" style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3><?php echo $edit_genre ? '✏️ ジャンル編集' : '➕ 新規ジャンル追加'; ?></h3>
        
        <?php if ($edit_genre): ?>
            <input type="hidden" name="genre_id" value="<?php echo $edit_genre['id']; ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="genre_name">ジャンル名 <span style="color: var(--danger);">*</span></label>
            <input type="text" id="genre_name" name="genre_name" class="form-control" 
                   value="<?php echo h($edit_genre['genre_name'] ?? ''); ?>" 
                   placeholder="例: スポーツイベント" required>
        </div>
        
        <div class="form-group">
            <label for="description">説明（任意）</label>
            <textarea id="description" name="description" class="form-control" rows="3" 
                      placeholder="例: スポーツ系のイベント。試合時間帯に売り上げが集中する傾向がある。"><?php echo h($edit_genre['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">
                <?php echo $edit_genre ? '💾 更新' : '➕ 追加'; ?>
            </button>
            <?php if ($edit_genre): ?>
                <a href="genre_manage.php" class="btn btn-secondary">キャンセル</a>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- ジャンル一覧 -->
    <h3>📋 登録済みジャンル一覧</h3>
    
    <?php if (count($genres) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>ジャンル名</th>
                <th>説明</th>
                <th>使用イベント数</th>
                <th>登録日</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($genres as $genre): ?>
            <tr>
                <td><strong><?php echo h($genre['genre_name']); ?></strong></td>
                <td style="max-width: 300px;"><?php echo h($genre['description']); ?></td>
                <td>
                    <?php if ($genre['event_count'] > 0): ?>
                        <span style="padding: 0.25rem 0.5rem; background: var(--doutor-cream); border-radius: 3px;">
                            <?php echo $genre['event_count']; ?> 件
                        </span>
                    <?php else: ?>
                        <span style="color: #999;">0 件</span>
                    <?php endif; ?>
                </td>
                <td><?php echo formatDate($genre['created_at']); ?></td>
                <td>
                    <a href="genre_manage.php?edit=<?php echo $genre['id']; ?>" class="btn btn-secondary btn-small">編集</a>
                    <?php if ($genre['event_count'] == 0): ?>
                        <a href="genre_manage.php?delete=<?php echo $genre['id']; ?>" 
                           class="btn btn-danger btn-small"
                           onclick="return confirm('このジャンルを削除してもよろしいですか？');">削除</a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-small" disabled title="使用中のジャンルは削除できません">削除</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-warning">
        ジャンルが登録されていません。上のフォームから新規ジャンルを追加してください。
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'auth.php';
require_once 'config.php';

$page_title = 'イベントカレンダー';
$pdo = getDB();

// 削除処理
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: calendar.php?msg=deleted');
    exit;
}

// 今月のイベントを取得（ジャンル情報も含む）
$current_month = date('Y-m');
$stmt = $pdo->prepare('
    SELECT e.*, eg.genre_name 
    FROM events e 
    LEFT JOIN event_genres eg ON e.genre_id = eg.id 
    WHERE e.event_date >= ? 
    ORDER BY e.event_date ASC
');
$stmt->execute([date('Y-m-d')]);
$events = $stmt->fetchAll();

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $message = '<div class="alert alert-success">イベントを保存しました。</div>';
    } elseif ($_GET['msg'] === 'synced') {
        $count = (int)($_GET['count'] ?? 0);
        $message = "<div class='alert alert-success'>✅ 幕張メッセから {$count} 件のイベントを同期しました。</div>";
    } elseif ($_GET['msg'] === 'error') {
        $message = '<div class="alert alert-danger">❌ 同期に失敗しました: ' . h($_GET['error'] ?? '不明なエラー') . '</div>';
    }
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header flex-between">
        <h2>📅 イベントカレンダー</h2>
        <div class="flex gap-1" style="flex-wrap: wrap;">
            <a href="sync_events.php" class="btn btn-secondary" onclick="return confirm('幕張メッセのサイトから今月のイベントを取得しますか？');">🔄 メッセ同期</a>
            <a href="event_edit.php" class="btn btn-primary">➕ イベント追加</a>
        </div>
    </div>
    
    <?php echo $message; ?>
    
    <div class="alert alert-warning">
        <strong>🎪 イベント情報の確認:</strong>
        <ul style="margin-top: 0.5rem;">
            <li><a href="https://www.m-messe.co.jp/event/" target="_blank" style="color: var(--doutor-brown);">幕張メッセ 公式イベント情報 ↗</a></li>
            <li><a href="https://www.marines.co.jp/schedule/" target="_blank" style="color: var(--doutor-brown);">ZOZOマリンスタジアム スケジュール ↗</a></li>
        </ul>
    </div>
    
    <?php if (count($events) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>日付</th>
                <th>イベント名</th>
                <th>会場</th>
                <th>ジャンル</th>
                <th>来場予想数</th>
                <th>推奨星ランク</th>
                <th>メモ</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event): ?>
            <tr>
                <td><strong><?php echo formatDate($event['event_date']); ?></strong></td>
                <td><?php echo h($event['event_name']); ?></td>
                <td>
                    <?php if ($event['venue']): ?>
                        <span style="padding: 0.25rem 0.5rem; background: var(--doutor-cream); border-radius: 3px; font-size: 0.9rem;">
                            <?php echo h($event['venue']); ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($event['genre_name']): ?>
                        <span style="padding: 0.25rem 0.5rem; background: #e3f2fd; color: #1976d2; border-radius: 3px; font-size: 0.9rem;">
                            🏷️ <?php echo h($event['genre_name']); ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #999; font-size: 0.9rem;">未設定</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($event['expected_visitors']): ?>
                        <span style="font-weight: bold; color: var(--doutor-brown);">
                            <?php echo number_format($event['expected_visitors']); ?> 人
                        </span>
                    <?php else: ?>
                        <span style="color: #999; font-size: 0.9rem;">-</span>
                    <?php endif; ?>
                </td>
                <td style="font-size: 1.2rem;"><?php echo displayStars($event['recommended_star']); ?></td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo h($event['memo']); ?>
                </td>
                <td>
                    <a href="event_edit.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary btn-small">編集</a>
                    <a href="calendar.php?delete=<?php echo $event['id']; ?>" 
                       class="btn btn-danger btn-small"
                       onclick="return confirm('このイベントを削除してもよろしいですか？');">削除</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-warning">
        今後のイベントが登録されていません。「イベント追加」ボタンからイベントを追加してください。
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3>💡 イベントカレンダーの活用方法</h3>
    </div>
    <ul>
        <li><strong>事前準備:</strong> 幕張メッセやZOZOマリンの公式サイトで、今後のイベントを確認しましょう。</li>
        <li><strong>推奨星ランク:</strong> イベントの規模に応じて、推奨する星ランクを設定しておくと、発注計算時に参考になります。</li>
        <li><strong>情報共有:</strong> メモ欄に「全館使用」「スタジアム満員予想」などの情報を記録しておくと、スタッフ全員で情報共有できます。</li>
    </ul>
</div>

<?php include 'includes/footer.php'; ?>

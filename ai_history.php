<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック（必要に応じて）
// requireAdmin();

$page_title = 'AI会話履歴';
$pdo = getDB();

// ページネーション設定
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 総件数取得
$count_stmt = $pdo->query("SELECT COUNT(*) FROM ai_chat_logs");
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// 履歴データ取得
$stmt = $pdo->prepare("
    SELECT * FROM ai_chat_logs 
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📜 AI会話履歴</h2>
    </div>

    <div class="alert alert-info">
        <strong>💡 履歴について:</strong><br>
        過去にAI（Gemini）と行ったやり取りの記録です。発注提案や分析結果を振り返ることができます。
    </div>

    <?php if (empty($logs)): ?>
        <p class="text-center">履歴はまだありません。</p>
    <?php else: ?>
        <div class="chat-history">
            <?php foreach ($logs as $log): ?>
                <div class="chat-log-item" style="border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; background: #fff;">
                    <div class="log-header" style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; margin-bottom: 1rem; color: #666;">
                        <span class="log-id">#<?php echo h($log['id']); ?></span>
                        <span class="log-date">📅 <?php echo h($log['created_at']); ?></span>
                    </div>
                    
                    <div class="log-content">
                        <div class="log-prompt" style="margin-bottom: 1.5rem;">
                            <h4 style="color: var(--doutor-brown); margin-bottom: 0.5rem;">👤 あなた（プロンプト）:</h4>
                            <div style="background: #f9f9f9; padding: 1rem; border-radius: 5px; white-space: pre-wrap; font-size: 0.95rem;">
                                <?php echo h($log['prompt']); ?>
                            </div>
                        </div>
                        
                        <div class="log-response">
                            <h4 style="color: var(--doutor-orange); margin-bottom: 0.5rem;">🤖 AI (Gemini):</h4>
                            <div style="background: #fffbf0; padding: 1rem; border-radius: 5px; border: 1px solid #ffeebb; white-space: pre-wrap; line-height: 1.6;">
                                <?php echo h($log['response']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ページネーション -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination" style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary btn-small">« 前へ</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="btn btn-primary btn-small"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>" class="btn btn-secondary btn-small" style="background: #fff; color: #333; border: 1px solid #ccc;"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary btn-small">次へ »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

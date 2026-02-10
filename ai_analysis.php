<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'includes/ai_helper.php';

// 管理者権限チェック
requireAdmin();

$page_title = 'AI在庫分析';
$pdo = getDB();

$analysis_result = null;
$suggestion_result = null;
$error_message = '';

// 在庫分析実行
if (isset($_POST['analyze'])) {
    $days = (int)($_POST['analysis_days'] ?? 7);
    $analysis_result = analyzeInventoryStatus($pdo, $days);
}

// 発注提案生成
if (isset($_POST['suggest'])) {
    // 今後のイベントを取得
    $stmt = $pdo->query("
        SELECT event_date, event_name, recommended_star, expected_visitors
        FROM events
        WHERE event_date >= CURRENT_DATE
        ORDER BY event_date ASC
        LIMIT 10
    ");
    $upcomingEvents = $stmt->fetchAll();
    
    $suggestion_result = generateOrderSuggestions($pdo, $upcomingEvents);
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>🤖 AI在庫分析</h2>
    </div>
    
    <div class="alert alert-warning">
        <strong>💡 AI分析について:</strong><br>
        Google Gemini APIを使用して、過去の発注データを分析し、改善提案を生成します。<br>
        <strong>APIキーの設定方法:</strong><br>
        1. <a href="https://aistudio.google.com/app/apikey" target="_blank">APIキーの取得（無料）</a><br>
        2. <code>config.php</code> の <code>GEMINI_API_KEY_CONFIG</code> にAPIキーを記述<br>
        　または、環境変数 <code>GEMINI_API_KEY</code> を設定
    </div>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger">❌ <?php echo h($error_message); ?></div>
    <?php endif; ?>
    
    <!-- 在庫状況分析 -->
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3>📊 在庫状況の分析</h3>
        <p>過去のデータから、予測精度や在庫管理の傾向を分析します。</p>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="analysis_days">分析期間</label>
                <select id="analysis_days" name="analysis_days" class="form-control" style="max-width: 200px;">
                    <option value="7">過去7日間</option>
                    <option value="14">過去14日間</option>
                    <option value="30">過去30日間</option>
                </select>
            </div>
            <button type="submit" name="analyze" class="btn btn-primary">
                🔍 分析を実行
            </button>
        </form>
        
        <?php if ($analysis_result): ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: white; border-radius: 8px; border: 1px solid #ddd;">
                <h4>📝 分析結果</h4>
                <?php if ($analysis_result['error']): ?>
                    <div class="alert alert-danger">
                        <?php echo h($analysis_result['message']); ?>
                    </div>
                <?php else: ?>
                    <div style="white-space: pre-wrap; font-family: inherit;">
                        <?php echo h($analysis_result['text']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 発注提案 -->
    <div style="background: #fff5e6; padding: 1.5rem; border-radius: 8px;">
        <h3>💡 AI発注提案</h3>
        <p>今後のイベントと過去のデータから、最適な発注計画を提案します。</p>
        
        <form method="POST" action="">
            <button type="submit" name="suggest" class="btn btn-primary">
                ✨ 提案を生成
            </button>
        </form>
        
        <?php if ($suggestion_result): ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: white; border-radius: 8px; border: 1px solid #e67e22;">
                <h4>📋 発注提案</h4>
                <?php if ($suggestion_result['error']): ?>
                    <div class="alert alert-danger">
                        <?php echo h($suggestion_result['message']); ?>
                    </div>
                <?php else: ?>
                    <div style="white-space: pre-wrap; font-family: inherit;">
                        <?php echo h($suggestion_result['text']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>⚙️ API設定</h3>
    </div>
    
    <div class="alert alert-warning">
        <strong>Gemini APIキーの設定方法:</strong><br>
        <strong>方法1（推奨）:</strong> <code>config.php</code> を開き、以下の行を編集:<br>
        　<code>define('GEMINI_API_KEY_CONFIG', 'あなたのAPIキー');</code><br>
        <strong>方法2:</strong> 環境変数を設定:<br>
        　<code>export GEMINI_API_KEY="your-api-key-here"</code><br>
        <br>
        📝 <a href="https://aistudio.google.com/app/apikey" target="_blank">APIキーの取得（無料）</a>
    </div>
    
    <p>
        <strong>現在の状態:</strong> 
        <?php if (empty(GEMINI_API_KEY)): ?>
            <span style="color: var(--danger);">❌ APIキーが設定されていません</span>
        <?php else: ?>
            <span style="color: var(--success);">✅ APIキーが設定されています</span>
        <?php endif; ?>
    </p>
</div>

<?php include 'includes/footer.php'; ?>

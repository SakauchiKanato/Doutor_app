<?php
/**
 * Gemini AI統合ヘルパー
 * Google Gemini APIを使用して在庫分析を行う
 */

require_once __DIR__ . '/../config.php';

// Gemini API設定
// APIキーは config.php で設定してください
// 最新のGemini 1.5 Flashモデルを使用（高速・無料枠が大きい）
define('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');

/**
 * Gemini APIにリクエストを送信
 * 
 * @param string $prompt プロンプト
 * @return array レスポンス
 */
function callGeminiAPI($prompt) {
    if (empty(GEMINI_API_KEY)) {
        return [
            'error' => true,
            'message' => 'Gemini APIキーが設定されていません。環境変数 GEMINI_API_KEY を設定してください。'
        ];
    }
    
    $url = GEMINI_API_ENDPOINT . '?key=' . GEMINI_API_KEY;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 8192
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return [
            'error' => true,
            'message' => 'API呼び出しエラー (HTTP ' . $httpCode . ')',
            'response' => $response
        ];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return [
            'error' => false,
            'text' => $result['candidates'][0]['content']['parts'][0]['text']
        ];
    }
    
    return [
        'error' => true,
        'message' => 'レスポンスの解析に失敗しました',
        'response' => $result
    ];
}

/**
 * 在庫状況を分析
 * 
 * @param PDO $pdo データベース接続
 * @param int $days 分析期間（日数）
 * @return array 分析結果
 */
function analyzeInventoryStatus($pdo, $days = 7) {
    // 過去N日分のデータを取得
    $stmt = $pdo->prepare("
        SELECT 
            f.target_date,
            i.name as item_name,
            f.star_level,
            f.predicted_consumption,
            f.actual_consumption,
            f.remaining_stock,
            e.event_name,
            eg.genre_name
        FROM forecasts f
        JOIN items i ON f.item_id = i.id
        LEFT JOIN events e ON f.target_date = e.event_date
        LEFT JOIN event_genres eg ON e.genre_id = eg.id
        WHERE f.target_date >= CURRENT_DATE - INTERVAL '$days days'
        AND f.actual_consumption IS NOT NULL
        ORDER BY f.target_date DESC
    ");
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    // データをテキスト形式に整形
    $dataText = "【過去{$days}日間の発注実績データ】\n\n";
    foreach ($data as $row) {
        $date = $row['target_date'];
        $item = $row['item_name'];
        $predicted = $row['predicted_consumption'];
        $actual = $row['actual_consumption'];
        $remaining = $row['remaining_stock'];
        $event = $row['event_name'] ?? 'なし';
        $genre = $row['genre_name'] ?? '-';
        
        $accuracy = $predicted > 0 ? round(($actual / $predicted) * 100, 1) : '-';
        
        $dataText .= "日付: {$date}\n";
        $dataText .= "商品: {$item}\n";
        $dataText .= "イベント: {$event} (ジャンル: {$genre})\n";
        $dataText .= "予測消費量: {$predicted}, 実際の消費量: {$actual} (精度: {$accuracy}%)\n";
        $dataText .= "残在庫: {$remaining}\n";
        $dataText .= "---\n";
    }
    
    // プロンプトを作成
    $prompt = <<<PROMPT
あなたはドトールコーヒーショップの在庫管理アドバイザーです。
以下のデータを分析し、改善提案を日本語で提供してください。

{$dataText}

以下の観点で分析してください：
1. 予測精度の傾向（商品別、イベント別）
2. 過剰在庫や不足の傾向
3. イベントジャンルごとの特徴
4. 具体的な改善提案（3〜5個）

分析結果は、店長が理解しやすいよう、箇条書きと具体例を交えて説明してください。
PROMPT;
    
    return callGeminiAPI($prompt);
}

/**
 * 発注提案を生成
 * 
 * @param PDO $pdo データベース接続
 * @param array $upcomingEvents 今後のイベント
 * @return array 提案結果
 */
function generateOrderSuggestions($pdo, $upcomingEvents) {
    // 商品別の平均消費量を取得
    $stmt = $pdo->query("
        SELECT 
            i.name as item_name,
            i.safety_stock,
            AVG(f.actual_consumption) as avg_consumption,
            COUNT(*) as data_count
        FROM items i
        LEFT JOIN forecasts f ON i.id = f.item_id
        WHERE f.actual_consumption IS NOT NULL
        GROUP BY i.id, i.name, i.safety_stock
    ");
    $items = $stmt->fetchAll();
    
    // イベント情報をテキスト化
    $eventText = "";
    foreach ($upcomingEvents as $event) {
        $eventText .= "- {$event['event_date']}: {$event['event_name']} (⭐️{$event['recommended_star']})\n";
    }
    
    // 商品情報をテキスト化
    $itemText = "";
    foreach ($items as $item) {
        $itemText .= "- {$item['item_name']}: 平均消費量 {$item['avg_consumption']}, 安全在庫 {$item['safety_stock']}\n";
    }
    
    $prompt = <<<PROMPT
あなたはドトールコーヒーショップの発注担当アシスタントです。
以下の情報を元に、効率的な発注計画を提案してください。

【今後のイベント】
{$eventText}

【商品別平均消費量】
{$itemText}

以下を含む発注提案を作成してください：
1. 今後3日間で優先的に発注すべき商品
2. 各商品の推奨発注量とその理由
3. 注意すべきポイント（イベントの影響など）

提案は具体的かつ実行可能な内容にしてください。
PROMPT;
    
    return callGeminiAPI($prompt);
}

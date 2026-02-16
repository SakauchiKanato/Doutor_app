<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = 'データ管理・編集';
$pdo = getDB();

$message = '';
$selected_date = $_GET['date'] ?? date('Y-m-d');

// --- 更新処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. イベントの追加・更新・削除
    if (isset($_POST['action']) && $_POST['action'] === 'update_event') {
        try {
            if (isset($_POST['delete_event_id'])) {
                // 削除
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$_POST['delete_event_id']]);
                $message = "<div class='alert alert-success'>✅ イベントを削除しました。</div>";
            } else {
                // 追加・更新
                $event_name = trim($_POST['event_name']);
                $genre_id = !empty($_POST['genre_id']) ? $_POST['genre_id'] : null;
                $event_id = !empty($_POST['event_id']) ? $_POST['event_id'] : null;
                
                if ($event_name === '') {
                    throw new Exception("イベント名を入力してください。");
                }
                
                if ($event_id) {
                    $stmt = $pdo->prepare("UPDATE events SET event_name = ?, genre_id = ? WHERE id = ?");
                    $stmt->execute([$event_name, $genre_id, $event_id]);
                    $message = "<div class='alert alert-success'>✅ イベントを更新しました。</div>";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO events (event_date, event_name, genre_id) VALUES (?, ?, ?)");
                    $stmt->execute([$selected_date, $event_name, $genre_id]);
                    $message = "<div class='alert alert-success'>✅ イベントを追加しました。</div>";
                }
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>❌ エラー: " . h($e->getMessage()) . "</div>";
        }
    }
    
    // 2. データ（実績・発注・在庫）の一括更新
    if (isset($_POST['action']) && $_POST['action'] === 'update_data') {
        try {
            $pdo->beginTransaction();
            $count = 0;
            
            foreach ($_POST['items'] as $item_id => $data) {
                // forecasts テーブル更新 (予測, 実績, 発注, 残在庫)
                // forecastsは (item_id, target_date) でユニーク
                
                // まず存在チェック
                $stmt = $pdo->prepare("SELECT id FROM forecasts WHERE item_id = ? AND target_date = ?");
                $stmt->execute([$item_id, $selected_date]);
                $fid = $stmt->fetchColumn();
                
                $predicted = $data['predicted'] !== '' ? $data['predicted'] : 0;
                $actual = $data['actual'] !== '' ? $data['actual'] : null;
                $ordered = $data['ordered'] !== '' ? $data['ordered'] : null;
                $stock = $data['remaining'] !== '' ? $data['remaining'] : null;
                
                if ($fid) {
                    $stmt = $pdo->prepare("UPDATE forecasts SET predicted_consumption = ?, actual_consumption = ?, ordered_quantity = ?, remaining_stock = ? WHERE id = ?");
                    $stmt->execute([$predicted, $actual, $ordered, $stock, $fid]);
                } else {
                    // 新規作成 (star_levelはデフォルト3とする)
                    $stmt = $pdo->prepare("INSERT INTO forecasts (item_id, target_date, predicted_consumption, actual_consumption, ordered_quantity, remaining_stock, star_level) VALUES (?, ?, ?, ?, ?, ?, 3)");
                    $stmt->execute([$item_id, $selected_date, $predicted, $actual, $ordered, $stock]);
                }
                
                // inventory_logs テーブルも更新 (実績, 在庫)
                // inventory_logs は (item_id, log_date)
                // ロジック: forecastsのactual/stockが入力されたら、inventory_logsにも反映させる連携があると良いが、
                // 現状は独立している部分もある。ここではforecastsを中心に更新するが、
                // 整合性を保つため inventory_logs も更新/挿入する。
                
                if ($actual !== null || $stock !== null) {
                    $stmt = $pdo->prepare("SELECT id FROM inventory_logs WHERE item_id = ? AND log_date = ?");
                    $stmt->execute([$item_id, $selected_date]);
                    $lid = $stmt->fetchColumn();
                    
                    $log_actual = $actual ?? 0;
                    $log_stock = $stock ?? 0; // quantityカラムに在庫が入る設計のようなので
                    // ※ 注意: inventory_logsのquantityが「在庫」なのか「消費」なのか...
                    // actual_import.phpを見ると:
                    // INSERT INTO inventory_logs (..., consumption, remaining_stock, ...)
                    // となっている。初期スキーマでは quantity だったが、migration_import_fix.php で consumption, notes が追加された?
                    // 以前の変更を確認: actual_import.php では quantity に remaining_stock を入れている。
                    // つまり inventory_logs.quantity = remaining_stock looks correct based on my previous analysis.
                    // Wait, actual_import.php Step 124 shows:
                    // Set `quantity` column to `remaining_stock`.
                    // And added `consumption` column.
                    
                    if ($lid) {
                        $stmt = $pdo->prepare("UPDATE inventory_logs SET consumption = ?, quantity = ? WHERE id = ?");
                        $stmt->execute([$log_actual, $log_stock, $lid]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO inventory_logs (item_id, log_date, consumption, quantity) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$item_id, $selected_date, $log_actual, $log_stock]);
                    }
                }
                
                $count++;
            }
            
            $pdo->commit();
            $message = "<div class='alert alert-success'>✅ {$count}件のデータを保存しました。</div>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>❌ エラー: " . h($e->getMessage()) . "</div>";
        }
    }
}

// --- データ取得 ---

// 1. その日のイベント
$events = $pdo->prepare("
    SELECT e.*, eg.genre_name 
    FROM events e 
    LEFT JOIN event_genres eg ON e.genre_id = eg.id 
    WHERE e.event_date = ?
");
$events->execute([$selected_date]);
$day_events = $events->fetchAll();

// 2. ジャンル一覧（プルダウン用）
$genres = $pdo->query("SELECT * FROM event_genres ORDER BY id ASC")->fetchAll();
// idカラム名が id か genre_id か確認が必要。init.sqlにはevent_genresがないが、usageを見るに id っぽい。
// 念のため確認: previous context `genre_analytics.php` line 14: SELECT * FROM event_genres.
// line 18: eg.id, eg.genre_name. Okay.

// 3. 商品データ一覧 (Items + Forecasts)
$items_sql = "
    SELECT 
        i.id as item_id, 
        i.name as item_name,
        f.predicted_consumption,
        f.actual_consumption,
        f.ordered_quantity,
        f.remaining_stock
    FROM items i
    LEFT JOIN forecasts f ON i.id = f.item_id AND f.target_date = ?
    ORDER BY i.id ASC
";
$data_stmt = $pdo->prepare($items_sql);
$data_stmt->execute([$selected_date]);
$items_data = $data_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="flex-between">
            <h2>📅 データ管理・編集</h2>
            <form method="GET" action="" class="flex gap-1">
                <input type="date" name="date" class="form-control" value="<?php echo h($selected_date); ?>" onchange="this.form.submit()">
                <button type="submit" class="btn btn-secondary">移動</button>
            </form>
        </div>
    </div>
    
    <?php echo $message; ?>
    
    <div class="alert alert-info">
        <strong>💡 編集モード:</strong> <?php echo h($selected_date); ?> のデータを編集中です。<br>
        イベントの追加・修正や、実績値・発注数の手動修正が行えます。
    </div>

    <!-- イベント編集セクション -->
    <div style="background: #fff; padding: 1.5rem; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 2rem;">
        <h3>🎉 イベント情報</h3>
        
        <!-- 既存イベントリスト -->
        <?php if (!empty($day_events)): ?>
            <ul style="list-style: none; padding: 0; margin-bottom: 1rem;">
            <?php foreach ($day_events as $event): ?>
                <li style="border-bottom: 1px solid #eee; padding: 0.5rem 0; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong><?php echo h($event['event_name']); ?></strong>
                        <?php if ($event['genre_name']): ?>
                            <span style="font-size: 0.85rem; background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 4px; margin-left: 0.5rem;">
                                <?php echo h($event['genre_name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="" onsubmit="return confirm('削除しますか？');">
                        <input type="hidden" name="action" value="update_event">
                        <input type="hidden" name="delete_event_id" value="<?php echo $event['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-small">削除</button>
                    </form>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: #999;">登録されたイベントはありません。</p>
        <?php endif; ?>

        <!-- 新規追加・編集フォーム -->
        <form method="POST" action="" class="flex gap-2" style="align-items: flex-end; margin-top: 1rem; background: #f9f9f9; padding: 1rem; border-radius: 5px;">
            <input type="hidden" name="action" value="update_event">
            
            <div style="flex: 1;">
                <label style="font-size: 0.9rem;">イベント名</label>
                <input type="text" name="event_name" class="form-control" placeholder="例: 感謝デー" required>
            </div>
            
            <div style="width: 200px;">
                <label style="font-size: 0.9rem;">ジャンル</label>
                <select name="genre_id" class="form-control">
                    <option value="">なし</option>
                    <?php foreach ($genres as $genre): ?>
                        <option value="<?php echo $genre['id']; ?>"><?php echo h($genre['genre_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">➕ 追加</button>
        </form>
    </div>

    <!-- データ一覧・一括編集 -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="update_data">
        
        <div style="overflow-x: auto;">
            <table class="table" id="calc-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">商品名</th>
                        <th style="width: 20%;">予測消費</th>
                        <th style="width: 20%; background: #fff3e0;">実際消費</th>
                        <th style="width: 20%;">残在庫</th>
                        <th style="width: 20%;">発注量</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items_data as $item): ?>
                    <tr>
                        <td><strong><?php echo h($item['item_name']); ?></strong></td>
                        
                        <!-- 予測 -->
                        <td>
                            <input type="number" name="items[<?php echo $item['item_id']; ?>][predicted]" 
                                   value="<?php echo h($item['predicted_consumption']); ?>" 
                                   class="form-control input-sm" style="width: 100%;">
                        </td>
                        
                        <!-- 実績 (ハイライト) -->
                        <td style="background: #fff8f0;">
                            <input type="number" name="items[<?php echo $item['item_id']; ?>][actual]" 
                                   value="<?php echo h($item['actual_consumption']); ?>" 
                                   class="form-control input-sm" style="width: 100%; border-color: #ffcc80;">
                        </td>
                        
                        <!-- 残在庫 -->
                        <td>
                            <input type="number" name="items[<?php echo $item['item_id']; ?>][remaining]" 
                                   value="<?php echo h($item['remaining_stock']); ?>" 
                                   class="form-control input-sm" style="width: 100%;">
                        </td>
                        
                        <!-- 発注量 -->
                        <td>
                            <input type="number" name="items[<?php echo $item['item_id']; ?>][ordered]" 
                                   value="<?php echo h($item['ordered_quantity']); ?>" 
                                   class="form-control input-sm" style="width: 100%;">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-center mt-3" style="position: sticky; bottom: 20px; z-index: 100;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                💾 変更を保存する
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

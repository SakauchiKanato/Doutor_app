<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = '実績データインポート';
$pdo = getDB();

// 商品一覧を取得（商品名→IDマッピング用）
$items_map = [];
$stmt = $pdo->query('SELECT id, name FROM items ORDER BY name');
while ($row = $stmt->fetch()) {
    $items_map[$row['name']] = $row['id'];
}

// CSVインポート処理
$import_message = '';
$show_import_form = false;

// 1. マッピング確定後のインポート実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_mapping']) && isset($_SESSION['actual_csv_import_data'])) {
    $mapping = $_POST['column_mapping'];
    $csv_data = $_SESSION['actual_csv_import_data']['data'];
    
    $success_count = 0;
    $error_count = 0;
    $update_count = 0;
    $errors = [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($csv_data as $index => $row) {
            $line_num = $index + 2; // ヘッダー + 1行目から
            
            // 必須項目の取得
            $date = isset($mapping['log_date']) && $mapping['log_date'] !== '' && isset($row[$mapping['log_date']]) ? trim($row[$mapping['log_date']]) : '';
            $item_name = isset($mapping['item_name']) && $mapping['item_name'] !== '' && isset($row[$mapping['item_name']]) ? trim($row[$mapping['item_name']]) : '';
            $consumption = isset($mapping['consumption']) && $mapping['consumption'] !== '' && isset($row[$mapping['consumption']]) ? (float)$row[$mapping['consumption']] : 0;
            
            // オプション項目
            $remaining = isset($mapping['remaining_stock']) && $mapping['remaining_stock'] !== '' && isset($row[$mapping['remaining_stock']]) && $row[$mapping['remaining_stock']] !== '' ? (float)$row[$mapping['remaining_stock']] : null;
            $notes = isset($mapping['notes']) && $mapping['notes'] !== '' && isset($row[$mapping['notes']]) ? trim($row[$mapping['notes']]) : '';
            
            // データ検証
            if (empty($date) || empty($item_name) || $consumption < 0) {
                 if (!empty($item_name)) {
                    $errors[] = "行{$line_num}: 必須項目不足（日付={$date}, 商品={$item_name}, 消費量={$consumption}）";
                    $error_count++;
                 }
                continue;
            }
            
            // 日付形式を変換（YYYY/MM/DD → YYYY-MM-DD）
            $date = str_replace('/', '-', $date);
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $matches)) {
                $date = sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
            } else {
                $errors[] = "行{$line_num}: 日付形式不正（{$date}）";
                $error_count++;
                continue;
            }
            
            // 商品名からIDを取得
            if (!isset($items_map[$item_name])) {
                $errors[] = "行{$line_num}: 商品未登録（{$item_name}）";
                $error_count++;
                continue;
            }
            $item_id = $items_map[$item_name];
            
            // 重複チェック（inventory_logsは日付+商品で一意）
            $stmt = $pdo->prepare('SELECT id FROM inventory_logs WHERE log_date = ? AND item_id = ?');
            $stmt->execute([$date, $item_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // 更新
                $sql = 'UPDATE inventory_logs SET consumption = ?';
                $params = [$consumption];
                
                if ($remaining !== null) {
                    $sql .= ', quantity = ?'; // remaining_stock -> quantity
                    $params[] = $remaining;
                }
                if ($notes !== '') {
                    $sql .= ', notes = ?';
                    $params[] = $notes;
                }
                
                $sql .= ' WHERE id = ?';
                $params[] = $existing['id'];
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $update_count++;
            } else {
                // 新規挿入
                // remaining_stock -> quantity
                $stmt = $pdo->prepare('
                    INSERT INTO inventory_logs (log_date, item_id, consumption, quantity, notes)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([$date, $item_id, $consumption, $remaining, $notes]);
                $success_count++;
            }
        }
        
        $pdo->commit();
        
        $import_message = "<div class='alert alert-success'>✅ CSVインポート完了: 新規{$success_count}件、更新{$update_count}件</div>";
        if ($error_count > 0) {
            $import_message .= "<div class='alert alert-danger'>❌ {$error_count}件のエラーがありました。<br>" . implode('<br>', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '...他' : '') . "</div>";
        }
        
        unset($_SESSION['actual_csv_import_data']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $import_message = "<div class='alert alert-danger'>❌ インポートエラー: " . h($e->getMessage()) . "</div>";
    }
}
// 2. CSVファイルアップロード処理
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['import'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $csv_data = [];
        
        // 文字コード検出と変換
        $content = file_get_contents($file['tmp_name']);
        $encoding = mb_detect_encoding($content, 'UTF-8, SJIS-win, SJIS, EUC-JP, ASCII', true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        $tmp_file = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tmp_file, $content);
        $handle = fopen($tmp_file, 'r');
        
        if ($handle !== false) {
            // ヘッダー行を取得
            $headers = fgetcsv($handle);
            
            if ($headers !== false) {
                // データ行を取得
                while (($row = fgetcsv($handle)) !== false) {
                    if (array_filter($row)) {
                        $csv_data[] = $row;
                    }
                }
                fclose($handle);
                unlink($tmp_file);
                
                // カラムマッピング画面を表示するためのフラグ
                $_SESSION['actual_csv_import_data'] = [
                    'headers' => $headers,
                    'data' => $csv_data
                ];
                
                // マッピング画面へリダイレクト
                header('Location: actual_import.php');
                exit;
                
            } else {
                fclose($handle);
                $import_message = '<div class="alert alert-danger">❌ CSVファイルが空か、フォーマットが不正です。</div>';
                $show_import_form = true;
            }
        } else {
            $import_message = '<div class="alert alert-danger">❌ ファイルを開けませんでした。</div>';
            $show_import_form = true;
        }
    } else {
        $import_message = '<div class="alert alert-danger">❌ ファイルのアップロードに失敗しました。</div>';
        $show_import_form = true;
    }
}

// キャンセル処理
if (isset($_GET['cancel'])) {
    unset($_SESSION['actual_csv_import_data']);
    header('Location: actual_import.php');
    exit;
}

include 'includes/header.php';
?>

<!-- CSVカラムマッピング画面 -->
<?php if (isset($_SESSION['actual_csv_import_data'])): ?>
<?php
    $csv_import = $_SESSION['actual_csv_import_data'];
    $headers = $csv_import['headers'];
    $data = $csv_import['data'];
?>
<div class="card">
    <div class="card-header">
        <h2>📊 CSVカラムマッピング - 実績データ</h2>
    </div>

    <?php if (!empty($import_message)) echo $import_message; ?>
    
    <div class="alert alert-warning">
        <strong>💡 カラムの対応を設定してください:</strong><br>
        CSVファイルの各カラムが、どの実績情報に対応するかを選択してください。<br>
        <span style="color: red;">*</span> は必須項目です。
    </div>
    
    <form method="POST" action="">
        <input type="hidden" name="import" value="1">
        <input type="hidden" name="confirm_mapping" value="1">
        
        <table class="table">
            <thead>
                <tr>
                    <th>実績情報</th>
                    <th>CSVカラム</th>
                    <th>プレビュー（1行目）</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>記録日 <span style="color: red;">*</span></strong></td>
                    <td>
                        <select name="column_mapping[log_date]" class="form-control" required>
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(日付|date|記録)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_log_date" style="color: #999;">選択してください</span></td>
                </tr>
                <tr>
                    <td><strong>商品名 <span style="color: red;">*</span></strong></td>
                    <td>
                        <select name="column_mapping[item_name]" class="form-control" required>
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(商品|name|item)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_item_name" style="color: #999;">選択してください</span></td>
                </tr>
                <tr>
                    <td><strong>消費量 <span style="color: red;">*</span></strong></td>
                    <td>
                        <select name="column_mapping[consumption]" class="form-control" required>
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(消費|consumption|使用)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_consumption" style="color: #999;">選択してください</span></td>
                </tr>
                <tr>
                    <td><strong>残在庫</strong></td>
                    <td>
                        <select name="column_mapping[remaining_stock]" class="form-control">
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(在庫|stock|remaining)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_remaining_stock" style="color: #999;">-</span></td>
                </tr>
                <tr>
                    <td><strong>備考</strong></td>
                    <td>
                        <select name="column_mapping[notes]" class="form-control">
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(備考|note|メモ)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_notes" style="color: #999;">-</span></td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 2rem; display:flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">✅ インポート実行</button>
            <a href="actual_import.php?cancel=1" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
    
    <script>
        const csvData = <?php echo json_encode($data[0] ?? []); ?>;
        
        document.querySelectorAll('select[name^="column_mapping"]').forEach(select => {
            select.addEventListener('change', function() {
                const field = this.name.match(/\[(.*?)\]/)[1];
                const idx = this.value;
                const preview = document.getElementById('preview_' + field);
                
                if (idx !== '') {
                    preview.textContent = csvData[idx] || '(値なし)';
                    preview.style.color = '#000';
                } else {
                    preview.textContent = ['log_date', 'item_name', 'consumption'].includes(field) ? '選択してください' : '-';
                    preview.style.color = '#999';
                }
            });
            // 初期表示
            select.dispatchEvent(new Event('change'));
        });
    </script>
</div>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h2>📥 実績データインポート</h2>
    </div>
    
    <?php echo $import_message; ?>
    
    <div class="alert alert-info">
        <strong>💡 この機能について:</strong><br>
        過去の実績消費データをCSVファイルから一括登録できます。<br>
        機械学習・AI分析の精度向上に役立ちます。
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data" style="margin-top: 2rem;">
        <input type="hidden" name="import" value="1">
        <div class="form-group">
            <label for="csv_file"><strong>CSVファイルを選択</strong></label>
            <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">📤 アップロード</button>
    </form>
    
    <div class="alert alert-warning" style="margin-top: 2rem;">
        <strong>📋 CSVフォーマットについて:</strong><br>
        ・1行目はヘッダー行（カラム名）にしてください<br>
        ・カラム名は自由です（アップロード後にマッピングできます）<br>
        ・<span style="color: red;">必須項目</span>: 記録日、商品名、消費量<br>
        ・<span style="color: green;">オプション項目</span>: 残在庫、備考<br>
        ・重複データは自動的に上書き更新されます<br>
        <br>
        <strong>CSVサンプル:</strong><br>
        <code>日付,商品名,消費量,残在庫,備考</code><br>
        <code>2026-01-15,ブレンドコーヒー豆,12,5,イベント日</code><br>
        <code>2026-01-16,ホイップクリーム,14,8,通常営業</code>
    </div>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>

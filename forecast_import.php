<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = '発注データインポート';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_mapping']) && isset($_SESSION['forecast_csv_import_data'])) {
    $mapping = $_POST['column_mapping'];
    $duplicate_action = $_POST['duplicate_action'] ?? 'skip';
    $csv_data = $_SESSION['forecast_csv_import_data']['data'];
    
    $success_count = 0;
    $error_count = 0;
    $duplicate_count = 0;
    $errors = [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($csv_data as $index => $row) {
            $line_num = $index + 2; // ヘッダー + 1行目から
            
            // 必須項目の取得
            $date = isset($mapping['target_date']) && $mapping['target_date'] !== '' && isset($row[$mapping['target_date']]) ? trim($row[$mapping['target_date']]) : '';
            $item_name = isset($mapping['item_name']) && $mapping['item_name'] !== '' && isset($row[$mapping['item_name']]) ? trim($row[$mapping['item_name']]) : '';
            $star_level = isset($mapping['star_level']) && $mapping['star_level'] !== '' && isset($row[$mapping['star_level']]) ? (int)$row[$mapping['star_level']] : 0;
            $predicted = isset($mapping['predicted_consumption']) && $mapping['predicted_consumption'] !== '' && isset($row[$mapping['predicted_consumption']]) ? (float)$row[$mapping['predicted_consumption']] : 0;
            
            // オプション項目
            $actual = isset($mapping['actual_consumption']) && $mapping['actual_consumption'] !== '' && isset($row[$mapping['actual_consumption']]) && $row[$mapping['actual_consumption']] !== '' ? (float)$row[$mapping['actual_consumption']] : null;
            $remaining = isset($mapping['remaining_stock']) && $mapping['remaining_stock'] !== '' && isset($row[$mapping['remaining_stock']]) && $row[$mapping['remaining_stock']] !== '' ? (float)$row[$mapping['remaining_stock']] : null;
            $ordered = isset($mapping['ordered_quantity']) && $mapping['ordered_quantity'] !== '' && isset($row[$mapping['ordered_quantity']]) && $row[$mapping['ordered_quantity']] !== '' ? (float)$row[$mapping['ordered_quantity']] : null;
            
            // データ検証
            if (empty($date) || empty($item_name) || $star_level < 1 || $star_level > 5 || $predicted < 0) {
                // 必須項目が欠けている場合でも、商品名だけあればエラーとしてカウント
                if (!empty($item_name)) {
                     $errors[] = "行{$line_num}: 必須項目不足（日付={$date}, 商品={$item_name}, 星={$star_level}, 予測={$predicted}）";
                     $error_count++;
                }
                continue;
            }
            
            // 日付形式を変換（YYYY/MM/DD → YYYY-MM-DD）
            $date = str_replace('/', '-', $date);
            // 日付の正規化 (YYYY-M-D -> YYYY-MM-DD)
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
            
            // 重複チェック
            $stmt = $pdo->prepare('SELECT id FROM forecasts WHERE target_date = ? AND item_id = ?');
            $stmt->execute([$date, $item_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $duplicate_count++;
                if ($duplicate_action === 'skip') {
                    continue;
                } elseif ($duplicate_action === 'update') {
                    // 更新
                    $sql = 'UPDATE forecasts SET star_level = ?, predicted_consumption = ?';
                    $params = [$star_level, $predicted];
                    
                    if ($actual !== null) {
                        $sql .= ', actual_consumption = ?';
                        $params[] = $actual;
                    }
                    if ($remaining !== null) {
                        $sql .= ', remaining_stock = ?';
                        $params[] = $remaining;
                    }
                    if ($ordered !== null) {
                        $sql .= ', ordered_quantity = ?';
                        $params[] = $ordered;
                    }
                    
                    // forecast_date も念のため target_date で更新（もしNULLなら）
                    $sql .= ', forecast_date = ?';
                    $params[] = $date;
                    
                    $sql .= ' WHERE id = ?';
                    $params[] = $existing['id'];
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success_count++;
                    continue;
                }
            }
            
            // 新規挿入
            // forecast_date は target_date と同じにする（過去データインポートの想定）
            $stmt = $pdo->prepare('
                INSERT INTO forecasts (target_date, forecast_date, item_id, star_level, predicted_consumption, actual_consumption, remaining_stock, ordered_quantity)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$date, $date, $item_id, $star_level, $predicted, $actual, $remaining, $ordered]);
            $success_count++;
        }
        
        $pdo->commit();
        
        $import_message = "<div class='alert alert-success'>✅ CSVインポート完了: {$success_count}件のデータを追加・更新しました。</div>";
        if ($duplicate_count > 0 && $duplicate_action === 'skip') {
            $import_message .= "<div class='alert alert-warning'>⚠️ {$duplicate_count}件の重複データをスキップしました。</div>";
        }
        if ($error_count > 0) {
            $import_message .= "<div class='alert alert-danger'>❌ {$error_count}件のエラーがありました。<br>" . implode('<br>', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '...他' : '') . "</div>";
        }
        
        unset($_SESSION['forecast_csv_import_data']);
        
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
                $_SESSION['forecast_csv_import_data'] = [
                    'headers' => $headers,
                    'data' => $csv_data
                ];
                
                // マッピング画面へリダイレクト
                header('Location: forecast_import.php');
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
    unset($_SESSION['forecast_csv_import_data']);
    header('Location: forecast_import.php');
    exit;
}

include 'includes/header.php';
?>

<!-- CSVカラムマッピング画面 -->
<?php if (isset($_SESSION['forecast_csv_import_data'])): ?>
<?php
    $csv_import = $_SESSION['forecast_csv_import_data'];
    $headers = $csv_import['headers'];
    $data = $csv_import['data'];
?>
<div class="card">
    <div class="card-header">
        <h2>📊 CSVカラムマッピング - 発注データ</h2>
    </div>

    <?php if (!empty($import_message)) echo $import_message; ?>
    
    <div class="alert alert-warning">
        <strong>💡 カラムの対応を設定してください:</strong><br>
        CSVファイルの各カラムが、どの発注情報に対応するかを選択してください。<br>
        <span style="color: red;">*</span> は必須項目です。
    </div>
    
    <form method="POST" action="">
        <input type="hidden" name="import" value="1">
        <input type="hidden" name="confirm_mapping" value="1">
        
        <table class="table">
            <thead>
                <tr>
                    <th>発注情報</th>
                    <th>CSVカラム</th>
                    <th>プレビュー（1行目）</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>対象日 <span style="color: red;">*</span></strong></td>
                    <td>
                        <select name="column_mapping[target_date]" class="form-control" required>
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(日付|date|対象)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_target_date" style="color: #999;">選択してください</span></td>
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
                    <td><strong>星ランク <span style="color: red;">*</span></strong></td>
                    <td>
                        <select name="column_mapping[star_level]" class="form-control" required>
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(星|star|ランク|level)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_star_level" style="color: #999;">選択してください</span></td>
                </tr>
                <tr>
                    <td><strong>予測消費量 <span style="color: red;">*</span></strong></td>
                    <td>
                        <select name="column_mapping[predicted_consumption]" class="form-control" required>
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(予測|predicted|consumption)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_predicted_consumption" style="color: #999;">選択してください</span></td>
                </tr>
                <tr>
                    <td><strong>実際の消費量</strong></td>
                    <td>
                        <select name="column_mapping[actual_consumption]" class="form-control">
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(実際|actual|実績)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_actual_consumption" style="color: #999;">-</span></td>
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
                    <td><strong>発注量</strong></td>
                    <td>
                        <select name="column_mapping[ordered_quantity]" class="form-control">
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (preg_match('/(発注|order|注文)/iu', $header)) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_ordered_quantity" style="color: #999;">-</span></td>
                </tr>
            </tbody>
        </table>
        
        <div class="form-group" style="margin-top: 2rem;">
            <label><strong>重複データの処理方法:</strong></label>
            <div>
                <label style="margin-right: 2rem;">
                    <input type="radio" name="duplicate_action" value="skip" checked> スキップ（既存データを保持）
                </label>
                <label>
                    <input type="radio" name="duplicate_action" value="update"> 上書き更新
                </label>
            </div>
        </div>
        
        <div style="margin-top: 2rem; display:flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">✅ インポート実行</button>
            <a href="forecast_import.php?cancel=1" class="btn btn-secondary">キャンセル</a>
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
                    preview.textContent = ['target_date', 'item_name', 'star_level', 'predicted_consumption'].includes(field) ? '選択してください' : '-';
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
        <h2>📥 発注データインポート</h2>
    </div>
    
    <?php echo $import_message; ?>
    
    <div class="alert alert-info">
        <strong>💡 この機能について:</strong><br>
        過去の発注データをCSVファイルから一括登録できます。<br>
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
        ・<span style="color: red;">必須項目</span>: 対象日、商品名、星ランク、予測消費量<br>
        ・<span style="color: green;">オプション項目</span>: 実際の消費量、残在庫、発注量<br>
        <br>
        <strong>CSVサンプル:</strong><br>
        <code>日付,商品名,星ランク,予測消費量,実際の消費量,残在庫</code><br>
        <code>2026-01-15,ブレンドコーヒー豆,3,10,12,5</code><br>
        <code>2026-01-16,ホイップクリーム,4,15,14,8</code>
    </div>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'auth.php';
require_once 'config.php';

// 管理者権限チェック
requireAdmin();

$page_title = '商品管理';
$pdo = getDB();

// CSVインポート処理
$import_message = '';
$show_import_form = false;

// 1. マッピング確定後のインポート実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_mapping']) && isset($_SESSION['csv_import_data'])) {
    $mapping = $_POST['column_mapping'];
    $csv_data = $_SESSION['csv_import_data']['data'];
    $success_count = 0;
    $error_count = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($csv_data as $row) {
            $name = isset($mapping['name']) && $mapping['name'] !== '' && isset($row[$mapping['name']]) ? trim($row[$mapping['name']]) : '';
            $unit = isset($mapping['unit']) && $mapping['unit'] !== '' && isset($row[$mapping['unit']]) ? trim($row[$mapping['unit']]) : '個';
            $safety_stock = isset($mapping['safety_stock']) && $mapping['safety_stock'] !== '' && isset($row[$mapping['safety_stock']]) ? (int)$row[$mapping['safety_stock']] : 10;
            
            if ($name !== '') {
                // 重複チェック
                $stmt = $pdo->prepare('SELECT id FROM items WHERE name = ?');
                $stmt->execute([$name]);
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare('INSERT INTO items (name, unit, safety_stock) VALUES (?, ?, ?)');
                    $stmt->execute([$name, $unit, $safety_stock]);
                    $success_count++;
                } else {
                    // 既に存在する場合はスキップ
                }
            } else {
                $error_count++;
            }
        }
        
        $pdo->commit();
        $import_message = "<div class='alert alert-success'>✅ CSVインポート完了: {$success_count}件の商品を追加しました。</div>";
        if ($error_count > 0) {
            $import_message .= "<div class='alert alert-warning'>⚠️ {$error_count}件のエラーがありました（商品名が空の行など）。</div>";
        }
        
        // セッションデータをクリア
        unset($_SESSION['csv_import_data']);
        
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
        
        // 一時ファイルに保存して読み込み
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
                
                // カラムマッピング画面を表示するためのフラグ設定
                $_SESSION['csv_import_data'] = [
                    'headers' => $headers,
                    'data' => $csv_data
                ];
                
                // マッピング画面へリダイレクト（念のため）
                header('Location: items.php');
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

// 削除処理
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: items.php?msg=deleted');
    exit;
}

// マッピングキャンセル処理
if (isset($_GET['cancel'])) {
    unset($_SESSION['csv_import_data']);
    header('Location: items.php');
    exit;
}

// 商品一覧を取得
$items = $pdo->query('SELECT * FROM items ORDER BY id ASC')->fetchAll();

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $message = '<div class="alert alert-success">商品を保存しました。</div>';
    } elseif ($_GET['msg'] === 'deleted') {
        $message = '<div class="alert alert-success">商品を削除しました。</div>';
    }
}

include 'includes/header.php';
?>

<!-- CSVカラムマッピング画面 -->
<?php if (isset($_SESSION['csv_import_data'])): ?>
<?php
    $csv_import = $_SESSION['csv_import_data'];
    $headers = $csv_import['headers'];
    $data = $csv_import['data'];
?>
<div class="card">
    <div class="card-header">
        <h2>📊 CSVカラムマッピング</h2>
    </div>
    
    <div class="alert alert-warning">
        <strong>💡 カラムの対応を設定してください:</strong><br>
        CSVファイルの各カラムが、どの商品情報に対応するかを選択してください。<br>
        対応するカラムがない場合は「(割り当てなし)」を選択してください。
    </div>
    
    <form method="POST" action="">
        <input type="hidden" name="import" value="1">
        <input type="hidden" name="confirm_mapping" value="1">
        
        <table class="table">
            <thead>
                <tr>
                    <th>商品情報</th>
                    <th>CSVカラム</th>
                    <th>プレビュー（1行目）</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>商品名 <span style="color: red;">*</span></strong></td>
                    <td>
                        <select name="column_mapping[name]" class="form-control" required>
                            <option value="">(割り当てなし)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (strpos(strtolower($header), '名') !== false || strpos(strtolower($header), 'name') !== false) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_name" style="color: #999;">選択してください</span></td>
                </tr>
                <tr>
                    <td><strong>単位</strong></td>
                    <td>
                        <select name="column_mapping[unit]" class="form-control">
                            <option value="">(割り当てなし - デフォルト: 個)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (strpos(strtolower($header), '単位') !== false || strpos(strtolower($header), 'unit') !== false) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_unit" style="color: #999;">個</span></td>
                </tr>
                <tr>
                    <td><strong>安全在庫数</strong></td>
                    <td>
                        <select name="column_mapping[safety_stock]" class="form-control">
                            <option value="">(割り当てなし - デフォルト: 10)</option>
                            <?php foreach ($headers as $idx => $header): ?>
                                <option value="<?php echo $idx; ?>" <?php echo (strpos(strtolower($header), '在庫') !== false || strpos(strtolower($header), 'stock') !== false) ? 'selected' : ''; ?>>
                                    <?php echo h($header); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span id="preview_safety_stock" style="color: #999;">10</span></td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 2rem; display:flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary">✅ インポート実行</button>
            <a href="items.php?cancel=1" class="btn btn-secondary">キャンセル</a>
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
                    preview.textContent = field === 'name' ? '選択してください' : (field === 'unit' ? '個' : '10');
                    preview.style.color = '#999';
                }
            });
            // 初期表示
            select.dispatchEvent(new Event('change'));
        });
    </script>
</div>

<?php else: // 通常画面 ?>

<div class="card">
    <div class="card-header flex-between">
        <h2>📦 商品管理</h2>
        <div style="display: flex; gap: 0.5rem;">
            <a href="item_edit.php" class="btn btn-primary">➕ 新規商品追加</a>
            <button type="button" class="btn btn-secondary" onclick="toggleImportForm()">
                📄 CSV一括登録
            </button>
        </div>
    </div>
    
    <?php echo $import_message; ?>
    <?php echo $message; ?>
    
    <!-- CSV一括登録フォーム -->
    <div id="csv-import-form" style="display: <?php echo $show_import_form ? 'block' : 'none'; ?>; background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <h3>📄 CSV一括登録</h3>
        <p>CSVファイルから商品を一括登録できます。</p>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="import" value="1">
            <div class="form-group">
                <label for="csv_file">CSVファイルを選択</label>
                <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">📤 アップロード</button>
                <button type="button" class="btn btn-secondary" onclick="toggleImportForm()">キャンセル</button>
            </div>
        </form>
        <div class="alert alert-warning" style="margin-top: 1rem;">
            <strong>💡 CSVフォーマットについて:</strong><br>
            ・1行目はヘッダー行（カラム名）にしてください<br>
            ・文字コードはUTF-8またはShift-JISに対応しています<br>
            ・最低限「商品名」のカラムが必要です
        </div>
    </div>
    
    <script>
    function toggleImportForm() {
        const form = document.getElementById('csv-import-form');
        if (form.style.display === 'none') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
    </script>
    
    <?php if (count($items) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>商品名</th>
                <th>単位</th>
                <th>安全在庫数</th>
                <th>星ランク設定</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <?php
                // この商品の星ランク定義数を取得
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM star_definitions WHERE item_id = ?');
                $stmt->execute([$item['id']]);
                $star_count = $stmt->fetchColumn();
            ?>
            <tr>
                <td><?php echo $item['id']; ?></td>
                <td><strong><?php echo h($item['name']); ?></strong></td>
                <td><?php echo h($item['unit']); ?></td>
                <td><?php echo $item['safety_stock']; ?></td>
                <td>
                    <?php if ($star_count == 5): ?>
                        <span style="color: var(--success);">✅ 設定済み</span>
                    <?php else: ?>
                        <span style="color: var(--warning);">⚠️ 未設定 (<?php echo $star_count; ?>/5)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="item_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary btn-small">編集</a>
                    <a href="items.php?delete=<?php echo $item['id']; ?>" 
                       class="btn btn-danger btn-small delete-btn" 
                       data-item-name="<?php echo h($item['name']); ?>"
                       onclick="return confirm('「<?php echo h($item['name']); ?>」を削除してもよろしいですか？');">削除</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-warning">
        商品が登録されていません。「新規商品追加」ボタンから商品を追加してください。
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3>💡 商品管理のポイント</h3>
    </div>
    <ul>
        <li><strong>安全在庫数:</strong> 最低限キープしたい在庫量を設定します。</li>
        <li><strong>星ランク設定:</strong> 各商品について、星1〜5それぞれの1日あたり消費量を設定する必要があります。</li>
        <li><strong>発注計算:</strong> 星ランクが設定されていない商品は、発注計算で正確な結果が得られません。</li>
    </ul>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>

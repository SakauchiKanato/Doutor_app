<?php
require_once 'auth.php';
require_once 'config.php';

// このスクリプトはデータベースの更新（マイグレーション）を自動で行います。
// ブラウザでこのファイルにアクセスするだけで実行されます。

$pdo = getDB();

echo "<h2>🛠 データベース自動修復スクリプト</h2>";

try {
    $pdo->beginTransaction();

    // 1. forecasts テーブルの修復
    echo "<h3>1. forecasts テーブル</h3>";
    $count = $pdo->exec('
        DELETE FROM forecasts a USING forecasts b
        WHERE a.id < b.id AND a.item_id = b.item_id AND a.target_date = b.target_date
    ');
    echo "<p>重複データを {$count} 件削除しました。</p>";
    
    $exists = $pdo->query("SELECT 1 FROM pg_constraint WHERE conname = 'unique_forecast_target'")->fetch();
    if (!$exists) {
        $pdo->exec('ALTER TABLE forecasts ADD CONSTRAINT unique_forecast_target UNIQUE (item_id, target_date)');
        echo "<p>✅ ユニーク制約 (unique_forecast_target) を追加しました。</p>";
    } else {
        echo "<p>ℹ️ 制約は既に存在します。</p>";
    }

    // 2. daily_stars テーブルの修復
    echo "<h3>2. daily_stars テーブル</h3>";
    $count = $pdo->exec('
        DELETE FROM daily_stars a USING daily_stars b
        WHERE a.id < b.id AND a.target_date = b.target_date
    ');
    echo "<p>重複データを {$count} 件削除しました。</p>";

    $exists = $pdo->query("SELECT 1 FROM pg_constraint WHERE conname = 'unique_daily_stars_date'")->fetch();
    if (!$exists) {
        // target_dateカラム自体が最初からUNIQUE指定されている可能性もあるため、エラーを無視できる構成に or 個別にチェック
        try {
            $pdo->exec('ALTER TABLE daily_stars ADD CONSTRAINT unique_daily_stars_date UNIQUE (target_date)');
            echo "<p>✅ ユニーク制約 (unique_daily_stars_date) を追加しました。</p>";
        } catch (Exception $e) {
            echo "<p>ℹ️ 制約追加をスキップしました（既にカラム制約がある可能性があります）。</p>";
        }
    } else {
        echo "<p>ℹ️ 制約は既に存在します。</p>";
    }

    // 3. orders テーブルの修復
    echo "<h3>3. orders テーブル</h3>";
    $count = $pdo->exec('
        DELETE FROM orders a USING orders b
        WHERE a.id < b.id AND a.item_id = b.item_id AND a.delivery_date = b.delivery_date
    ');
    echo "<p>重複データを {$count} 件削除しました。</p>";

    $exists = $pdo->query("SELECT 1 FROM pg_constraint WHERE conname = 'unique_orders_delivery'")->fetch();
    if (!$exists) {
        $pdo->exec('ALTER TABLE orders ADD CONSTRAINT unique_orders_delivery UNIQUE (item_id, delivery_date)');
        echo "<p>✅ ユニーク制約 (unique_orders_delivery) を追加しました。</p>";
    } else {
        echo "<p>ℹ️ 制約は既に存在します。</p>";
    }

    // 4. inventory_logs テーブルの修復
    echo "<h3>4. inventory_logs テーブル</h3>";
    $count = $pdo->exec('
        DELETE FROM inventory_logs a USING inventory_logs b
        WHERE a.id < b.id AND a.item_id = b.item_id AND a.log_date = b.log_date
    ');
    echo "<p>重複データを {$count} 件削除しました。</p>";

    $exists = $pdo->query("SELECT 1 FROM pg_constraint WHERE conname = 'unique_inventory_log_date'")->fetch();
    if (!$exists) {
        $pdo->exec('ALTER TABLE inventory_logs ADD CONSTRAINT unique_inventory_log_date UNIQUE (item_id, log_date)');
        echo "<p>✅ ユニーク制約 (unique_inventory_log_date) を追加しました。</p>";
    } else {
        echo "<p>ℹ️ 制約は既に存在します。</p>";
    }

    $pdo->commit();
    echo "<hr><h3 style='color: green;'>✨ すべてのデータベース修復が完了しました！</h3>";
    echo "<p><a href='order_calculator.php'>発注計算画面に戻って再度保存をお試しください</a></p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h3 style='color: red;'>❌ エラーが発生しました</h3>";
    echo "<pre>" . h($e->getMessage()) . "</pre>";
    echo "<p>※もし「ALTER TABLE...」でエラーが出る場合は、既に手動で追加されている可能性があります。</p>";
}

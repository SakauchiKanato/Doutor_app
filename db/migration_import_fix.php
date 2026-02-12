<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

// CLIまたはブラウザからの実行を許可
if (php_sapi_name() !== 'cli' && !isset($_SESSION['user_id'])) {
    die('Access denied');
}

$pdo = getDB();

echo "Starting database migration for CSV import fix...\n";

try {
    $pdo->beginTransaction();

    // 1. forecasts テーブルに ordered_quantity カラムを追加
    echo "Checking forecasts table...\n";
    $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'forecasts' AND column_name = 'ordered_quantity'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE forecasts ADD COLUMN ordered_quantity INTEGER');
        echo " - Added ordered_quantity column to forecasts table.\n";
    } else {
        echo " - ordered_quantity column already exists.\n";
    }

    // 2. inventory_logs テーブルに consumption, notes カラムを追加
    echo "Checking inventory_logs table...\n";
    
    // consumption
    $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'inventory_logs' AND column_name = 'consumption'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_logs ADD COLUMN consumption INTEGER');
        echo " - Added consumption column to inventory_logs table.\n";
    } else {
        echo " - consumption column already exists.\n";
    }

    // notes
    $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'inventory_logs' AND column_name = 'notes'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_logs ADD COLUMN notes TEXT');
        echo " - Added notes column to inventory_logs table.\n";
    } else {
        echo " - notes column already exists.\n";
    }

    $pdo->commit();
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

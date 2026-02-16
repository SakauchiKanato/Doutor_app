<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

echo "Migration: Setting up database for Data Management...\n";

try {
    // 1. forecasts テーブルに ordered_quantity カラムを追加
    echo "Checking forecasts table...\n";
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'forecasts' AND column_name = 'ordered_quantity'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE forecasts ADD COLUMN ordered_quantity INTEGER");
        echo " - Added: ordered_quantity column to forecasts.\n";
    } else {
        echo " - Skipped: ordered_quantity already exists.\n";
    }

    // 2. inventory_logs テーブルに consumption カラムを追加
    echo "Checking inventory_logs table...\n";
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'inventory_logs' AND column_name = 'consumption'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN consumption INTEGER");
        echo " - Added: consumption column to inventory_logs.\n";
    } else {
        echo " - Skipped: consumption already exists.\n";
    }
    
    // 3. inventory_logs テーブルに notes カラムを追加
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'inventory_logs' AND column_name = 'notes'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN notes TEXT");
        echo " - Added: notes column to inventory_logs.\n";
    } else {
        echo " - Skipped: notes already exists.\n";
    }
    
    // 4. inventory_logs テーブルに remaining_stock カラムを追加（念のため、もしなければ）
    // init.sqlでは quantity があるが、コードでは remaining_stock も使われている箇所があるかもしれないので確認。
    // actual_import.php では quantity = remaining_stock として扱っているが、
    //念の為 remaining_stock カラムも追加しておく（quantity と混同しやすいので）
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'inventory_logs' AND column_name = 'remaining_stock'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN remaining_stock INTEGER");
        echo " - Added: remaining_stock column to inventory_logs.\n";
    } else {
        echo " - Skipped: remaining_stock already exists.\n";
    }

    echo "Success: Database setup for Data Management completed.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

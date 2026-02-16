<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

echo "Migration: Creating ai_chat_logs table...\n";

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS ai_chat_logs (
        id SERIAL PRIMARY KEY,
        prompt TEXT NOT NULL,
        response TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    $pdo->exec($sql);
    echo "Success: ai_chat_logs table created (or already exists).\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

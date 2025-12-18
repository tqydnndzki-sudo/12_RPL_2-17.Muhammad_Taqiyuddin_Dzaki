<?php
require_once 'config/database.php';

try {
    // Add kodebarang column to detailrequest table
    $pdo->exec("ALTER TABLE detailrequest ADD COLUMN kodebarang VARCHAR(50) AFTER kodeproject");
    echo "Added kodebarang column to detailrequest table\n";
    
    // Add satuan column to detailrequest table
    $pdo->exec("ALTER TABLE detailrequest ADD COLUMN satuan VARCHAR(50) AFTER kodebarang");
    echo "Added satuan column to detailrequest table\n";
    
    echo "Schema update completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
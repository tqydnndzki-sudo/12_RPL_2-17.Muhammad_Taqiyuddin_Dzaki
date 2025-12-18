<?php
require_once 'config/database.php';

try {
    // Remove satuan column from detailrequest table
    $pdo->exec("ALTER TABLE detailrequest DROP COLUMN satuan");
    echo "Removed satuan column from detailrequest table\n";
    
    // Remove kodebarang column from detailrequest table
    $pdo->exec("ALTER TABLE detailrequest DROP COLUMN kodebarang");
    echo "Removed kodebarang column from detailrequest table\n";
    
    echo "Schema fix completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
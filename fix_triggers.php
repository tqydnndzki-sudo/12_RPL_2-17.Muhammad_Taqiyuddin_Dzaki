<?php
require_once 'config/database.php';

try {
    // Drop problematic trigger
    $pdo->exec("DROP TRIGGER IF EXISTS trg_detailrequest_after_insert");
    echo "Trigger 'trg_detailrequest_after_insert' dropped successfully.\n";
    
    // Also drop duplicate triggers on purchaserequest if they exist
    $pdo->exec("DROP TRIGGER IF EXISTS trg_sync_detail_status");
    echo "Trigger 'trg_sync_detail_status' dropped successfully.\n";
    
    echo "All problematic triggers have been removed.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
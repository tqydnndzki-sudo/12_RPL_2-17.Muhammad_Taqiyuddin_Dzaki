<?php
require_once 'config/database.php';

try {
    // Get detailed trigger information
    $stmt = $pdo->query("SHOW CREATE TRIGGER trg_detailrequest_after_insert");
    $triggerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($triggerInfo) {
        echo "Trigger Details:\n";
        print_r($triggerInfo);
    } else {
        echo "Trigger not found.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
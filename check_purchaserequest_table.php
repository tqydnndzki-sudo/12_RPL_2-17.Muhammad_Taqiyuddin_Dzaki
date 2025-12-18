<?php
require_once 'config/database.php';

try {
    // Check purchaserequest table structure
    $stmt = $pdo->query("DESCRIBE purchaserequest");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Purchaserequest table structure:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
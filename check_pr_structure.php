<?php
require_once 'config/database.php';

try {
    // Check purchaserequest table structure
    echo "=== purchaserequest table structure ===\n";
    $stmt = $pdo->query('DESCRIBE purchaserequest');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n=== users table structure ===\n";
    $stmt = $pdo->query('DESCRIBE users');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
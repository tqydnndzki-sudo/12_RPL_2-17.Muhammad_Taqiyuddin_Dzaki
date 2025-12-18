<?php
require_once 'config/database.php';

try {
    // Check master_status_pr table structure
    $stmt = $pdo->query('DESCRIBE master_status_pr');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "master_status_pr structure:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check actual data in master_status_pr
    echo "\nmaster_status_pr data:\n";
    $stmt = $pdo->query('SELECT * FROM master_status_pr');
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statuses as $status) {
        print_r($status);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
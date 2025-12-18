<?php
require_once 'config/database.php';

try {
    // Check master_status_pr table structure
    $stmt = $pdo->query("DESCRIBE master_status_pr");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Master_status_pr table structure:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")";
        if ($column['Null'] === 'NO') {
            echo " NOT NULL";
        }
        if ($column['Key'] === 'PRI') {
            echo " PRIMARY KEY";
        }
        echo "\n";
    }
    
    echo "\nData in master_status_pr table:\n";
    $stmt = $pdo->query("SELECT * FROM master_status_pr");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statuses as $status) {
        echo "  - idstatus: " . $status['idstatus'] . ", nama_status: " . $status['nama_status'] . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
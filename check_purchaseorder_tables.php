<?php
require_once 'config/database.php';

try {
    // Check purchaseorder table structure
    $stmt = $pdo->query("DESCRIBE purchaseorder");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Purchaseorder table structure:\n";
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
    
    echo "\n";
    
    // Check detailorder table structure
    $stmt = $pdo->query("DESCRIBE detailorder");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Detailorder table structure:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")";
        if ($column['Null'] === 'NO') {
            echo " NOT NULL";
        }
        if ($column['Key'] === 'PRI') {
            echo " PRIMARY KEY";
        }
        if ($column['Extra'] === 'auto_increment') {
            echo " AUTO_INCREMENT";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
<?php
require_once 'config/database.php';

try {
    // Check logstatusorder table structure
    $stmt = $pdo->query("DESCRIBE logstatusorder");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Logstatusorder table structure:\n";
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
    
    // Check sample data
    echo "\nSample data from logstatusorder:\n";
    $stmt = $pdo->query("SELECT * FROM logstatusorder LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($data as $row) {
        echo "  - ";
        foreach ($row as $key => $value) {
            echo "$key: $value, ";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
<?php
require_once 'config/database.php';

try {
    // Check detailrequest table structure
    $stmt = $pdo->query("DESCRIBE detailrequest");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Detailrequest table structure:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")";
        if ($column['Null'] === 'NO') {
            echo " NOT NULL";
        }
        if ($column['Default'] !== null) {
            echo " DEFAULT '" . $column['Default'] . "'";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
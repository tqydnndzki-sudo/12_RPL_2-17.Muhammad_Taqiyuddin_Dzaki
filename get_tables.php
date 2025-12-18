<?php
require_once 'config/database.php';

try {
    // Get list of tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables in database:\n";
    foreach ($tables as $table) {
        echo "- " . $table . "\n";
    }
    
    // Get structure for important tables
    $important_tables = ['inventory', 'barangmasuk', 'barangkeluar', 'detailmasuk', 'detailkeluar', 'm_barang', 'kategoribarang', 'purchaseorder'];
    
    foreach ($important_tables as $table) {
        if (in_array($table, $tables)) {
            echo "\nStructure for table '$table':\n";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $column) {
                echo "  " . $column['Field'] . " " . $column['Type'];
                if ($column['Null'] === 'NO') echo " NOT NULL";
                if ($column['Key'] === 'PRI') echo " PRIMARY KEY";
                if ($column['Extra']) echo " " . $column['Extra'];
                echo "\n";
            }
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
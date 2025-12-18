<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database:\n";
    foreach ($tables as $table) {
        echo "- " . $table . "\n";
    }
    
    // Check if logstatusreq and logstatusorder tables exist
    if (in_array('logstatusreq', $tables)) {
        echo "\nlogstatusreq table exists\n";
        // Show structure
        $stmt = $pdo->query('DESCRIBE logstatusreq');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "logstatusreq structure:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
    if (in_array('logstatusorder', $tables)) {
        echo "\nlogstatusorder table exists\n";
        // Show structure
        $stmt = $pdo->query('DESCRIBE logstatusorder');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "logstatusorder structure:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
    if (in_array('detailrequest', $tables)) {
        echo "\ndetailrequest table exists\n";
        // Show structure
        $stmt = $pdo->query('DESCRIBE detailrequest');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "detailrequest structure:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
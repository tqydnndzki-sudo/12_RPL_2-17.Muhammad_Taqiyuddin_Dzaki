<?php
require_once 'config/database.php';

try {
    // Check logstatusbarang table structure
    $stmt = $pdo->query("DESCRIBE logstatusbarang");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "logstatusbarang table structure:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check foreign key constraints
    $stmt = $pdo->query("SELECT 
        CONSTRAINT_NAME,
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'simba' 
        AND TABLE_NAME = 'logstatusbarang'
        AND REFERENCED_TABLE_NAME IS NOT NULL");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nForeign key constraints for logstatusbarang:\n";
    foreach ($constraints as $constraint) {
        echo "  - " . $constraint['CONSTRAINT_NAME'] . ": " . 
             $constraint['TABLE_NAME'] . "." . $constraint['COLUMN_NAME'] . 
             " -> " . $constraint['REFERENCED_TABLE_NAME'] . "." . $constraint['REFERENCED_COLUMN_NAME'] . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
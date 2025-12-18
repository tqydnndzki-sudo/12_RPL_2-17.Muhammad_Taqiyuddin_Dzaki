<?php
require_once 'config/database.php';

echo "Checking related tables for date fields...\n";

$tables = ['m_barang', 'barangmasuk', 'barangkeluar', 'purchaserequest'];

foreach($tables as $table) {
    echo "\n{$table} table columns:\n";
    try {
        $stmt = $pdo->query("DESCRIBE {$table}");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($columns as $column) {
            // Check for date/datetime columns
            if (strpos($column['Type'], 'date') !== false || strpos($column['Type'], 'datetime') !== false || strpos($column['Type'], 'timestamp') !== false) {
                echo "- " . $column['Field'] . " (" . $column['Type'] . ") <<< DATE FIELD\n";
            } else {
                echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
            }
        }
    } catch (PDOException $e) {
        echo "Error checking {$table}: " . $e->getMessage() . "\n";
    }
}
?>
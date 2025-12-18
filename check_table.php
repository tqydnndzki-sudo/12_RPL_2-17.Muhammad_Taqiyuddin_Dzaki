<?php
require 'config/database.php';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("DESCRIBE purchaseorder");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Struktur tabel purchaseorder:\n";
    foreach($columns as $column) {
        echo $column['Field'] . ' (' . $column['Type'] . ') ' . 
             ($column['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . ' ' . 
             ($column['Key'] == 'PRI' ? 'PRIMARY KEY' : '') . ' ' . 
             ($column['Extra'] ? $column['Extra'] : '') . "\n";
    }
    
    echo "\nContoh data dari tabel purchaseorder:\n";
    $stmt = $pdo->prepare("SELECT * FROM purchaseorder LIMIT 3");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($orders as $order) {
        echo "ID: " . $order['idpurchaseorder'] . ", Request ID: " . $order['idrequest'] . ", Supplier: " . $order['supplier'] . ", Tanggal: " . $order['tgl_po'] . "\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
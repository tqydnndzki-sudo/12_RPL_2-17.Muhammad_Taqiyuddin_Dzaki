<?php
require_once 'config/database.php';

echo "Checking m_barang table structure:\n";
$stmt = $pdo->query('DESCRIBE m_barang');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);

echo "\nChecking kategoribarang table structure:\n";
$stmt = $pdo->query('DESCRIBE kategoribarang');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
?>
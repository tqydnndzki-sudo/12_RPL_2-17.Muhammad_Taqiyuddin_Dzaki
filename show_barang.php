<?php
require_once 'config/database.php';

try {
    // Get all barang data
    $stmt = $pdo->query("SELECT idbarang, kodebarang, nama_barang, harga, satuan, kodeproject FROM m_barang LIMIT 10");
    $barang = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available barang:\n";
    foreach($barang as $item) {
        echo "- ID: " . $item['idbarang'] . ", Kode: " . $item['kodebarang'] . ", Nama: " . $item['nama_barang'] . ", Harga: " . $item['harga'] . ", Satuan: " . $item['satuan'] . ", Project: " . $item['kodeproject'] . "\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
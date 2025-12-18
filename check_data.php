<?php
require_once 'config/database.php';

echo "Checking inventory data...\n";

try {
    // Check total records
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
    $count = $stmt->fetchColumn();
    echo "Total records in inventory: " . $count . "\n";
    
    // Check years available
    $stmt = $pdo->query("SELECT DISTINCT YEAR(tgl_masuk) as tahun FROM inventory WHERE tgl_masuk IS NOT NULL ORDER BY tahun DESC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available years: " . implode(', ', $years) . "\n";
    
    // Check sample data
    $stmt = $pdo->query("SELECT idinventory, nama_barang, stok_akhir, harga, tgl_masuk FROM inventory LIMIT 5");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample data:\n";
    foreach($samples as $sample) {
        echo "- " . $sample['nama_barang'] . " (Stok: " . $sample['stok_akhir'] . ", Harga: " . $sample['harga'] . ", Tanggal: " . $sample['tgl_masuk'] . ")\n";
    }
    
    // Check data for current year
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT MONTH(tgl_masuk) as bulan, SUM(stok_akhir * harga) as total_value FROM inventory WHERE YEAR(tgl_masuk) = ? GROUP BY MONTH(tgl_masuk) ORDER BY bulan");
    $stmt->execute([$currentYear]);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Monthly data for year {$currentYear}:\n";
    foreach($monthlyData as $data) {
        echo "- Month " . $data['bulan'] . ": " . $data['total_value'] . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
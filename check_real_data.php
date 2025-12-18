<?php
require_once 'config/database.php';

echo "Checking actual data in database...\n";

try {
    // Check if we have data in purchaserequest
    $stmt = $pdo->query("SELECT COUNT(*) FROM purchaserequest");
    $count = $stmt->fetchColumn();
    echo "Total records in purchaserequest: " . $count . "\n";
    
    // Check if we have data in detailrequest
    $stmt = $pdo->query("SELECT COUNT(*) FROM detailrequest");
    $count = $stmt->fetchColumn();
    echo "Total records in detailrequest: " . $count . "\n";
    
    // Check years in purchaserequest
    $stmt = $pdo->query("SELECT DISTINCT YEAR(tgl_req) as tahun FROM purchaserequest WHERE tgl_req IS NOT NULL ORDER BY tahun DESC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available years in purchaserequest: " . implode(', ', $years) . "\n";
    
    // Check sample data for current year
    $currentYear = date('Y');
    echo "\nChecking data for year {$currentYear}:\n";
    
    $stmt = $pdo->prepare("SELECT MONTH(pr.tgl_req) as bulan, SUM(dr.harga * dr.qty) as total_value FROM purchaserequest pr JOIN detailrequest dr ON pr.idrequest = dr.idrequest WHERE YEAR(pr.tgl_req) = ? GROUP BY MONTH(pr.tgl_req) ORDER BY bulan");
    $stmt->execute([$currentYear]);
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($monthlyData)) {
        echo "No monthly data found for year {$currentYear}\n";
    } else {
        echo "Monthly data for year {$currentYear}:\n";
        foreach($monthlyData as $data) {
            echo "- Month " . $data['bulan'] . ": " . number_format($data['total_value'], 2) . "\n";
        }
    }
    
    // Check inventory data
    echo "\nChecking inventory data:\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
    $count = $stmt->fetchColumn();
    echo "Total records in inventory: " . $count . "\n";
    
    // Check category data
    $stmt = $pdo->query("SELECT kb.nama_kategori, COUNT(i.idinventory) as total_items, SUM(i.stok_akhir * i.harga) as total_value FROM inventory i LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori GROUP BY i.idkategori, kb.nama_kategori ORDER BY total_value DESC");
    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categoryData)) {
        echo "No category data found\n";
    } else {
        echo "Category data:\n";
        foreach($categoryData as $data) {
            echo "- " . $data['nama_kategori'] . ": " . number_format($data['total_value'], 2) . " (" . $data['total_items'] . " items)\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
<?php
require_once 'config/database.php';

echo "Verifying chart data...\n";

try {
    // Get category data for column chart (total price by category)
    $stmtProject = $pdo->prepare("
        SELECT 
            kb.nama_kategori as category_name,
            SUM(i.stok_akhir * i.harga) as total_value
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        GROUP BY i.idkategori, kb.nama_kategori
        ORDER BY total_value DESC
    ");
    $stmtProject->execute();
    $allProjectData = $stmtProject->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Column chart data (category totals):\n";
    foreach($allProjectData as $item) {
        echo "- " . $item['category_name'] . ": " . number_format($item['total_value'], 2) . "\n";
    }
    
    // Get category data for doughnut chart (item count by category)
    $stmtCategory = $pdo->prepare("
        SELECT 
            kb.nama_kategori as category_name,
            COUNT(i.idinventory) as total_items,
            SUM(i.stok_akhir * i.harga) as total_value
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        GROUP BY i.idkategori, kb.nama_kategori
        ORDER BY total_items DESC
    ");
    $stmtCategory->execute();
    $categoryData = $stmtCategory->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nDoughnut chart data (item counts):\n";
    foreach($categoryData as $item) {
        echo "- " . $item['category_name'] . ": " . $item['total_items'] . " items\n";
    }
    
    // Output JSON data that would be sent to JavaScript
    echo "\nJSON data for column chart:\n";
    echo json_encode($allProjectData, JSON_PRETTY_PRINT);
    
    echo "\n\nJSON data for doughnut chart:\n";
    echo json_encode($categoryData, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
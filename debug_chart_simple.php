<?php
require_once 'config/database.php';

echo "<h2>Debugging Chart Data</h2>";

// Test the exact queries used in index.php
try {
    // Column chart data
    echo "<h3>Column Chart Data Query:</h3>";
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
    
    echo "<pre>";
    print_r($allProjectData);
    echo "</pre>";
    
    echo "<h3>JSON for JavaScript:</h3>";
    echo "<pre>const categoryChartData = " . json_encode($allProjectData) . ";</pre>";
    
    // Doughnut chart data
    echo "<h3>Doughnut Chart Data Query:</h3>";
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
    
    echo "<pre>";
    print_r($categoryData);
    echo "</pre>";
    
    echo "<h3>JSON for JavaScript:</h3>";
    echo "<pre>const categoryData = " . json_encode($categoryData) . ";</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
<?php
require_once 'config/database.php';

// Test all the queries used in index.php
echo "<h1>Debug Index.php Queries</h1>";

try {
    // Test 1: Category data for column chart
    echo "<h2>1. Column Chart Data</h2>";
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
    
    // Test 2: Category data for doughnut chart
    echo "<h2>2. Doughnut Chart Data</h2>";
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
    
    // Test 3: Available years
    echo "<h2>3. Available Years</h2>";
    $stmtYears = $pdo->query("
        SELECT DISTINCT YEAR(created_at) as tahun
        FROM inventory
        WHERE YEAR(created_at) IS NOT NULL
        ORDER BY tahun DESC
    ");
    $availableYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<pre>";
    print_r($availableYears);
    echo "</pre>";
    
    echo "<h2>4. JavaScript Test</h2>";
    echo "<p>If you see this, PHP is working correctly.</p>";
    echo "<p>Column chart data as JSON:</p>";
    echo "<pre>const categoryChartData = " . json_encode($allProjectData) . ";</pre>";
    echo "<p>Doughnut chart data as JSON:</p>";
    echo "<pre>const categoryData = " . json_encode($categoryData) . ";</pre>";
    
} catch (PDOException $e) {
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
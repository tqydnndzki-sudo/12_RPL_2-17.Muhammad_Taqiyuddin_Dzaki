<?php
require_once 'config/database.php';
session_start();

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

// Output the exact JSON data that would be sent to JavaScript
echo "<h2>Debug: JSON Data for Charts</h2>";

echo "<h3>Column Chart Data:</h3>";
echo "<pre>";
echo json_encode($allProjectData, JSON_PRETTY_PRINT);
echo "</pre>";

echo "<h3>Doughnut Chart Data:</h3>";
echo "<pre>";
echo json_encode($categoryData, JSON_PRETTY_PRINT);
echo "</pre>";

echo "<h3>JavaScript Variables:</h3>";
echo "<pre>";
echo "const categoryChartData = " . json_encode($allProjectData) . ";\n";
echo "const categoryData = " . json_encode($categoryData) . ";\n";
echo "</pre>";
?>
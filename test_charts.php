<?php
require_once 'config/database.php';

echo "Testing database connection and data retrieval for charts...\n";

try {
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection: OK\n";
    
    // Check if inventory table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
    $count = $stmt->fetchColumn();
    echo "📊 Inventory table records: " . $count . "\n";
    
    // Check if kategoribarang table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) FROM kategoribarang");
    $count = $stmt->fetchColumn();
    echo "📋 Category table records: " . $count . "\n";
    
    // Test the exact query used for column chart
    $stmt = $pdo->prepare("
        SELECT 
            kb.nama_kategori as category_name,
            SUM(i.stok_akhir * i.harga) as total_value
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        GROUP BY i.idkategori, kb.nama_kategori
        ORDER BY total_value DESC
    ");
    $stmt->execute();
    $columnData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "📈 Column chart data items: " . count($columnData) . "\n";
    
    // Test the exact query used for doughnut chart
    $stmt = $pdo->prepare("
        SELECT 
            kb.nama_kategori as category_name,
            COUNT(i.idinventory) as total_items,
            SUM(i.stok_akhir * i.harga) as total_value
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        GROUP BY i.idkategori, kb.nama_kategori
        ORDER BY total_items DESC
    ");
    $stmt->execute();
    $doughnutData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "🍩 Doughnut chart data items: " . count($doughnutData) . "\n";
    
    // Display sample data
    if (!empty($columnData)) {
        echo "\nSample column chart data:\n";
        foreach ($columnData as $item) {
            echo "  - " . $item['category_name'] . ": " . number_format($item['total_value'], 2) . "\n";
        }
    }
    
    if (!empty($doughnutData)) {
        echo "\nSample doughnut chart data:\n";
        foreach ($doughnutData as $item) {
            echo "  - " . $item['category_name'] . ": " . $item['total_items'] . " items\n";
        }
    }
    
    echo "\n✅ All tests passed. Data should be displayed in charts.\n";
    echo "If charts are still not visible, please check browser console for JavaScript errors.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and table structure.\n";
}
?>
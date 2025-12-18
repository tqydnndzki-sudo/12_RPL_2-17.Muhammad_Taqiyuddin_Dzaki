<?php
require_once 'config/database.php';

echo "Checking actual inventory data...\n";

try {
    // Check inventory data with category names
    $stmt = $pdo->query("
        SELECT 
            i.idinventory,
            i.kodebarang,
            i.nama_barang,
            i.stok_akhir,
            i.harga,
            i.total,
            kb.nama_kategori
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        ORDER BY i.nama_barang
    ");
    $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Inventory data:\n";
    foreach($inventoryData as $item) {
        echo "- " . $item['nama_barang'] . " (" . $item['kodebarang'] . ")\n";
        echo "  Kategori: " . $item['nama_kategori'] . "\n";
        echo "  Stok: " . $item['stok_akhir'] . "\n";
        echo "  Harga: " . number_format($item['harga'], 2) . "\n";
        echo "  Total: " . number_format($item['total'], 2) . "\n";
        echo "\n";
    }
    
    // Check category summary for column chart
    echo "Category summary for column chart (total value by category):\n";
    $stmt = $pdo->query("
        SELECT 
            kb.nama_kategori as category_name,
            SUM(i.stok_akhir * i.harga) as total_value
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        GROUP BY i.idkategori, kb.nama_kategori
        ORDER BY total_value DESC
    ");
    $categorySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($categorySummary as $category) {
        echo "- " . $category['category_name'] . ": " . number_format($category['total_value'], 2) . "\n";
    }
    
    // Check item count for doughnut chart
    echo "\nItem count by category for doughnut chart:\n";
    $stmt = $pdo->query("
        SELECT 
            kb.nama_kategori as category_name,
            COUNT(i.idinventory) as total_items
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        GROUP BY i.idkategori, kb.nama_kategori
        ORDER BY total_items DESC
    ");
    $itemCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($itemCounts as $count) {
        echo "- " . $count['category_name'] . ": " . $count['total_items'] . " items\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
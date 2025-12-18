<?php
require_once 'config/database.php';

echo "Comparing database data with chart data...\n";

try {
    // Get raw inventory data
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
    
    echo "=== RAW INVENTORY DATA ===\n";
    foreach($inventoryData as $item) {
        echo $item['nama_barang'] . " | " . $item['nama_kategori'] . " | Stok: " . $item['stok_akhir'] . " | Harga: " . number_format($item['harga'], 2) . " | Total: " . number_format($item['total'], 2) . "\n";
    }
    
    // Calculate expected column chart data manually
    echo "\n=== EXPECTED COLUMN CHART DATA (MANUAL CALCULATION) ===\n";
    $expectedColumnData = [];
    foreach($inventoryData as $item) {
        $category = $item['nama_kategori'];
        if (!isset($expectedColumnData[$category])) {
            $expectedColumnData[$category] = 0;
        }
        $expectedColumnData[$category] += $item['stok_akhir'] * $item['harga'];
    }
    
    // Sort by value descending
    arsort($expectedColumnData);
    foreach($expectedColumnData as $category => $totalValue) {
        echo $category . ": " . number_format($totalValue, 2) . "\n";
    }
    
    // Calculate expected doughnut chart data manually
    echo "\n=== EXPECTED DOUGHNUT CHART DATA (MANUAL CALCULATION) ===\n";
    $expectedDoughnutData = [];
    foreach($inventoryData as $item) {
        $category = $item['nama_kategori'];
        if (!isset($expectedDoughnutData[$category])) {
            $expectedDoughnutData[$category] = 0;
        }
        $expectedDoughnutData[$category]++;
    }
    
    // Sort by count descending
    arsort($expectedDoughnutData);
    foreach($expectedDoughnutData as $category => $count) {
        echo $category . ": " . $count . " items\n";
    }
    
    // Get actual chart data from database queries
    echo "\n=== ACTUAL COLUMN CHART DATA (FROM QUERY) ===\n";
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
    $actualColumnData = $stmtProject->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($actualColumnData as $item) {
        echo $item['category_name'] . ": " . number_format($item['total_value'], 2) . "\n";
    }
    
    echo "\n=== ACTUAL DOUGHNUT CHART DATA (FROM QUERY) ===\n";
    $stmtCategory = $pdo->prepare("
        SELECT 
            kb.nama_kategori as category_name,
            COUNT(i.idinventory) as total_items
        FROM inventory i
        LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
        GROUP BY i.idkategori, kb.nama_kategori
        ORDER BY total_items DESC
    ");
    $stmtCategory->execute();
    $actualDoughnutData = $stmtCategory->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($actualDoughnutData as $item) {
        echo $item['category_name'] . ": " . $item['total_items'] . " items\n";
    }
    
    // Verification
    echo "\n=== VERIFICATION ===\n";
    $columnMatch = true;
    foreach($expectedColumnData as $category => $expectedValue) {
        $found = false;
        foreach($actualColumnData as $actualItem) {
            if ($actualItem['category_name'] == $category) {
                $actualValue = floatval($actualItem['total_value']);
                if (abs($expectedValue - $actualValue) < 0.01) {
                    echo "✓ Column chart data MATCH for " . $category . "\n";
                } else {
                    echo "✗ Column chart data MISMATCH for " . $category . " (Expected: " . number_format($expectedValue, 2) . ", Actual: " . number_format($actualValue, 2) . ")\n";
                    $columnMatch = false;
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "✗ Column chart data MISSING for " . $category . "\n";
            $columnMatch = false;
        }
    }
    
    $doughnutMatch = true;
    foreach($expectedDoughnutData as $category => $expectedCount) {
        $found = false;
        foreach($actualDoughnutData as $actualItem) {
            if ($actualItem['category_name'] == $category) {
                $actualCount = intval($actualItem['total_items']);
                if ($expectedCount == $actualCount) {
                    echo "✓ Doughnut chart data MATCH for " . $category . "\n";
                } else {
                    echo "✗ Doughnut chart data MISMATCH for " . $category . " (Expected: " . $expectedCount . ", Actual: " . $actualCount . ")\n";
                    $doughnutMatch = false;
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "✗ Doughnut chart data MISSING for " . $category . "\n";
            $doughnutMatch = false;
        }
    }
    
    if ($columnMatch && $doughnutMatch) {
        echo "\n✅ ALL DATA IS SYNCHRONIZED BETWEEN DATABASE AND CHARTS!\n";
    } else {
        echo "\n❌ SOME DATA IS NOT SYNCHRONIZED. PLEASE CHECK THE QUERIES.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
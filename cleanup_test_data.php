<?php
// Clean up all test data
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Cleanup Test Data</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
    </style>
</head>
<body>
<h1>🧹 Cleanup Test Data</h1>";

try {
    // Delete all test PRs
    $stmt = $pdo->prepare("DELETE FROM detailrequest WHERE idrequest LIKE 'TEST%'");
    $stmt->execute();
    echo "<div class='success'>✓ Deleted " . $stmt->rowCount() . " test detail requests</div>";
    
    $stmt = $pdo->prepare("DELETE FROM purchaserequest WHERE idrequest LIKE 'TEST%'");
    $stmt->execute();
    echo "<div class='success'>✓ Deleted " . $stmt->rowCount() . " test purchase requests</div>";
    
    // Delete test barang (BRG with high numbers or TEST codes)
    $stmt = $pdo->prepare("DELETE FROM m_barang WHERE kodebarang LIKE 'BR-TEST%' OR kodebarang LIKE 'BR-MANUAL%' OR kodebarang LIKE 'BR-INIT%'");
    $stmt->execute();
    echo "<div class='success'>✓ Deleted " . $stmt->rowCount() . " test barang (old format)</div>";
    
    echo "<hr>";
    
    // Show current data
    echo "<h2>Current Data in Database</h2>";
    
    // Show PRs
    $stmt = $pdo->query("SELECT idrequest, namarequestor, idsupervisor, keterangan, status FROM purchaserequest ORDER BY idrequest DESC LIMIT 20");
    $prs = $stmt->fetchAll();
    
    echo "<h3>Purchase Requests (" . count($prs) . " rows)</h3>";
    if (count($prs) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID Request</th><th>Requestor</th><th>Supervisor</th><th>Keterangan</th><th>Status</th></tr>";
        foreach ($prs as $pr) {
            echo "<tr>";
            echo "<td>{$pr['idrequest']}</td>";
            echo "<td>{$pr['namarequestor']}</td>";
            echo "<td>{$pr['idsupervisor']}</td>";
            echo "<td>{$pr['keterangan']}</td>";
            echo "<td>{$pr['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No purchase requests found. Form is ready for fresh data!</div>";
    }
    
    // Show barang
    $stmt = $pdo->query("SELECT idbarang, kodebarang, nama_barang, harga FROM m_barang ORDER BY idbarang DESC LIMIT 20");
    $barangs = $stmt->fetchAll();
    
    echo "<h3>Barang (" . count($barangs) . " rows)</h3>";
    if (count($barangs) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Kode</th><th>Nama</th><th>Harga</th></tr>";
        foreach ($barangs as $barang) {
            echo "<tr>";
            echo "<td>{$barang['idbarang']}</td>";
            echo "<td>{$barang['kodebarang']}</td>";
            echo "<td>{$barang['nama_barang']}</td>";
            echo "<td>{$barang['harga']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No barang found. Database is clean!</div>";
    }
    
    echo "<hr>";
    echo "<div class='success'><h2>✅ Cleanup Complete!</h2></div>";
    echo "<p><strong>Database is now clean and ready for real form submissions.</strong></p>";
    
    echo "<p><a href='pages/purchase-request.php'>→ Go to Purchase Request Form</a></p>";
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>

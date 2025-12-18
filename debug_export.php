<?php
require_once 'config/database.php';

// Test query for purchase request data
$query = "
    SELECT 
        pr.idrequest,
        pr.tgl_req,
        pr.tgl_butuh,
        pr.namarequestor,
        pr.keterangan,
        pr.idsupervisor,
        COALESCE(ls.status, 0) as status_code,
        CASE COALESCE(ls.status, 0)
            WHEN 1 THEN 'Process Approval Leader'
            WHEN 2 THEN 'Process Approval Manager'
            WHEN 3 THEN 'Approved'
            WHEN 4 THEN 'Hold'
            WHEN 5 THEN 'Reject'
            WHEN 6 THEN 'Done'
            ELSE 'Pending'
        END as status_name,
        GROUP_CONCAT(dr.idbarang SEPARATOR ', ') as idbarang,
        GROUP_CONCAT(mb.kodebarang SEPARATOR ', ') as kodebarang,
        GROUP_CONCAT(mb.satuan SEPARATOR ', ') as satuan,
        GROUP_CONCAT(dr.linkpembelian SEPARATOR ', ') as linkpembelian,
        GROUP_CONCAT(dr.namaitem SEPARATOR ', ') as namaitem,
        GROUP_CONCAT(dr.deskripsi SEPARATOR ', ') as deskripsi,
        GROUP_CONCAT(dr.harga SEPARATOR ', ') as harga,
        GROUP_CONCAT(dr.qty SEPARATOR ', ') as qty,
        GROUP_CONCAT(dr.total SEPARATOR ', ') as total,
        GROUP_CONCAT(dr.kodeproject SEPARATOR ', ') as kodeproject
    FROM purchaserequest pr
    LEFT JOIN (
        SELECT idrequest, status
        FROM (
            SELECT idrequest, status,
                   ROW_NUMBER() OVER (PARTITION BY idrequest ORDER BY date DESC) as rn
            FROM logstatusreq
        ) ranked
        WHERE rn = 1
    ) ls ON pr.idrequest = ls.idrequest
    LEFT JOIN detailrequest dr ON pr.idrequest = dr.idrequest
    LEFT JOIN m_barang mb ON dr.idbarang = mb.idbarang
    GROUP BY pr.idrequest, pr.tgl_req, pr.tgl_butuh, pr.namarequestor, pr.keterangan, pr.idsupervisor, ls.status
    ORDER BY pr.tgl_req DESC
    LIMIT 10";

$stmt = $pdo->prepare($query);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Debug Export Data</h2>";
echo "<p>Total rows: " . count($items) . "</p>";

if (empty($items)) {
    echo "<p>No data found</p>";
} else {
    echo "<table border='1'>";
    echo "<tr>";
    foreach (array_keys($items[0]) as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr>";
    
    foreach ($items as $item) {
        echo "<tr>";
        foreach ($item as $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>
<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get parameters
$tab = $_GET['tab'] ?? 'purchase-request';
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$search = $_GET['search'] ?? '';

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=procurement_data_" . $tab . "_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

if ($tab == 'purchase-request') {
    // Query for purchase request data
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
        WHERE 1=1";
    
    $params = [];
    if ($search) {
        $query .= " AND (pr.idrequest LIKE :search OR pr.keterangan LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($selectedYear > 0) {
        $query .= " AND YEAR(pr.tgl_req) = :year";
        $params[':year'] = $selectedYear;
    }
    if ($selectedMonth > 0) {
        $query .= " AND MONTH(pr.tgl_req) = :month";
        $params[':month'] = $selectedMonth;
    }
    
    $query .= "
        GROUP BY pr.idrequest, pr.tgl_req, pr.tgl_butuh, pr.namarequestor, pr.keterangan, pr.idsupervisor, ls.status
        ORDER BY pr.tgl_req DESC";
        
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output headers
    echo "No\tID Request\tTanggal Request\tTanggal Butuh\tNama Requestor\tKeterangan\tID Supervisor\tStatus\tID Barang\tKode Barang\tSatuan\tLink Pembelian\tNama Item\tDeskripsi\tHarga\tQty\tTotal\tKode Project\n";
    
    // Output data
    $no = 1;
    foreach ($items as $item) {
        echo "{$no}\t{$item['idrequest']}\t{$item['tgl_req']}\t{$item['tgl_butuh']}\t{$item['namarequestor']}\t{$item['keterangan']}\t{$item['idsupervisor']}\t{$item['status_name']}\t{$item['idbarang']}\t{$item['kodebarang']}\t{$item['satuan']}\t{$item['linkpembelian']}\t{$item['namaitem']}\t{$item['deskripsi']}\t{$item['harga']}\t{$item['qty']}\t{$item['total']}\t{$item['kodeproject']}\n";
        $no++;
    }
    
} elseif ($tab == 'purchase-order') {
    // Query for purchase order data
    $query = "
        SELECT 
            po.idpurchaseorder,
            po.tgl_po,
            po.supplier,
            pr.idrequest,
            pr.keterangan,
            COALESCE(lso.status, 'Pending') as status_name
        FROM purchaseorder po
        LEFT JOIN purchaserequest pr ON po.idrequest = pr.idrequest
        LEFT JOIN (
            SELECT idpurchaseorder, status
            FROM (
                SELECT idpurchaseorder, status,
                       ROW_NUMBER() OVER (PARTITION BY idpurchaseorder ORDER BY date DESC) as rn
                FROM logstatusorder
            ) ranked
            WHERE rn = 1
        ) lso ON po.idpurchaseorder = lso.idpurchaseorder
        WHERE 1=1";
    
    $params = [];
    if ($search) {
        $query .= " AND (po.idpurchaseorder LIKE :search OR po.supplier LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($selectedYear > 0) {
        $query .= " AND YEAR(po.tgl_po) = :year";
        $params[':year'] = $selectedYear;
    }
    if ($selectedMonth > 0) {
        $query .= " AND MONTH(po.tgl_po) = :month";
        $params[':month'] = $selectedMonth;
    }
    
    $query .= " ORDER BY po.tgl_po DESC";
        
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output headers
    echo "No\tID Purchase Order\tTanggal PO\tSupplier\tID Request\tKeterangan\tStatus\n";
    
    // Output data
    $no = 1;
    foreach ($items as $item) {
        echo "{$no}\t{$item['idpurchaseorder']}\t{$item['tgl_po']}\t{$item['supplier']}\t{$item['idrequest']}\t{$item['keterangan']}\t{$item['status_name']}\n";
        $no++;
    }
}

exit();
?>
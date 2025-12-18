<?php
require_once 'config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get parameters
$tab = $_GET['tab'] ?? 'purchase-request';
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$search = $_GET['search'] ?? '';

// Set headers for Excel download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="procurement_data_' . $tab . '_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Include PhpSpreadsheet library
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

try {
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
        
        // Set column headers
        $headers = [
            'No',
            'ID Request',
            'Tanggal Request',
            'Tanggal Butuh',
            'Nama Requestor',
            'Keterangan',
            'ID Supervisor',
            'Status',
            'ID Barang',
            'Kode Barang',
            'Satuan',
            'Link Pembelian',
            'Nama Item',
            'Deskripsi',
            'Harga',
            'Qty',
            'Total',
            'Kode Project'
        ];
        
        // Write headers
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        
        // Write data
        $row = 2;
        $no = 1;
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item['idrequest']);
            $sheet->setCellValue('C' . $row, $item['tgl_req']);
            $sheet->setCellValue('D' . $row, $item['tgl_butuh']);
            $sheet->setCellValue('E' . $row, $item['namarequestor']);
            $sheet->setCellValue('F' . $row, $item['keterangan']);
            $sheet->setCellValue('G' . $row, $item['idsupervisor']);
            $sheet->setCellValue('H' . $row, $item['status_name']);
            $sheet->setCellValue('I' . $row, $item['idbarang']);
            $sheet->setCellValue('J' . $row, $item['kodebarang']);
            $sheet->setCellValue('K' . $row, $item['satuan']);
            $sheet->setCellValue('L' . $row, $item['linkpembelian']);
            $sheet->setCellValue('M' . $row, $item['namaitem']);
            $sheet->setCellValue('N' . $row, $item['deskripsi']);
            $sheet->setCellValue('O' . $row, $item['harga']);
            $sheet->setCellValue('P' . $row, $item['qty']);
            $sheet->setCellValue('Q' . $row, $item['total']);
            $sheet->setCellValue('R' . $row, $item['kodeproject']);
            $row++;
        }
        
        // Set sheet title
        $sheet->setTitle('Purchase Request');
        
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
        
        // Set column headers
        $headers = [
            'No',
            'ID Purchase Order',
            'Tanggal PO',
            'Supplier',
            'ID Request',
            'Keterangan',
            'Status'
        ];
        
        // Write headers
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        
        // Write data
        $row = 2;
        $no = 1;
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item['idpurchaseorder']);
            $sheet->setCellValue('C' . $row, $item['tgl_po']);
            $sheet->setCellValue('D' . $row, $item['supplier']);
            $sheet->setCellValue('E' . $row, $item['idrequest']);
            $sheet->setCellValue('F' . $row, $item['keterangan']);
            $sheet->setCellValue('G' . $row, $item['status_name']);
            $row++;
        }
        
        // Set sheet title
        $sheet->setTitle('Purchase Order');
    }
    
    // Auto-size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
    
} catch (Exception $e) {
    // Handle error
    header('Content-Type: text/plain');
    echo "Error: " . $e->getMessage();
    exit();
}
?>
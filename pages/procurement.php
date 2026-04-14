<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Function to generate PR ID
 * @param PDO $pdo Database connection
 * @return string Generated PR ID in format PRYYYYNNNN
 */
function generatePRId(PDO $pdo) {
    $year = date('Y');

    $stmt = $pdo->prepare("
        SELECT idrequest 
        FROM purchaserequest 
        WHERE idrequest LIKE :prefix 
        ORDER BY idrequest DESC 
        LIMIT 1
    ");
    $stmt->execute([':prefix' => "PR{$year}%"]);
    $lastId = $stmt->fetchColumn();

    if ($lastId) {
        $lastNumber = (int) substr($lastId, -4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    return 'PR' . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

require_once __DIR__ . '/../config/database.php';require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
$auth->checkAccess();

// Initialize variables
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$poStatusFilter = isset($_GET['po_status']) ? $_GET['po_status'] : '';

// Sorting variables for Purchase Request
$prSort = isset($_GET['pr_sort']) ? $_GET['pr_sort'] : 'tgl_req';
$prOrder = isset($_GET['pr_order']) ? $_GET['pr_order'] : 'DESC';
$allowedPRSort = ['idrequest', 'tgl_req', 'namarequestor', 'status_code', 'total_items', 'total_amount'];
if (!in_array($prSort, $allowedPRSort)) $prSort = 'tgl_req';
$prOrder = strtoupper($prOrder) === 'ASC' ? 'ASC' : 'DESC';

// Generate ORDER BY clause for PR (handle aggregate columns)
$prOrderBy = match($prSort) {
    'total_items' => "COUNT(dr.iddetailrequest) $prOrder",
    'total_amount' => "SUM(dr.total) $prOrder",
    'status_code' => "COALESCE(ls.status, 0) $prOrder",
    default => "pr.$prSort $prOrder"
};

// Sorting variables for Purchase Order
$poSort = isset($_GET['po_sort']) ? $_GET['po_sort'] : 'tgl_po';
$poOrder = isset($_GET['po_order']) ? $_GET['po_order'] : 'DESC';
$allowedPOSort = ['idpurchaseorder', 'tgl_po', 'supplier', 'status'];
if (!in_array($poSort, $allowedPOSort)) $poSort = 'tgl_po';
$poOrder = strtoupper($poOrder) === 'ASC' ? 'ASC' : 'DESC';

// Generate ORDER BY clause for PO (handle status column)
$poOrderBy = match($poSort) {
    'status' => "COALESCE(lso.status, 0) $poOrder",
    default => "po.$poSort $poOrder"
};

// Fetch users for supervisor dropdown
$qUsers = $pdo->prepare("SELECT iduser, nama FROM users WHERE roletype IN ('Leader', 'Manager') ORDER BY nama");
$qUsers->execute();
$users = $qUsers->fetchAll(PDO::FETCH_ASSOC);

// Di bagian atas file, tambahkan endpoint AJAX untuk mengambil data barang
if (isset($_POST['get_barang_data']) && isset($_POST['idbarang'])) {
    header('Content-Type: application/json');
        
    $idbarang = $_POST['idbarang'];
    $stmt = $pdo->prepare("SELECT idbarang, kodebarang, nama_barang, deskripsi, harga, satuan, kodeproject FROM m_barang WHERE idbarang = ?");
    $stmt->execute([$idbarang]);
    $barang = $stmt->fetch(PDO::FETCH_ASSOC);
        
    echo json_encode($barang);
    exit;
}
    
// Endpoint AJAX untuk mengambil detail request
if (isset($_POST['get_request_details']) && isset($_POST['idrequest'])) {
    header('Content-Type: application/json');
        
    $idrequest = $_POST['idrequest'];
    $stmt = $pdo->prepare("SELECT idbarang, qty, harga, total FROM detailrequest WHERE idrequest = ?");
    $stmt->execute([$idrequest]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    echo json_encode($details);
    exit;
}
    
// Endpoint AJAX untuk mengambil data purchase order
if (isset($_POST['get_purchase_order_data']) && isset($_POST['idpurchaseorder'])) {
    header('Content-Type: application/json');
        
    $idpurchaseorder = $_POST['idpurchaseorder'];
        
    // Get purchase order data
    $stmt = $pdo->prepare("SELECT idpurchaseorder, idrequest, supplier, tgl_po FROM purchaseorder WHERE idpurchaseorder = ?");
    $stmt->execute([$idpurchaseorder]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
    // Get detail order data
    $stmt = $pdo->prepare("SELECT iddetailorder, idpurchaseorder, idbarang, qty, harga, total FROM detailorder WHERE idpurchaseorder = ?");
    $stmt->execute([$idpurchaseorder]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    echo json_encode([
        'order' => $order,
        'details' => $details
    ]);
    exit;
}

// Fetch items from m_barang for dropdown
$qItems = $pdo->prepare("SELECT idbarang, kodebarang, nama_barang, deskripsi, harga, satuan, kodeproject FROM m_barang ORDER BY nama_barang");
$qItems->execute();
$itemsList = $qItems->fetchAll(PDO::FETCH_ASSOC);



// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle add purchase request
    if (isset($_POST['add_purchase_request'])) {
        // Log form data for debugging
        error_log('Add purchase request form submitted');
        error_log('Form data: ' . print_r($_POST, true));
        $namarequestor = $_POST['namarequestor'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';
        $tgl_req = $_POST['tgl_req'] ?? date('Y-m-d H:i:s');
        $tgl_butuh = $_POST['tgl_butuh'] ?? date('Y-m-d');
        $idsupervisor = $_POST['idsupervisor'] ?? null;
        
        // Detail request fields
        $idbarang = $_POST['idbarang'] ?? '';
        $linkpembelian = $_POST['linkpembelian'] ?? '';
        $namaitem = $_POST['namaitem'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $harga = $_POST['harga'] ?? 0;
        $qty = $_POST['qty'] ?? 0;
        $total = $_POST['total'] ?? 0;
        $kodeproject = $_POST['kodeproject'] ?? '';        
        // No need to override with hidden values since we're not using them anymore
        
        // Get current user ID and role
        $iduserrequest = $_SESSION['user_id'] ?? null;
        $currentUserRole = $_SESSION['role'] ?? '';
        $currentUsername = $_SESSION['username'] ?? '';
        
        // Generate ID for purchaserequest using the generatePRId function
        $idrequest = generatePRId($pdo);        
        // Determine initial status based on user role
        $initialStatus = 1; // Default to Process Approval Leader
        
        // Special cases based on role
        if ($currentUserRole === 'Manager') {
            $initialStatus = 3; // Auto-approved for managers
        } elseif ($currentUserRole === 'Procurement' && $namarequestor !== $currentUsername) {
            $initialStatus = 2; // Process Approval Manager if created by procurement for someone else
        }
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            error_log('Database transaction started for add purchase request');
            
            // Insert purchase request
            $insertPR = $pdo->prepare("INSERT INTO purchaserequest (idrequest, iduserrequest, tgl_req, namarequestor, keterangan, tgl_butuh, idsupervisor) VALUES (?, ?, ?, ?, ?, ?, ?)");
            error_log('Executing purchase request insert query');
            if ($insertPR->execute([$idrequest, $iduserrequest, $tgl_req, $namarequestor, $keterangan, $tgl_butuh, $idsupervisor])) {
                error_log('Purchase request insert successful');                
                // Insert detail request if any detail fields are provided
                if (!empty($idbarang) || !empty($namaitem)) {
                    error_log('Inserting detail request');
                    $insertDetail = $pdo->prepare("INSERT INTO detailrequest (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject, kodebarang, satuan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $insertDetail->execute([$idbarang, $idrequest, $linkpembelian, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $kodebarang, $satuan, 1]);                    error_log('Detail request insert result: ' . ($result ? 'success' : 'failed'));
                }
                
                // Log initial status
                error_log('Inserting log status');
                $logStatus = $pdo->prepare("INSERT INTO logstatusreq (idrequest, status, date) VALUES (?, ?, NOW())");
                $result = $logStatus->execute([$idrequest, $initialStatus]);
                error_log('Log status insert result: ' . ($result ? 'success' : 'failed'));                
                // Commit transaction
                error_log('Committing transaction');
                $pdo->commit();
                error_log('Transaction committed successfully');
                
                // Redirect to avoid resubmission
                header("Location: procurement.php?tab=purchase-request&success=add_success");
                exit();
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            error_log('Error in add purchase request: ' . $e->getMessage());
            error_log('Form data: ' . print_r($_POST, true));
            $pdo->rollback();
            // Redirect with error
            header("Location: procurement.php?tab=purchase-request&error=add_failed&message=" . urlencode($e->getMessage()));
            exit();
        }
    }
    
    // Handle edit purchase request
    if (isset($_POST['edit_purchase_request'])) {
        $idrequest = $_POST['idrequest'] ?? 0;
        $namarequestor = $_POST['namarequestor'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';
        $tgl_req = $_POST['tgl_req'] ?? date('Y-m-d H:i:s');
        $tgl_butuh = $_POST['tgl_butuh'] ?? date('Y-m-d');
        $idsupervisor = $_POST['idsupervisor'] ?? null;
        
        // Detail request fields
        $idbarang = $_POST['idbarang'] ?? '';
        $linkpembelian = $_POST['linkpembelian'] ?? '';
        $namaitem = $_POST['namaitem'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $harga = $_POST['harga'] ?? 0;
        $qty = $_POST['qty'] ?? 0;
        $total = $_POST['total'] ?? 0;
        $kodeproject = $_POST['kodeproject'] ?? '';
        $kodebarang = $_POST['kodebarang'] ?? '';
        $satuan = $_POST['satuan'] ?? '';
        
        // No need to override with hidden values since we're not using them anymore        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update purchase request
            $updatePR = $pdo->prepare("UPDATE purchaserequest SET tgl_req = ?, namarequestor = ?, keterangan = ?, tgl_butuh = ?, idsupervisor = ? WHERE idrequest = ?");
            $updatePR->execute([$tgl_req, $namarequestor, $keterangan, $tgl_butuh, $idsupervisor, $idrequest]);
            
            // Update or insert detail request
            // First check if detail request exists
            $checkDetail = $pdo->prepare("SELECT iddetailrequest FROM detailrequest WHERE idrequest = ? LIMIT 1");
            $checkDetail->execute([$idrequest]);
            $detail = $checkDetail->fetch();
            
            if ($detail) {
                // Update existing detail request
                $updateDetail = $pdo->prepare("UPDATE detailrequest SET idbarang = ?, linkpembelian = ?, namaitem = ?, deskripsi = ?, harga = ?, qty = ?, total = ?, kodeproject = ?, kodebarang = ?, satuan = ?, status = ? WHERE idrequest = ?");
                $updateDetail->execute([$idbarang, $linkpembelian, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $kodebarang, $satuan, 1, $idrequest]);
            } else {
                // Insert new detail request
                $insertDetail = $pdo->prepare("INSERT INTO detailrequest (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject, kodebarang, satuan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertDetail->execute([$idbarang, $idrequest, $linkpembelian, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $kodebarang, $satuan, 1]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to avoid resubmission
            header("Location: procurement.php?tab=purchase-request&success=update_success");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            // Redirect with error
            header("Location: procurement.php?tab=purchase-request&error=update_failed&message=" . urlencode($e->getMessage()));
            exit();        }
    }
    
    // Handle delete purchase request
    if (isset($_POST['delete_purchase_request'])) {
        $idrequest = $_POST['idrequest'] ?? 0;
        
        // Debug: Log the received ID
        error_log("Delete request received for ID: " . $idrequest);
        
        // Validate that idrequest is provided
        if (empty($idrequest)) {
            header("Location: procurement.php?tab=purchase-request&error=delete_failed&message=" . urlencode("Invalid request ID: " . $idrequest));
            exit();
        }
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete related records first (foreign key constraints)
            // First delete logstatusreq entries
            $deleteLogStatus = $pdo->prepare("DELETE FROM logstatusreq WHERE idrequest = ?");
            $deleteLogStatus->execute([$idrequest]);
            error_log("Deleted " . $deleteLogStatus->rowCount() . " log status records");
            
            // Get all detailrequest IDs for this purchase request to handle logstatusbarang constraint
            $getDetailIds = $pdo->prepare("SELECT iddetailrequest FROM detailrequest WHERE idrequest = ?");
            $getDetailIds->execute([$idrequest]);
            $detailIds = $getDetailIds->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete logstatusbarang entries that reference these detail requests
            if (!empty($detailIds)) {
                $placeholders = str_repeat('?,', count($detailIds) - 1) . '?';
                $deleteLogStatusBarang = $pdo->prepare("DELETE FROM logstatusbarang WHERE iddetailrequest IN ($placeholders)");
                $deleteLogStatusBarang->execute($detailIds);
                error_log("Deleted " . $deleteLogStatusBarang->rowCount() . " log status barang records");
            }
            
            // Now delete detailrequest entries
            $deleteDetailRequest = $pdo->prepare("DELETE FROM detailrequest WHERE idrequest = ?");
            $deleteDetailRequest->execute([$idrequest]);
            error_log("Deleted " . $deleteDetailRequest->rowCount() . " detail request records");
            
            // Finally delete the purchase request
            $deletePR = $pdo->prepare("DELETE FROM purchaserequest WHERE idrequest = ?");
            $result = $deletePR->execute([$idrequest]);
            error_log("Deleted " . $deletePR->rowCount() . " purchase request records");
            
            // Check if any row was actually deleted
            if ($deletePR->rowCount() == 0) {
                throw new Exception("No purchase request found with ID: " . $idrequest);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to avoid resubmission
            header("Location: procurement.php?tab=purchase-request&success=delete_success");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            // Log the error for debugging
            error_log("Delete failed for ID " . $idrequest . ": " . $e->getMessage());
            // Redirect with error
            header("Location: procurement.php?tab=purchase-request&error=delete_failed&message=" . urlencode($e->getMessage()));
            exit();
        }
    }
    
    // Handle add purchase order
    if (isset($_POST['add_purchase_order'])) {
        $idrequest = $_POST['idrequest'] ?? '';
        $supplier = $_POST['supplier'] ?? '';
        $tgl_po = $_POST['tgl_po'] ?? date('Y-m-d');
        
        // Detail order fields
        $idbarang = $_POST['idbarang'] ?? [];
        $qty = $_POST['qty'] ?? [];
        $harga = $_POST['harga'] ?? [];
        $total = $_POST['total'] ?? [];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Generate ID for purchaseorder (POYYYYNNNN format)
            $year = date('Y');
            $stmt = $pdo->prepare(
                "SELECT idpurchaseorder 
                FROM purchaseorder 
                WHERE idpurchaseorder LIKE :prefix 
                ORDER BY idpurchaseorder DESC 
                LIMIT 1"
            );
            $stmt->execute([':prefix' => "PO{$year}%"]);
            $lastId = $stmt->fetchColumn();
            
            if ($lastId) {
                $lastNumber = (int) substr($lastId, -4);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }
            
            $idpurchaseorder = 'PO' . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
            
            // Insert purchase order
            $insertPO = $pdo->prepare("INSERT INTO purchaseorder (idpurchaseorder, idrequest, supplier, tgl_po) VALUES (?, ?, ?, ?)");
            $insertPO->execute([$idpurchaseorder, $idrequest, $supplier, $tgl_po]);
            
            // Insert detail orders
            for ($i = 0; $i < count($idbarang); $i++) {
                if (!empty($idbarang[$i]) && !empty($qty[$i])) {
                    $detailHarga = $harga[$i] ?? 0;
                    $detailTotal = $total[$i] ?? 0;
                    
                    $insertDetail = $pdo->prepare("INSERT INTO detailorder (idpurchaseorder, idbarang, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
                    $insertDetail->execute([$idpurchaseorder, $idbarang[$i], $qty[$i], $detailHarga, $detailTotal]);
                }
            }
            
            // Insert initial status log
            $logStatus = $pdo->prepare("INSERT INTO logstatusorder (idpurchaseorder, status, date, keterangan) VALUES (?, ?, NOW(), ?)");
            $logStatus->execute([$idpurchaseorder, 'Process Order', 'New purchase order created']);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to avoid resubmission
            header("Location: procurement.php?tab=purchase-order&success=add_po_success");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            // Redirect with error
            header("Location: procurement.php?tab=purchase-order&error=add_po_failed&message=" . urlencode($e->getMessage()));
            exit();
        }
    }
    
    // Handle edit purchase order
    if (isset($_POST['edit_purchase_order'])) {
        $idpurchaseorder = $_POST['idpurchaseorder'] ?? '';
        $supplier = $_POST['supplier'] ?? '';
        $tgl_po = $_POST['tgl_po'] ?? date('Y-m-d');
        
        // Detail order fields
        $idbarang = $_POST['idbarang'] ?? [];
        $qty = $_POST['qty'] ?? [];
        $harga = $_POST['harga'] ?? [];
        $total = $_POST['total'] ?? [];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update purchase order
            $updatePO = $pdo->prepare("UPDATE purchaseorder SET supplier = ?, tgl_po = ? WHERE idpurchaseorder = ?");
            $updatePO->execute([$supplier, $tgl_po, $idpurchaseorder]);
            
            // Delete existing detail orders
            $deleteDetail = $pdo->prepare("DELETE FROM detailorder WHERE idpurchaseorder = ?");
            $deleteDetail->execute([$idpurchaseorder]);
            
            // Insert new detail orders
            for ($i = 0; $i < count($idbarang); $i++) {
                if (!empty($idbarang[$i]) && !empty($qty[$i])) {
                    $detailHarga = $harga[$i] ?? 0;
                    $detailTotal = $total[$i] ?? 0;
                    
                    $insertDetail = $pdo->prepare("INSERT INTO detailorder (idpurchaseorder, idbarang, qty, harga, total) VALUES (?, ?, ?, ?, ?)");
                    $insertDetail->execute([$idpurchaseorder, $idbarang[$i], $qty[$i], $detailHarga, $detailTotal]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to avoid resubmission
            header("Location: procurement.php?tab=purchase-order&success=update_po_success");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            // Redirect with error
            header("Location: procurement.php?tab=purchase-order&error=update_po_failed&message=" . urlencode($e->getMessage()));
            exit();
        }
    }
    
    // Handle delete purchase order
    if (isset($_POST['delete_purchase_order'])) {
        // Check user role - only Admin and Procurement can delete PO
        $userRole = $_SESSION['role'] ?? '';
        if ($userRole !== 'Admin' && $userRole !== 'Procurement') {
            header("Location: procurement.php?tab=purchase-order&error=delete_po_failed&message=" . urlencode("You do not have permission to delete purchase orders."));
            exit();
        }
        
        $idpurchaseorder = $_POST['idpurchaseorder'] ?? '';        
        // Validate that idpurchaseorder is provided
        if (empty($idpurchaseorder)) {
            header("Location: procurement.php?tab=purchase-order&error=delete_po_failed&message=" . urlencode("Invalid purchase order ID"));
            exit();
        }
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete related records first (foreign key constraints)
            // First delete logstatusorder entries
            $deleteLogStatus = $pdo->prepare("DELETE FROM logstatusorder WHERE idpurchaseorder = ?");
            $deleteLogStatus->execute([$idpurchaseorder]);
            
            // Delete detailorder entries
            $deleteDetailOrder = $pdo->prepare("DELETE FROM detailorder WHERE idpurchaseorder = ?");
            $deleteDetailOrder->execute([$idpurchaseorder]);
            
            // Finally delete the purchase order
            $deletePO = $pdo->prepare("DELETE FROM purchaseorder WHERE idpurchaseorder = ?");
            $result = $deletePO->execute([$idpurchaseorder]);
            
            // Check if any row was actually deleted
            if ($deletePO->rowCount() == 0) {
                throw new Exception("No purchase order found with ID: " . $idpurchaseorder);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to avoid resubmission
            header("Location: procurement.php?tab=purchase-order&success=delete_po_success");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            // Redirect with error
            header("Location: procurement.php?tab=purchase-order&error=delete_po_failed&message=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Handle filter parameters more robustly
$selectedYear = 0;
$selectedMonth = 0;

if (isset($_GET['year']) && $_GET['year'] !== '' && $_GET['year'] !== '0' && $_GET['year'] !== 'All Years') {
    $selectedYear = (int)$_GET['year'];
}

if (isset($_GET['month']) && $_GET['month'] !== '' && $_GET['month'] !== '0' && $_GET['month'] !== 'All Months') {
    $selectedMonth = (int)$_GET['month'];
}

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
// Ensure limit is one of the allowed values
if (!in_array($limit, [10, 25, 50, 100])) {
    $limit = 25;
}
$offset = ($page - 1) * $limit;

// Query untuk Overview - Total Amount Purchase Order
$queryTotalPO = "
    SELECT 
        COALESCE(SUM(dor.total), 0) as total_amount
    FROM purchaseorder po
    LEFT JOIN detailorder dor ON po.idpurchaseorder = dor.idpurchaseorder
    WHERE 1=1
        " . ($selectedYear > 0 ? "AND YEAR(po.created_at) = :year" : "") . "
        " . ($selectedMonth > 0 ? "AND MONTH(po.created_at) = :month" : "") . "
";

$stmtTotalPO = $pdo->prepare($queryTotalPO);
$params = [];
if ($selectedYear > 0) {
    $params[':year'] = $selectedYear;
}
if ($selectedMonth > 0) {
    $params[':month'] = $selectedMonth;
}
$stmtTotalPO->execute($params);
$totalPO = $stmtTotalPO->fetch(PDO::FETCH_ASSOC);

// Query untuk Remain Purchase Request
$queryRemainPR = "
    SELECT 
        COUNT(DISTINCT pr.idrequest) as jumlah_pr,
        COALESCE(SUM(dreq.total), 0) as total_amount
    FROM purchaserequest pr
    LEFT JOIN detailrequest dreq ON pr.idrequest = dreq.idrequest
    LEFT JOIN (
        SELECT idrequest, status
        FROM (
            SELECT idrequest, status,
                   ROW_NUMBER() OVER (PARTITION BY idrequest ORDER BY date DESC) as rn
            FROM logstatusreq
        ) ranked
        WHERE rn = 1
    ) ls ON pr.idrequest = ls.idrequest
    WHERE 1=1
        AND COALESCE(ls.status, 0) NOT IN (3, 6)
        " . ($selectedYear > 0 ? "AND YEAR(pr.tgl_req) = :year" : "") . "
        " . ($selectedMonth > 0 ? "AND MONTH(pr.tgl_req) = :month" : "") . "
";

$stmtRemainPR = $pdo->prepare($queryRemainPR);
$params = [];
if ($selectedYear > 0) {
    $params[':year'] = $selectedYear;
}
if ($selectedMonth > 0) {
    $params[':month'] = $selectedMonth;
}
$stmtRemainPR->execute($params);
$remainPR = $stmtRemainPR->fetch(PDO::FETCH_ASSOC);

// Query untuk Chart - Purchase Request by Status
$queryPRStatus = "
    SELECT 
        CASE COALESCE(ls.status, 0)
            WHEN 1 THEN 'Process Approval Leader'
            WHEN 2 THEN 'Process Approval Manager'
            WHEN 3 THEN 'Approved'
            WHEN 4 THEN 'Hold'
            WHEN 5 THEN 'Reject'
            WHEN 6 THEN 'Done'
            ELSE 'Pending'
        END as status_name,
        COALESCE(ls.status, 0) as status_code,
        COUNT(*) as jumlah
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
    WHERE 1=1
        " . ($selectedYear > 0 ? "AND YEAR(pr.tgl_req) = :year" : "") . "
        " . ($selectedMonth > 0 ? "AND MONTH(pr.tgl_req) = :month" : "") . "
    GROUP BY ls.status
    ORDER BY ls.status
";

$stmtPRStatus = $pdo->prepare($queryPRStatus);
$params = [];
if ($selectedYear > 0) {
    $params[':year'] = $selectedYear;
}
if ($selectedMonth > 0) {
    $params[':month'] = $selectedMonth;
}
$stmtPRStatus->execute($params);
$prStatusData = $stmtPRStatus->fetchAll(PDO::FETCH_ASSOC);

// Query untuk Annual Expenditure Chart (12 months data)
$queryAnnualExpenditure = "
    SELECT 
        MONTH(po.created_at) as month_num,
        MONTHNAME(po.created_at) as month_name,
        COALESCE(SUM(dor.total), 0) as total_expenditure
    FROM purchaseorder po
    LEFT JOIN detailorder dor ON po.idpurchaseorder = dor.idpurchaseorder
    WHERE 1=1
        " . ($selectedYear > 0 ? "AND YEAR(po.created_at) = :selected_year" : "AND YEAR(po.created_at) = YEAR(CURDATE())") . "
    GROUP BY MONTH(po.created_at), MONTHNAME(po.created_at)
    ORDER BY MONTH(po.created_at)
";

$stmtAnnualExpenditure = $pdo->prepare($queryAnnualExpenditure);
$annualExpenditureParams = [];
if ($selectedYear > 0) {
    $annualExpenditureParams[':selected_year'] = $selectedYear;
}
$stmtAnnualExpenditure->execute($annualExpenditureParams);
$annualExpenditureData = $stmtAnnualExpenditure->fetchAll(PDO::FETCH_ASSOC);

// Query untuk Progress Purchase Requests Table
$queryProgressPR = "
    SELECT 
        pr.idrequest,
        pr.tgl_req,
        pr.namarequestor,
        pr.keterangan,
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
        COALESCE(progress.items_arrived, 0) as items_arrived,
        COALESCE(progress.total_items, 1) as total_items,
        ROUND((COALESCE(progress.items_arrived, 0) / COALESCE(progress.total_items, 1)) * 100, 2) as progress_percentage
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
    LEFT JOIN (
        SELECT 
            dr.idrequest,
            COUNT(*) as total_items,
            COUNT(CASE WHEN lsb.status = 4 THEN 1 END) as items_arrived
        FROM detailrequest dr
        LEFT JOIN (
            SELECT iddetailrequest, status
            FROM (
                SELECT iddetailrequest, status,
                       ROW_NUMBER() OVER (PARTITION BY iddetailrequest ORDER BY date DESC) as rn
                FROM logstatusbarang
            ) ranked
            WHERE rn = 1
        ) lsb ON dr.iddetailrequest = lsb.iddetailrequest
        GROUP BY dr.idrequest
    ) progress ON pr.idrequest = progress.idrequest
    WHERE 1=1
        AND pr.idrequest IN (
            SELECT DISTINCT po.idrequest 
            FROM purchaseorder po 
            WHERE po.idrequest IS NOT NULL
        )
        " . ($selectedYear > 0 ? "AND YEAR(pr.tgl_req) = :year" : "") . "
        " . ($selectedMonth > 0 ? "AND MONTH(pr.tgl_req) = :month" : "") . "
    ORDER BY pr.tgl_req DESC
    LIMIT 10
";

$stmtProgressPR = $pdo->prepare($queryProgressPR);
$params = [];
if ($selectedYear > 0) {
    $params[':year'] = $selectedYear;
}
if ($selectedMonth > 0) {
    $params[':month'] = $selectedMonth;
}
$stmtProgressPR->execute($params);
$progressPRData = $stmtProgressPR->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$chartLabels = [];
$chartData = [];
$chartColors = [
    'Process Approval Leader' => '#fbbf24',
    'Process Approval Manager' => '#60a5fa',
    'Approved' => '#34d399',
    'Hold' => '#fb923c',
    'Reject' => '#f87171',
    'Done' => '#10b981',
    'Pending' => '#9ca3af'
];
$chartBackgroundColors = [];

foreach ($prStatusData as $row) {
    $chartLabels[] = $row['status_name'];
    $chartData[] = $row['jumlah'];
    $chartBackgroundColors[] = $chartColors[$row['status_name']] ?? '#9ca3af';
}

// Prepare data for annual expenditure chart
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthlyExpenditure = array_fill(0, 12, 0);

foreach ($annualExpenditureData as $row) {
    $monthIndex = $row['month_num'] - 1;
    $monthlyExpenditure[$monthIndex] = (float)$row['total_expenditure'];
}

// Initialize items array
$items = [];
$total_pages = 1;

// Queries based on active tab
if ($activeTab == 'purchase-request') {
    $searchCondition = $search ? "AND (pr.idrequest LIKE :search OR pr.keterangan LIKE :search)" : "";
    
    // Tambahkan filter tahun dan bulan
    $dateFilter = "";
    if ($selectedYear > 0) {
        $dateFilter .= " AND YEAR(pr.tgl_req) = :year";
    }
    if ($selectedMonth > 0) {
        $dateFilter .= " AND MONTH(pr.tgl_req) = :month";
    }
    
    // Tambahkan filter status
    $statusCondition = "";
    if ($statusFilter !== '') {
        $statusCondition .= " AND COALESCE(ls.status, 0) = :status";
    }
    
    $countQuery = "SELECT COUNT(*) FROM purchaserequest pr 
                   LEFT JOIN (
                       SELECT idrequest, status
                       FROM (
                           SELECT idrequest, status,
                                  ROW_NUMBER() OVER (PARTITION BY idrequest ORDER BY date DESC) as rn
                           FROM logstatusreq
                       ) ranked
                       WHERE rn = 1
                   ) ls ON pr.idrequest = ls.idrequest
                   WHERE 1=1 $searchCondition $dateFilter $statusCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($statusFilter !== '') $countStmt->bindValue(':status', $statusFilter, PDO::PARAM_INT);
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    $stmt = $pdo->prepare("
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
            ls.note_reject,
            COUNT(dr.iddetailrequest) as total_items,
            SUM(dr.total) as total_amount
        FROM purchaserequest pr
        LEFT JOIN (
            SELECT idrequest, status, note_reject
            FROM (
                SELECT idrequest, status, note_reject,
                       ROW_NUMBER() OVER (PARTITION BY idrequest ORDER BY date DESC) as rn
                FROM logstatusreq
            ) ranked
            WHERE rn = 1
        ) ls ON pr.idrequest = ls.idrequest
        LEFT JOIN detailrequest dr ON pr.idrequest = dr.idrequest
        WHERE 1=1 $searchCondition $dateFilter $statusCondition
        GROUP BY pr.idrequest, pr.tgl_req, pr.tgl_butuh, pr.namarequestor, pr.keterangan, pr.idsupervisor, ls.status, ls.note_reject
        ORDER BY $prOrderBy
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($statusFilter !== '') $stmt->bindValue(':status', $statusFilter, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($activeTab == 'purchase-order') {
    $searchCondition = $search ? "AND (po.idpurchaseorder LIKE :search OR po.supplier LIKE :search)" : "";
    
    // Tambahkan filter tahun dan bulan
    $dateFilter = "";
    if ($selectedYear > 0) {
        $dateFilter .= " AND YEAR(po.tgl_po) = :year";
    }
    if ($selectedMonth > 0) {
        $dateFilter .= " AND MONTH(po.tgl_po) = :month";
    }
    
    // Tambahkan filter status untuk PO
    $poStatusCondition = "";
    if ($poStatusFilter !== '') {
        $poStatusCondition .= " AND COALESCE(lso.status, 'Pending') = :po_status";
    }
    
    $countQuery = "SELECT COUNT(*) FROM purchaseorder po 
                   LEFT JOIN (
                       SELECT idpurchaseorder, status
                       FROM logstatusorder l1
                       WHERE l1.date = (
                           SELECT MAX(l2.date)
                           FROM logstatusorder l2
                           WHERE l2.idpurchaseorder = l1.idpurchaseorder
                       )
                   ) lso ON po.idpurchaseorder = lso.idpurchaseorder
                   WHERE 1=1 $searchCondition $dateFilter $poStatusCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($poStatusFilter !== '') $countStmt->bindValue(':po_status', $poStatusFilter);
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            po.idpurchaseorder,
            po.tgl_po,
            po.supplier,
            pr.idrequest,
            pr.keterangan,
            COALESCE(lso.status, 'Pending') as status_name,
            lso.keterangan as po_note_reject
        FROM purchaseorder po
        LEFT JOIN purchaserequest pr ON po.idrequest = pr.idrequest
        LEFT JOIN (
            SELECT idpurchaseorder, status, keterangan
            FROM logstatusorder l1
            WHERE l1.date = (
                SELECT MAX(l2.date)
                FROM logstatusorder l2
                WHERE l2.idpurchaseorder = l1.idpurchaseorder
            )
        ) lso ON po.idpurchaseorder = lso.idpurchaseorder
        WHERE 1=1 $searchCondition $dateFilter $poStatusCondition
        ORDER BY $poOrderBy
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($poStatusFilter !== '') $stmt->bindValue(':po_status', $poStatusFilter);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$title = 'Procurement';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement - Internal Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        /* Enhanced Table Styling */
        #purchase-request-table thead th,
        #purchase-order-table thead th {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
            transition: background-color 0.2s ease;
        }
        
        #purchase-request-table thead th a,
        #purchase-order-table thead th a {
            text-decoration: none;
            color: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        #purchase-request-table thead th a:hover,
        #purchase-order-table thead th a:hover {
            color: #2563eb;
        }
        
        #purchase-request-table tbody tr,
        #purchase-order-table tbody tr {
            transition: all 0.15s ease-in-out;
        }
        
        #purchase-request-table tbody tr:hover,
        #purchase-order-table tbody tr:hover {
            background-color: #eff6ff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        #purchase-request-table tbody td,
        #purchase-order-table tbody td {
            vertical-align: middle;
            padding: 1rem 1.5rem;
        }
        
        /* Status Badge Enhancement */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        /* Action Button Styling */
        .action-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Table Container Enhancement */
        .table-container {
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        /* Zebra Striping */
        #purchase-request-table tbody tr:nth-child(even),
        #purchase-order-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        #purchase-request-table tbody tr:nth-child(even):hover,
        #purchase-order-table tbody tr:nth-child(even):hover {
            background-color: #eff6ff;
        }
        
        /* Table styles are handled by Tailwind CSS */
    </style>
</head>
<body class="bg-gray-50">

<div class="flex min-h-screen">
    
    <!-- SIDEBAR -->
    <aside class="w-64 bg-white border-r border-gray-200 fixed h-full flex flex-col">
        <div class="p-6">
            <img src="../images/logo_ipo.png" alt="Logo IPO" class="h-12 w-auto">
        </div>
        
        <nav class="px-4 space-y-1 flex-grow">
            <a href="inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <span class="font-medium">Inventory</span>
            </a>
            
            <a href="procurement.php" class="flex items-center gap-3 px-4 py-3 bg-gray-100 text-gray-900 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="font-medium">Procurement</span>
            </a>
            
            <a href="master-data.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                </svg>
                <span class="font-medium">Master Data</span>
            </a>
        </nav>
        
        <!-- User Info and Logout -->
        <div class="p-4 border-t border-gray-200 mt-auto">
            <div class="mb-3">
                <p class="text-sm text-gray-600">Logged in as:</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></p>
                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Unknown'); ?></p>
            </div>
            <a href="/logout.php" class="block w-full px-4 py-2 text-center text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 ml-64 p-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Procurement</h1>
        
        <!-- Display Success or Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php 
                switch($_GET['success']) {
                    case 'add_success':
                        echo 'Purchase request berhasil ditambahkan.';
                        break;
                    case 'update_success':
                        echo 'Purchase request berhasil diperbarui.';
                        break;
                    case 'delete_success':
                        echo 'Purchase request berhasil dihapus.';
                        break;
                    case 'add_po_success':
                        echo 'Purchase order berhasil ditambahkan.';
                        break;
                    case 'update_po_success':
                        echo 'Purchase order berhasil diperbarui.';
                        break;
                    case 'delete_po_success':
                        echo 'Purchase order berhasil dihapus.';
                        break;
                    default:
                        echo 'Operasi berhasil.';
                }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php 
                switch($_GET['error']) {
                    case 'add_failed':
                        echo 'Gagal menambahkan purchase request.';
                        break;
                    case 'update_failed':
                        echo 'Gagal memperbarui purchase request.';
                        break;
                    case 'delete_failed':
                        echo 'Gagal menghapus purchase request.';
                        if (isset($_GET['message'])) {
                            echo '<br>Detail: ' . htmlspecialchars($_GET['message']);
                        }
                        break;
                    case 'add_po_failed':
                        echo 'Gagal menambahkan purchase order.';
                        if (isset($_GET['message'])) {
                            echo '<br>Detail: ' . htmlspecialchars($_GET['message']);
                        }
                        break;
                    case 'update_po_failed':
                        echo 'Gagal memperbarui purchase order.';
                        if (isset($_GET['message'])) {
                            echo '<br>Detail: ' . htmlspecialchars($_GET['message']);
                        }
                        break;
                    case 'delete_po_failed':
                        echo 'Gagal menghapus purchase order.';
                        if (isset($_GET['message'])) {
                            echo '<br>Detail: ' . htmlspecialchars($_GET['message']);
                        }
                        break;
                    default:
                        echo 'Terjadi kesalahan.';
                }
                if (isset($_GET['message'])) {
                    echo '<br>Pesan: ' . htmlspecialchars($_GET['message']);
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- TABS -->        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button onclick="showTab('overview')" id="tab-overview" class="tab-button border-b-2 border-blue-600 text-blue-600 py-4 px-6 font-medium">
                        Overview
                    </button>
                    <button onclick="showTab('purchase-request')" id="tab-purchase-request" class="tab-button border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 py-4 px-6 font-medium">
                        Purchase Request
                    </button>
                    <button onclick="showTab('purchase-order')" id="tab-purchase-order" class="tab-button border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 py-4 px-6 font-medium">
                        Purchase Order
                    </button>
                </nav>
            </div>

            <!-- TAB CONTENT -->
            <div class="p-6">
                
                <!-- OVERVIEW TAB -->
                <?php if ($activeTab == 'overview'): ?>
                <div>
                    <!-- Toolbar - Simplified without search and buttons -->
                    <div class="flex justify-end items-center mb-6">
                        <div class="flex gap-4 items-center">
                            <div>
                                <select onchange="filterData()" id="filter-month-<?= $activeTab ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-32">
                                    <option value="0" <?= $selectedMonth == 0 ? 'selected' : '' ?>>All Months</option>
                                    <?php for($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $selectedMonth == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <select onchange="filterData()" id="filter-year-<?= $activeTab ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-32">
                                    <option value="0" <?= $selectedYear == 0 ? 'selected' : '' ?>>All Years</option>
                                    <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Cards - Removed the green card -->
                    <div class="grid grid-cols-1 gap-6 mb-8">
                        
                        <!-- Total Amount Purchase Order - Kept this card -->
                        <div class="bg-gradient-to-br from-blue-900 to-blue-700 p-6 rounded-xl text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 opacity-10 text-9xl"><i class="fas fa-shopping-cart"></i></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 class="text-sm font-semibold opacity-90">Total Amount Purchase Order</h3>
                                    <i class="fas fa-shopping-cart text-2xl opacity-80"></i>
                                </div>
                                <div class="text-3xl font-bold mb-2">Rp <?= number_format($totalPO['total_amount'], 0, ',', '.') ?></div>
                            </div>
                        </div>

                    </div>

                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Column Chart - Annual Expenditure -->
                        <div class="bg-white p-4 rounded-lg shadow">
                            <h3 class="text-lg font-semibold mb-4">Annual Expenditure (<?= $selectedYear > 0 ? $selectedYear : date('Y') ?>)</h3>
                            <div style="position: relative; height: 200px">
                                <canvas id="chartAnnualExpenditure"></canvas>
                            </div>
                        </div>
                        
                        <!-- Doughnut Chart - Purchase Request by Status -->
                        <div class="bg-white p-4 rounded-lg shadow">
                            <h3 class="text-lg font-semibold mb-4">Purchase Request by Status</h3>
                            <div style="position: relative; height: 200px">
                                <canvas id="chartRemainPR"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Table -->
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold">Purchase Request Progress</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Request</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requestor</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <?php if (empty($progressPRData)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                                No data available
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($progressPRData as $item): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['idrequest']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars(date('d M Y', strtotime($item['tgl_req']))) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['namarequestor']) ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($item['keterangan']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php 
                                                        switch($item['status_code']) {
                                                            case 1: echo 'bg-yellow-100 text-yellow-800'; break;
                                                            case 2: echo 'bg-blue-100 text-blue-800'; break;
                                                            case 3: echo 'bg-green-100 text-green-800'; break;
                                                            case 4: echo 'bg-orange-100 text-orange-800'; break;
                                                            case 5: echo 'bg-red-100 text-red-800'; break;
                                                            case 6: echo 'bg-green-200 text-green-900'; break;
                                                            default: echo 'bg-gray-100 text-gray-800'; break;
                                                        }
                                                    ?>">
                                                    <?= htmlspecialchars($item['status_name']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $item['progress_percentage'] ?>%"></div>
                                                    </div>
                                                    <span class="text-sm text-gray-500"><?= $item['progress_percentage'] ?>%</span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?= $item['items_arrived'] ?>/<?= $item['total_items'] ?> items arrived
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PURCHASE REQUEST TAB -->
                <?php if ($activeTab == 'purchase-request'): ?>
                <div>
                    <!-- Action Buttons Row -->
                    <div class="flex justify-start items-center mb-4">
                        <div class="flex gap-2">
                            <?php if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Procurement' || $_SESSION['role'] === 'Leader' || $_SESSION['role'] === 'Manager'): ?>
                            <button onclick="showAddForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                <i class="fas fa-plus"></i>
                                Add
                            </button>
                            <button onclick="showEditForm()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Toolbar with Entries, Search, and Filter -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Entries Dropdown -->
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-700 font-medium">Show</label>
                                <select id="entries-pr" onchange="changeEntries('pr')" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <span class="text-sm text-gray-700">entries</span>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Search Input -->
                            <div class="relative">
                                <input type="text" id="search-pr" placeholder="Search..." onkeyup="searchTable('pr')" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                                <button onclick="clearSearch('pr')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <!-- Status Filter -->
                            <select id="status-filter-pr" onchange="filterTableByStatus('pr')" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="Pending">Pending</option>
                                <option value="Process Leader">🟡 Process Leader</option>
                                <option value="Process Manager">🔵 Process Manager</option>
                                <option value="Approved">🟢 Approved</option>
                                <option value="Ordered">🟠 Ordered</option>
                                <option value="Reject">🔴 Reject</option>
                                <option value="Done">✅ Done</option>
                            </select>
                        </div>
                    </div>                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm table-container">
                        <table id="purchase-request-table" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" width="50">#</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-request&pr_sort=idrequest&pr_order=<?= $prSort === 'idrequest' && $prOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'status' => $statusFilter])) ?>" class="hover:text-blue-600">
                                            <span class="font-semibold">ID Request</span>
                                            <?php if ($prSort === 'idrequest'): ?>
                                                <i class="fas fa-sort-<?= $prOrder === 'ASC' ? 'up' : 'down' ?> sort-arrow sort-active"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort sort-arrow text-gray-400"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-request&pr_sort=tgl_req&pr_order=<?= $prSort === 'tgl_req' && $prOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'status' => $statusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Tanggal
                                            <?php if ($prSort === 'tgl_req'): ?>
                                                <i class="fas fa-sort-<?= $prOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-request&pr_sort=namarequestor&pr_order=<?= $prSort === 'namarequestor' && $prOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'status' => $statusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Requestor
                                            <?php if ($prSort === 'namarequestor'): ?>
                                                <i class="fas fa-sort-<?= $prOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-request&pr_sort=total_items&pr_order=<?= $prSort === 'total_items' && $prOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'status' => $statusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Total Items
                                            <?php if ($prSort === 'total_items'): ?>
                                                <i class="fas fa-sort-<?= $prOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-request&pr_sort=total_amount&pr_order=<?= $prSort === 'total_amount' && $prOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'status' => $statusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Total Amount
                                            <?php if ($prSort === 'total_amount'): ?>
                                                <i class="fas fa-sort-<?= $prOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-request&pr_sort=status_code&pr_order=<?= $prSort === 'status_code' && $prOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'status' => $statusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Status
                                            <?php if ($prSort === 'status_code'): ?>
                                                <i class="fas fa-sort-<?= $prOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer" onclick="window.location.href='detail-request.php?idrequest=<?= htmlspecialchars($item['idrequest']) ?>'">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <strong><?= htmlspecialchars($item['idrequest'] ?? '-') ?></strong>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($item['tgl_req'] ?? 'now')) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item['namarequestor'] ?? '-') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            <span class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-800 rounded-lg text-xs font-semibold border border-blue-200">
                                                <i class="fas fa-box mr-1.5"></i><?= $item['total_items'] ?? 0 ?> items
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                            Rp <?= number_format($item['total_amount'] ?? 0, 0, ',', '.') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-sm
                                                <?php 
                                                    switch($item['status_code']) {
                                                        case 0: echo 'bg-gray-200 text-gray-800 border border-gray-300'; break;
                                                        case 1: echo 'bg-yellow-200 text-yellow-900 border border-yellow-300'; break;
                                                        case 2: echo 'bg-blue-200 text-blue-900 border border-blue-300'; break;
                                                        case 3: echo 'bg-green-200 text-green-900 border border-green-300'; break;
                                                        case 4: echo 'bg-orange-200 text-orange-900 border border-orange-300'; break;
                                                        case 5: echo 'bg-red-200 text-red-900 border border-red-300'; break;
                                                        case 6: echo 'bg-emerald-200 text-emerald-900 border border-emerald-300'; break;
                                                        default: echo 'bg-gray-200 text-gray-800 border border-gray-300'; break;
                                                    }
                                                ?>">
                                                <?= htmlspecialchars($item['status_name'] ?? 'Pending') ?>
                                            </span>
                                            <?php if (($item['status_code'] ?? 0) == 5 && !empty($item['note_reject'])): ?>
                                                <div class="mt-1 text-xs text-red-600 max-w-xs truncate" title="Alasan Reject: <?= htmlspecialchars($item['note_reject']) ?>">
                                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars(substr($item['note_reject'], 0, 30)) ?>...
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="detail-request.php?idrequest=<?= htmlspecialchars($item['idrequest']) ?>" 
                                               class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-150 text-xs font-medium shadow-sm">
                                                <i class="fas fa-eye mr-1.5"></i>Detail
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <div class="flex flex-1 justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>&pr_sort=<?= $prSort ?>&pr_order=<?= $prOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($statusFilter !== '') echo '&status=' . $statusFilter; ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>&pr_sort=<?= $prSort ?>&pr_order=<?= $prOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($statusFilter !== '') echo '&status=' . $statusFilter; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to <span class="font-medium"><?= min($offset + $limit, $totalItems) ?></span> of <span class="font-medium"><?= $totalItems ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>&pr_sort=<?= $prSort ?>&pr_order=<?= $prOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($statusFilter !== '') echo '&status=' . $statusFilter; ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>&pr_sort=<?= $prSort ?>&pr_order=<?= $prOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($statusFilter !== '') echo '&status=' . $statusFilter; ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>&pr_sort=<?= $prSort ?>&pr_order=<?= $prOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($statusFilter !== '') echo '&status=' . $statusFilter; ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- PURCHASE ORDER TAB -->
                <?php if ($activeTab == 'purchase-order'): ?>
                <div>
                    <!-- Toolbar with Entries, Search, and Filter -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Entries Dropdown -->
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-700 font-medium">Show</label>
                                <select id="entries-po" onchange="changeEntries('po')" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                                <span class="text-sm text-gray-700">entries</span>
                            </div>
                            
                            <!-- Action Buttons -->
                            <?php if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Procurement'): ?>
                            <button onclick="showAddPOForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                <i class="fas fa-plus"></i>
                                Add
                            </button>
                            <button onclick="showEditPOForm()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Search Input -->
                            <div class="relative">
                                <input type="text" id="search-po" placeholder="Search..." onkeyup="searchTable('po')" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                                <button onclick="clearSearch('po')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <!-- Status Filter -->
                            <select id="status-filter-po" onchange="filterTableByStatus('po')" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="Pending">Pending</option>
                                <option value="Process Order">Process Order</option>
                                <option value="Arrived">Arrived</option>
                            </select>
                        </div>
                    </div>                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm table-container">
                        <table id="purchase-order-table" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('pr', 0)" width="50">#</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-order&po_sort=idpurchaseorder&po_order=<?= $poSort === 'idpurchaseorder' && $poOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'po_status' => $poStatusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            ID Purchase Order
                                            <?php if ($poSort === 'idpurchaseorder'): ?>
                                                <i class="fas fa-sort-<?= $poOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-order&po_sort=tgl_po&po_order=<?= $poSort === 'tgl_po' && $poOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'po_status' => $poStatusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Tanggal PO
                                            <?php if ($poSort === 'tgl_po'): ?>
                                                <i class="fas fa-sort-<?= $poOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-order&po_sort=supplier&po_order=<?= $poSort === 'supplier' && $poOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'po_status' => $poStatusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Supplier
                                            <?php if ($poSort === 'supplier'): ?>
                                                <i class="fas fa-sort-<?= $poOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">ID Request</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Keterangan</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">
                                        <a href="?tab=purchase-order&po_sort=status&po_order=<?= $poSort === 'status' && $poOrder === 'ASC' ? 'DESC' : 'ASC' ?>&<?= http_build_query(array_filter(['year' => $selectedYear > 0 ? $selectedYear : null, 'month' => $selectedMonth > 0 ? $selectedMonth : null, 'search' => $search, 'po_status' => $poStatusFilter])) ?>" class="hover:text-blue-600 inline-flex items-center gap-1 font-semibold">
                                            Status
                                            <?php if ($poSort === 'status'): ?>
                                                <i class="fas fa-sort-<?= $poOrder === 'ASC' ? 'up' : 'down' ?> text-blue-600 ml-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-sort text-gray-400 ml-1"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-blue-50 transition-colors duration-150 selectable-row" data-id="<?= htmlspecialchars($item['idpurchaseorder']) ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><strong><?= htmlspecialchars($item['idpurchaseorder'] ?? '-') ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['tgl_po'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['supplier'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['idrequest'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold shadow-sm
                                                <?php 
                                                    // Determine status color based on status name
                                                    $statusClass = 'bg-gray-200 text-gray-800 border border-gray-300'; // Default
                                                    if ($item['status_name'] == 'Process Order') {
                                                        $statusClass = 'bg-blue-200 text-blue-900 border border-blue-300';
                                                    } elseif ($item['status_name'] == 'Arrived') {
                                                        $statusClass = 'bg-green-200 text-green-900 border border-green-300';
                                                    } elseif ($item['status_name'] == 'Pending') {
                                                        $statusClass = 'bg-yellow-200 text-yellow-900 border border-yellow-300';
                                                    }
                                                    echo $statusClass;
                                                ?>">
                                                <?= htmlspecialchars($item['status_name']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Procurement'): ?>
                                            <a href="detail-order.php?id=<?= urlencode($item['idpurchaseorder']) ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-150 text-xs font-medium shadow-sm mr-2">
                                                <i class="fas fa-eye mr-1.5"></i>Detail
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Procurement'): ?>
                                            <button onclick="confirmDeletePO('<?= urlencode($item['idpurchaseorder']) ?>')" class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-150 text-xs font-medium shadow-sm">
                                                <i class="fas fa-trash mr-1.5"></i>Delete
                                            </button>
                                            <?php endif; ?>                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <div class="flex flex-1 justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>&po_sort=<?= $poSort ?>&po_order=<?= $poOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($poStatusFilter !== '') echo '&po_status=' . urlencode($poStatusFilter); ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>&po_sort=<?= $poSort ?>&po_order=<?= $poOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($poStatusFilter !== '') echo '&po_status=' . urlencode($poStatusFilter); ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to <span class="font-medium"><?= min($offset + $limit, $totalItems) ?></span> of <span class="font-medium"><?= $totalItems ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>&po_sort=<?= $poSort ?>&po_order=<?= $poOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($poStatusFilter !== '') echo '&po_status=' . urlencode($poStatusFilter); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>&po_sort=<?= $poSort ?>&po_order=<?= $poOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($poStatusFilter !== '') echo '&po_status=' . urlencode($poStatusFilter); ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>&po_sort=<?= $poSort ?>&po_order=<?= $poOrder ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?><?php if ($poStatusFilter !== '') echo '&po_status=' . urlencode($poStatusFilter); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </main>

</div>

<script>
// Function to load barang data when ID Barang is selected
function loadBarangData(idbarang) {
    
    if (!idbarang) {
        // Clear fields if no item selected
        document.getElementById('namaitem').value = '';
        document.getElementById('deskripsi').value = '';
        document.getElementById('harga').value = '';
        document.getElementById('kodeproject').value = '';
        document.getElementById('total').value = '';
        return;
    }
    
    // Create FormData object
    const formData = new FormData();
    formData.append('get_barang_data', '1');
    formData.append('idbarang', idbarang);
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data) {
            // Populate fields with returned data
            document.getElementById('namaitem').value = data.nama_barang || '';
            document.getElementById('deskripsi').value = data.deskripsi || '';
            document.getElementById('harga').value = data.harga || '';
            document.getElementById('kodeproject').value = data.kodeproject || '';
            document.getElementById('kodebarang').value = data.kodebarang || '';
            document.getElementById('satuan').value = data.satuan || '';
            
            // Recalculate total
            calculateAddTotal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Function to calculate total
function calculateTotal() {
    const harga = parseFloat(document.getElementById('harga').value) || 0;
    const qty = parseFloat(document.getElementById('qty').value) || 0;
    const total = harga * qty;
    
    document.getElementById('total').value = total.toFixed(2);
}

// Function to calculate total for add form
function calculateAddTotal() {
    const harga = parseFloat(document.getElementById('harga').value) || 0;
    const qty = parseFloat(document.getElementById('qty').value) || 0;
    const total = harga * qty;
    
    document.getElementById('total').value = total.toFixed(2);
}

// Tab switching functionality
function showTab(tabName) {
    // Build URL with tab parameter
    let url = `?tab=${tabName}`;
    
    // Get current filter parameters if they exist
    const urlParams = new URLSearchParams(window.location.search);
    const month = urlParams.get('month');
    const year = urlParams.get('year');
    const search = urlParams.get('search');
    
    // Add filter parameters if they exist
    if (month) url += `&month=${encodeURIComponent(month)}`;
    if (year) url += `&year=${encodeURIComponent(year)}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    
    // Navigate to the new URL
    window.location.href = url;
}

// Filter functionality
function filterData() {
    // Get current tab from URL or default to 'overview'
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'overview';
    
    // Get filter elements for current tab
    const monthSelect = document.getElementById(`filter-month-${currentTab}`);
    const yearSelect = document.getElementById(`filter-year-${currentTab}`);
    
    // Get selected values
    const month = monthSelect ? monthSelect.value : '0';
    const year = yearSelect ? yearSelect.value : '0';
    
    // Build URL with parameters
    let url = `?tab=${currentTab}`;
    
    // Add month parameter if it's not '0' (All Months)
    if (month && month !== '0' && month !== 'All Months') {
        url += `&month=${encodeURIComponent(month)}`;
    }
    
    // Add year parameter if it's not '0' (All Years)
    if (year && year !== '0' && year !== 'All Years') {
        url += `&year=${encodeURIComponent(year)}`;
    }
    
    // Redirect to new URL
    window.location.href = url;
}

// Change entries per page
function changePerPage(value) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'purchase-request';
    
    // Build URL with per_page parameter
    let url = `?tab=${currentTab}&per_page=${value}&page=1`;
    
    // Preserve other filters
    if (urlParams.get('year')) url += `&year=${urlParams.get('year')}`;
    if (urlParams.get('month')) url += `&month=${urlParams.get('month')}`;
    if (urlParams.get('search')) url += `&search=${urlParams.get('search')}`;
    if (urlParams.get('status')) url += `&status=${urlParams.get('status')}`;
    if (urlParams.get('po_status')) url += `&po_status=${urlParams.get('po_status')}`;
    if (urlParams.get('pr_sort')) url += `&pr_sort=${urlParams.get('pr_sort')}`;
    if (urlParams.get('pr_order')) url += `&pr_order=${urlParams.get('pr_order')}`;
    if (urlParams.get('po_sort')) url += `&po_sort=${urlParams.get('po_sort')}`;
    if (urlParams.get('po_order')) url += `&po_order=${urlParams.get('po_order')}`;
    
    window.location.href = url;
}

// Download report function
function downloadReport() {
    // Get current tab
    const activeTab = document.querySelector('.tab-button.border-blue-600').id.replace('tab-', '');
    
    // Get filter values
    const monthSelect = document.getElementById('filter-month-' + activeTab);
    const yearSelect = document.getElementById('filter-year-' + activeTab);
    const searchInput = document.querySelector('input[name="search"]');
    
    // Get selected values
    const month = monthSelect ? monthSelect.value : '0';
    const year = yearSelect ? yearSelect.value : '0';
    const search = searchInput ? searchInput.value : '';
    
    // Build URL for export
    let url = `export_procurement.php?tab=${activeTab}`;
    
    // Add parameters
    if (month && month !== '0' && month !== 'All Months') {
        url += `&month=${encodeURIComponent(month)}`;
    }
    
    if (year && year !== '0' && year !== 'All Years') {
        url += `&year=${encodeURIComponent(year)}`;
    }
    
    if (search) {
        url += `&search=${encodeURIComponent(search)}`;
    }
    
    // Redirect to export URL
    window.location.href = url;
}

// Chart for Annual Expenditure
<?php if (!empty($annualExpenditureData)): ?>
const ctxAnnual = document.getElementById('chartAnnualExpenditure');
if (ctxAnnual) {
    new Chart(ctxAnnual, {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Expenditure (Rp)',
                data: <?= json_encode($monthlyExpenditure) ?>,
                backgroundColor: '#3b82f6',
                borderColor: '#2563eb',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Chart for Remain Purchase Request
<?php if (!empty($prStatusData)): ?>
const ctxPR = document.getElementById('chartRemainPR');
if (ctxPR) {
    new Chart(ctxPR, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                data: <?= json_encode($chartData) ?>,
                backgroundColor: <?= json_encode($chartBackgroundColors) ?>,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + ' PR';
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

<style>
/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 95%;
    max-width: 1200px;
    border-radius: 8px;
    max-height: 90vh;
    overflow-y: auto;
}
.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
}

/* Selected row highlighting */
.selectable-row.selected {
    background-color: #dbeafe !important; /* Light blue background */
    border-left: 4px solid #3b82f6; /* Blue left border */
}
</style>

<script>
// Modal functions
function openAddRequestModal() {
    // Set current datetime as default
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    const hours = String(now.getHours()).padStart(2, "0");
    const minutes = String(now.getMinutes()).padStart(2, "0");
    const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    
    document.getElementById("tgl_req").value = formattedDateTime;
    document.getElementById("addRequestModal").style.display = "block";
    
    // Add event listener for qty change to calculate total
    setTimeout(() => {
        const qtyInput = document.getElementById("qty");
        if (qtyInput) {
            qtyInput.addEventListener('input', calculateAddTotal);
            qtyInput.addEventListener('change', calculateAddTotal);
        }
        // Calculate initial total
        calculateAddTotal();
    }, 300);
}

function openAddOrderModal() {
    // Set current date as default
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    const formattedDate = `${year}-${month}-${day}`;
    
    document.getElementById("tgl_po").value = formattedDate;
    document.getElementById("addOrderModal").style.display = "block";
    
    // Add event listener for qty change to calculate total
    setTimeout(() => {
        const qtyInputs = document.querySelectorAll(".qty-input");
        qtyInputs.forEach(input => {
            input.addEventListener('input', calculateOrderTotal);
            input.addEventListener('change', calculateOrderTotal);
        });
        // Calculate initial total
        calculateOrderTotal();
    }, 300);
}

// Calculate total for add form
function calculateAddTotal() {
    const harga = parseFloat(document.getElementById("harga").value) || 0;
    const qty = parseFloat(document.getElementById("qty").value) || 0;
    const total = harga * qty;
    
    document.getElementById("total").value = total.toFixed(2);
}

// Calculate total for order form
function calculateOrderTotal() {
    const rows = document.querySelectorAll(".order-detail-row");
    rows.forEach(row => {
        const harga = parseFloat(row.querySelector(".harga-input").value) || 0;
        const qty = parseFloat(row.querySelector(".qty-input").value) || 0;
        const total = harga * qty;
        row.querySelector(".total-input").value = total.toFixed(2);
    });
}

function openEditRequestModal(requestData) {
    // Populate the form with request data
    document.getElementById("edit_idrequest_display").value = requestData.idrequest || "";
    document.getElementById("edit_idrequest_hidden").value = requestData.idrequest || "";
    
    document.getElementById("edit_namarequestor_display").value = requestData.namarequestor || "";
    document.getElementById("edit_namarequestor_hidden").value = requestData.namarequestor || "";
    
    // Format datetime for display
    let tglReqDisplay = "";
    if (requestData.tgl_req) {
        // Convert MySQL datetime to readable format
        const dateObj = new Date(requestData.tgl_req);
        tglReqDisplay = dateObj.toLocaleString('id-ID');
    }
    
    document.getElementById("edit_tgl_req_display").value = tglReqDisplay;
    document.getElementById("edit_tgl_req_hidden").value = requestData.tgl_req || "";
    
    document.getElementById("edit_keterangan").value = requestData.keterangan || "";
    document.getElementById("edit_tgl_butuh").value = requestData.tgl_butuh || "";
    document.getElementById("edit_idsupervisor").value = requestData.idsupervisor || "";
    
    // Populate detail request fields (read-only)
    document.getElementById("edit_idbarang").value = requestData.idbarang || "";
    
    document.getElementById("edit_kodebarang").value = requestData.kodebarang || "";
    
    document.getElementById("edit_satuan").value = requestData.satuan || "";
    
    document.getElementById("edit_linkpembelian").value = requestData.linkpembelian || "";
    
    document.getElementById("edit_namaitem").value = requestData.namaitem || "";
    
    document.getElementById("edit_deskripsi").value = requestData.deskripsi || "";
    
    document.getElementById("edit_harga").value = requestData.harga || "";
    
    document.getElementById("edit_qty").value = requestData.qty || "";
    
    document.getElementById("edit_total").value = requestData.total || "";
    
    document.getElementById("edit_kodeproject").value = requestData.kodeproject || "";    
    // Add event listener for qty change to calculate total
    document.getElementById("edit_qty").addEventListener('input', calculateEditTotal);
    
    // Calculate initial total
    calculateEditTotal();
    
    document.getElementById("editRequestModal").style.display = "block";
}

// Calculate total for edit form
function calculateEditTotal() {
    const harga = parseFloat(document.getElementById("edit_harga").value) || 0;
    const qty = parseFloat(document.getElementById("edit_qty").value) || 0;
    const total = harga * qty;
    
    document.getElementById("edit_total").value = total.toFixed(2);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = "none";
    }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    if (event.target.classList.contains("modal")) {
        event.target.style.display = "none";
    }
}

// Confirm delete function
function confirmDelete(idrequest) {
    if (confirm('Apakah Anda yakin ingin menghapus purchase request ini? Tindakan ini tidak dapat dibatalkan.')) {
        // Validate the ID
        if (!idrequest || idrequest.trim() === '') {
            alert('Invalid request ID');
            return;
        }
        
        // Create hidden form for deletion
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_purchase_request';
        input.value = '1';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'idrequest';
        idInput.value = idrequest;
        
        form.appendChild(input);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Confirm delete PO function
function confirmDeletePO(idpurchaseorder) {
    if (confirm('Apakah Anda yakin ingin menghapus purchase order ini? Tindakan ini tidak dapat dibatalkan.')) {
        // Validate the ID
        if (!idpurchaseorder || idpurchaseorder.trim() === '') {
            alert('Invalid purchase order ID');
            return;
        }
        
        // Create hidden form for deletion
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_purchase_order';
        input.value = '1';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'idpurchaseorder';
        idInput.value = idpurchaseorder;
        
        form.appendChild(input);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
// Add event listeners to the buttons
document.addEventListener("DOMContentLoaded", function() {
    // Add button in purchase request tab
    const addPrButton = document.getElementById("add-pr-button");
    if (addPrButton) {
        addPrButton.addEventListener("click", openAddRequestModal);
    }
    
    // Edit button in purchase request tab
    const editPrButton = document.getElementById("edit-pr-button");
    if (editPrButton) {
        editPrButton.addEventListener("click", function() {
            const selectedRow = document.querySelector('.selectable-row.selected');
            if (!selectedRow) {
                alert("Please select a purchase request to edit from the table below.");
                return;
            }
            
            // Get data from the selected row
            const requestData = {
                idrequest: selectedRow.dataset.id,
                namarequestor: selectedRow.dataset.nama,
                keterangan: selectedRow.dataset.ket,
                tgl_req: selectedRow.dataset.tgl,
                tgl_butuh: selectedRow.dataset.butuh,
                idsupervisor: selectedRow.dataset.supervisor,
                idbarang: selectedRow.dataset.idbarang,
                linkpembelian: selectedRow.dataset.link,
                namaitem: selectedRow.dataset.item,
                deskripsi: selectedRow.dataset.desc,
                harga: selectedRow.dataset.harga,
                qty: selectedRow.dataset.qty,
                total: selectedRow.dataset.total,
                kodeproject: selectedRow.dataset.project,
                kodebarang: selectedRow.dataset.kodebarang,
                satuan: selectedRow.dataset.satuan
            };
            
            openEditRequestModal(requestData);
        });
    }
    
    // Add button in purchase order tab
    const addPoButton = document.getElementById("add-po-button");
    if (addPoButton) {
        addPoButton.addEventListener("click", openAddOrderModal);
    }
    
    // Edit button in purchase order tab
    const editPoButton = document.getElementById("edit-po-button");
    if (editPoButton) {
        editPoButton.addEventListener("click", function() {
            const selectedRow = document.querySelector('#purchase-order-table .selectable-row.selected');
            if (!selectedRow) {
                alert("Please select a purchase order to edit from the table below.");
                return;
            }
            
            // Get the ID of the selected purchase order
            const idpurchaseorder = selectedRow.dataset.id;
            
            // Load the purchase order data for editing
            loadPurchaseOrderForEdit(idpurchaseorder);
        });
    }
    
    // Add click event to table rows for selection
    const selectableRows = document.querySelectorAll('.selectable-row');
    selectableRows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't select row if clicking on the detail link
            if (e.target.tagName === 'A' && e.target.href) {
                return;
            }
            
            // Remove selection from all rows
            selectableRows.forEach(r => r.classList.remove('selected'));
            
            // Add selection to clicked row
            this.classList.add('selected');
        });
    });
});

// Show add PO form function
function showAddPOForm() {
    // Check user role - Admin, Procurement, Leader, and Manager can add PO
    var userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
    if (userRole !== 'Admin' && userRole !== 'Procurement' && userRole !== 'Leader' && userRole !== 'Manager') {
        alert('You do not have permission to add purchase orders.');
        return;
    }
    document.getElementById('addOrderModal').style.display = 'block';
}

// Show edit PO form function
function showEditPOForm() {
    // Check user role - Admin, Procurement, Leader, and Manager can edit PO
    var userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
    if (userRole !== 'Admin' && userRole !== 'Procurement' && userRole !== 'Leader' && userRole !== 'Manager') {
        alert('You do not have permission to edit purchase orders.');
        return;
    }
    
    // Check if a row is selected
    const selectedRow = document.querySelector('.selected-row');
    if (!selectedRow) {
        alert('Please select a row to edit first');
        return;
    }
    
    // Get data from the selected row
    const idPurchaseOrder = selectedRow.getAttribute('data-id');
    
    // Load purchase order data for editing
    loadPurchaseOrderForEdit(idPurchaseOrder);
}

// Handle search on Enter key
function handleSearch(event) {
    if (event.key === 'Enter') {
        performSearch();
    }
}

// Perform search function
function performSearch() {
    // Get current tab from URL or default
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'overview';
    
    // Get search input
    const searchInput = document.getElementById(`search-${currentTab}`);
    const searchTerm = searchInput ? searchInput.value : '';
    
    // Get current filters
    const monthSelect = document.getElementById(`filter-month-${currentTab}`);
    const yearSelect = document.getElementById(`filter-year-${currentTab}`);
    const month = monthSelect ? monthSelect.value : '0';
    const year = yearSelect ? yearSelect.value : '0';
    
    // Build URL with parameters
    let url = `?tab=${currentTab}`;
    
    // Add search parameter if present
    if (searchTerm) {
        url += `&search=${encodeURIComponent(searchTerm)}`;
    }
    
    // Add month parameter if it's not '0' (All Months)
    if (month && month !== '0' && month !== 'All Months') {
        url += `&month=${encodeURIComponent(month)}`;
    }
    
    // Add year parameter if it's not '0' (All Years)
    if (year && year !== '0' && year !== 'All Years') {
        url += `&year=${encodeURIComponent(year)}`;
    }
    
    // Navigate to the new URL
    window.location.href = url;
}

// Show add form function
function showAddForm() {
    document.getElementById('addRequestModal').style.display = 'block';
}

// Show edit form function
function showEditForm() {
    // Check if a row is selected
    const selectedRow = document.querySelector('.selected-row');
    if (!selectedRow) {
        alert('Please select a row to edit first');
        return;
    }
    
    // Get data from the selected row
    const idRequest = selectedRow.getAttribute('data-id');
    const namaRequestor = selectedRow.getAttribute('data-nama');
    const keterangan = selectedRow.getAttribute('data-ket');
    const tglReq = selectedRow.getAttribute('data-tgl');
    const tglButuh = selectedRow.getAttribute('data-butuh');
    const idSupervisor = selectedRow.getAttribute('data-supervisor');
    const idBarang = selectedRow.getAttribute('data-idbarang');
    const linkPembelian = selectedRow.getAttribute('data-link');
    const namaItem = selectedRow.getAttribute('data-item');
    const deskripsi = selectedRow.getAttribute('data-desc');
    const harga = selectedRow.getAttribute('data-harga');
    const qty = selectedRow.getAttribute('data-qty');
    const total = selectedRow.getAttribute('data-total');
    const kodeProject = selectedRow.getAttribute('data-project');
    const kodeBarang = selectedRow.getAttribute('data-kodebarang');
    const satuan = selectedRow.getAttribute('data-satuan');
    
    // Populate the edit form with data
    document.getElementById('edit_idrequest_display').value = idRequest;
    document.getElementById('edit_idrequest_hidden').value = idRequest;
    document.getElementById('edit_tgl_req_display').value = tglReq;
    document.getElementById('edit_tgl_req_hidden').value = tglReq;
    document.getElementById('edit_namarequestor_display').value = namaRequestor;
    document.getElementById('edit_namarequestor_hidden').value = namaRequestor;
    document.getElementById('edit_keterangan').value = keterangan;
    document.getElementById('edit_tgl_butuh').value = tglButuh;
    document.getElementById('edit_idsupervisor').value = idSupervisor;
    document.getElementById('edit_idbarang').value = idBarang;
    document.getElementById('edit_linkpembelian').value = linkPembelian;
    document.getElementById('edit_namaitem').value = namaItem;
    document.getElementById('edit_deskripsi').value = deskripsi;
    document.getElementById('edit_harga').value = harga;
    document.getElementById('edit_qty').value = qty;
    document.getElementById('edit_total').value = total;
    document.getElementById('edit_kodeproject').value = kodeProject;
    document.getElementById('edit_kodebarang').value = kodeBarang;
    document.getElementById('edit_satuan').value = satuan;
    
    // Show the edit modal
    document.getElementById('editRequestModal').style.display = 'block';
}

// Show add PO form function
function showAddPOForm() {
    // Check user role - Admin, Procurement, Leader, and Manager can add PO
    var userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
    if (userRole !== 'Admin' && userRole !== 'Procurement' && userRole !== 'Leader' && userRole !== 'Manager') {
        alert('You do not have permission to add purchase orders.');
        return;
    }
    document.getElementById('addOrderModal').style.display = 'block';
}

// Show edit PO form function
function showEditPOForm() {
    // Check user role - Admin, Procurement, Leader, and Manager can edit PO
    var userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
    if (userRole !== 'Admin' && userRole !== 'Procurement' && userRole !== 'Leader' && userRole !== 'Manager') {
        alert('You do not have permission to edit purchase orders.');
        return;
    }
    
    // Check if a row is selected
    const selectedRow = document.querySelector('.selected-row');
    if (!selectedRow) {
        alert('Please select a row to edit first');
        return;
    }
    
    // Get data from the selected row
    const idPurchaseOrder = selectedRow.getAttribute('data-id');
    
    // Load purchase order data for editing
    loadPurchaseOrderForEdit(idPurchaseOrder);
}
// Function to handle row selection
function selectRow(element) {
    // Remove selection from all rows
    const rows = document.querySelectorAll('.selectable-row');
    rows.forEach(row => {
        row.classList.remove('selected-row');
        row.style.backgroundColor = '';
    });
    
    // Add selection to clicked row
    element.classList.add('selected-row');
    element.style.backgroundColor = '#e5f3ff'; // Light blue highlight
}

// Function to close modals
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    // Also remove row selection when closing modal
    const selectedRow = document.querySelector('.selected-row');
    if (selectedRow) {
        selectedRow.classList.remove('selected-row');
        selectedRow.style.backgroundColor = '';
    }
}

// Function to confirm deletion
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this record?')) {
        // Create a form dynamically and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_purchase_request';
        input.value = '1';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'idrequest';
        idInput.value = id;
        
        form.appendChild(input);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Function to confirm PO deletion
function confirmDeletePO(id) {
    if (confirm('Are you sure you want to delete this purchase order?')) {
        // Create a form dynamically and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_purchase_order';
        input.value = '1';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'idpurchaseorder';
        idInput.value = id;
        
        form.appendChild(input);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Add event listeners to rows when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.selectable-row');
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't select row if clicking on a button or link
            if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
                return;
            }
            selectRow(this);
        });
    });
});

// Function to calculate total for add form
function calculateAddTotal() {
    const harga = parseFloat(document.getElementById('harga').value) || 0;
    const qty = parseFloat(document.getElementById('qty').value) || 0;
    const total = harga * qty;
    document.getElementById('total').value = total.toFixed(2);
}

// Function to calculate total for edit form
function calculateEditTotal() {
    const harga = parseFloat(document.getElementById('edit_harga').value) || 0;
    const qty = parseFloat(document.getElementById('edit_qty').value) || 0;
    const total = harga * qty;
    document.getElementById('edit_total').value = total.toFixed(2);
}

// Function to load barang data when ID Barang is selected
function loadBarangData(idbarang) {
    if (!idbarang) {
        // Clear fields if no item selected
        document.getElementById('namaitem').value = '';
        document.getElementById('deskripsi').value = '';
        document.getElementById('harga').value = '';
        document.getElementById('kodeproject').value = '';
        document.getElementById('kodebarang').value = '';
        document.getElementById('satuan').value = '';
        document.getElementById('total').value = '';
        return;
    }
    
    // Create FormData object
    const formData = new FormData();
    formData.append('get_barang_data', '1');
    formData.append('idbarang', idbarang);
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data) {
            // Populate fields with returned data
            document.getElementById('namaitem').value = data.nama_barang || '';
            document.getElementById('deskripsi').value = data.deskripsi || '';
            document.getElementById('harga').value = data.harga || '';
            document.getElementById('kodeproject').value = data.kodeproject || '';
            document.getElementById('kodebarang').value = data.kodebarang || '';
            document.getElementById('satuan').value = data.satuan || '';
            
            // Recalculate total
            calculateAddTotal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

</body>
</html>

<!-- Add Purchase Request Modal -->
<div id="addRequestModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-8 border w-11/12 max-w-6xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Add New Purchase Request</h2>
            <button onclick="closeModal('addRequestModal')" class="text-gray-600 hover:text-gray-800 text-3xl font-bold">&times;</button>
        </div>
        
        <form id="addRequestForm" method="POST" action="">
            <input type="hidden" name="add_purchase_request" value="1">
            
            <!-- Main Grid Container -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Left Column - Request Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Request Information</h3>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="namarequestor">
                            Requestor Name <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="namarequestor" name="namarequestor" type="text" required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="keterangan">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  id="keterangan" name="keterangan" rows="3" required></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="tgl_req">
                            Request Date <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="tgl_req" name="tgl_req" type="datetime-local" required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="tgl_butuh">
                            Required Date <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="tgl_butuh" name="tgl_butuh" type="date" required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="idsupervisor">
                            Supervisor <span class="text-red-500">*</span>
                        </label>
                        <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                id="idsupervisor" name="idsupervisor" required>
                            <option value="">Select Supervisor</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= htmlspecialchars($user['iduser']) ?>">
                                <?= htmlspecialchars($user['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Right Column - Detail Request Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Detail Request Information</h3>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="idbarang">
                            ID Barang <span class="text-red-500">*</span>
                        </label>
                        <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                id="idbarang" name="idbarang" onchange="loadBarangData(this.value)" required>
                            <option value="">Select Item</option>
                            <?php foreach ($itemsList as $item): ?>
                            <option value="<?= htmlspecialchars($item['idbarang']) ?>">
                                <?= htmlspecialchars($item['kodebarang']) ?> - <?= htmlspecialchars($item['nama_barang']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    

                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="kodebarang">
                            Kode Barang
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="kodebarang" name="kodebarang" type="text">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="satuan">
                            Satuan
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="satuan" name="satuan" type="text">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="linkpembelian">
                            Link Pembelian
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="linkpembelian" name="linkpembelian" type="url" placeholder="https://store.example/product">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="namaitem">
                            Nama Item <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight focus:outline-none" 
                               id="namaitem" name="namaitem" type="text" readonly required>
                        <p class="text-xs text-gray-500 mt-1">Auto-filled after selecting ID Barang</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="deskripsi">
                            Deskripsi
                        </label>
                        <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  id="deskripsi" name="deskripsi" rows="3"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="harga">
                                Harga <span class="text-red-500">*</span>
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight focus:outline-none" 
                                   id="harga" name="harga" type="number" step="0.01" readonly required>
                            <p class="text-xs text-gray-500 mt-1">Auto-filled</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="qty">
                                Qty <span class="text-red-500">*</span>
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   id="qty" name="qty" type="number" min="1" onchange="calculateAddTotal()" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="total">
                            Total
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 font-bold leading-tight focus:outline-none" 
                               id="total" name="total" type="text" readonly>
                        <p class="text-xs text-gray-500 mt-1">Calculated automatically (Harga × Qty)</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="kodeproject">
                            Kode Project <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight focus:outline-none" 
                               id="kodeproject" name="kodeproject" type="text" readonly required>
                        <p class="text-xs text-gray-500 mt-1">Auto-filled after selecting ID Barang</p>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-4 mt-8 pt-6 border-t">
                <button type="button" 
                        onclick="closeModal('addRequestModal')" 
                        class="px-6 py-2 bg-gray-500 text-white font-bold rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Create Request
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Purchase Request Modal -->
<div id="editRequestModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editRequestModal')">&times;</span>
        <h2 class="text-xl font-bold mb-4">Edit Purchase Request</h2>
        <form method="POST">
            <input type="hidden" name="edit_purchase_request" value="1">
            <input type="hidden" name="idrequest" id="edit_idrequest">
            <!-- Main Grid Container -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Left Column -->
                <div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_idrequest_display">
                            ID Request
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                               id="edit_idrequest_display" type="text" readonly>
                        <input type="hidden" name="idrequest" id="edit_idrequest_hidden">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_tgl_req_display">
                            Request Date
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                               id="edit_tgl_req_display" type="text" readonly>
                        <input type="hidden" name="tgl_req" id="edit_tgl_req_hidden">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_namarequestor_display">
                            Requestor Name
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                               id="edit_namarequestor_display" type="text" readonly>
                        <input type="hidden" name="namarequestor" id="edit_namarequestor_hidden">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_keterangan">
                            Description
                        </label>
                        <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                  id="edit_keterangan" name="keterangan" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_tgl_butuh">
                            Required Date
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="edit_tgl_butuh" name="tgl_butuh" type="date" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_idsupervisor">
                            Supervisor
                        </label>
                        <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                id="edit_idsupervisor" name="idsupervisor" required>
                            <option value="">Select Supervisor</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= htmlspecialchars($user['iduser']) ?>">
                                <?= htmlspecialchars($user['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Right Column - Detail Request Fields -->
                <div>
                    <h3 class="text-lg font-bold mb-4 text-gray-800">Detail Request Information</h3>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_idbarang">
                            ID Barang
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                               id="edit_idbarang" name="idbarang" type="text" readonly>
                    </div>
                    

                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_kodebarang">
                            Kode Barang
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="edit_kodebarang" name="kodebarang" type="text">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_satuan">
                            Satuan
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="edit_satuan" name="satuan" type="text">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_linkpembelian">
                            Link Pembelian
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="edit_linkpembelian" name="linkpembelian" type="text">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_namaitem">
                            Nama Item
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                               id="edit_namaitem" name="namaitem" type="text" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_deskripsi">
                            Deskripsi
                        </label>
                        <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                  id="edit_deskripsi" name="deskripsi" rows="3"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_harga">
                                Harga
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                                   id="edit_harga" name="harga" type="number" step="0.01" readonly>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_qty">
                                Qty
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                   id="edit_qty" name="qty" type="number" onchange="calculateEditTotal()">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_total">
                                Total
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                                   id="edit_total" name="total" type="number" step="0.01" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_kodeproject">
                            Kode Project
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                               id="edit_kodeproject" name="kodeproject" type="text" readonly>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <button type="button" onclick="closeModal('editRequestModal')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Update Request
                </button>
            </div>
        </form>
    </div>
</div></body>
</html>

<!-- Add Purchase Order Modal -->
<div id="addOrderModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-8 border w-11/12 max-w-6xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Add New Purchase Order</h2>
            <button onclick="closeModal('addOrderModal')" class="text-gray-600 hover:text-gray-800 text-3xl font-bold">&times;</button>
        </div>
        
        <form id="addOrderForm" method="POST" action="">
            <input type="hidden" name="add_purchase_order" value="1">
            
            <!-- Main Grid Container -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Left Column - Order Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Order Information</h3>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="idrequest">
                            ID Request <span class="text-red-500">*</span>
                        </label>
                        <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                id="idrequest" name="idrequest" onchange="loadRequestDetails(this.value)" required>
                            <option value="">Select Request</option>
                            <?php 
                            // Fetch only approved purchase requests (status = 3) that are not rejected
                            $stmt = $pdo->prepare("
                                SELECT pr.idrequest, pr.namarequestor, u.nama as nama_requestor
                                FROM purchaserequest pr
                                LEFT JOIN users u ON pr.namarequestor = u.iduser
                                WHERE pr.status = 3 
                                AND pr.idrequest IN (SELECT DISTINCT idrequest FROM detailrequest)
                                AND pr.idrequest NOT IN (
                                    SELECT idrequest FROM purchaserequest WHERE status = 5
                                )
                                ORDER BY pr.tgl_req DESC
                            ");
                            $stmt->execute();
                            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($requests as $request): 
                            ?>
                            <option value="<?= htmlspecialchars($request['idrequest']) ?>">
                                <?= htmlspecialchars($request['idrequest']) ?> - <?= htmlspecialchars($request['nama_requestor'] ?: $request['namarequestor']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="supplier">
                            Supplier <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="supplier" name="supplier" type="text" required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="tgl_po">
                            PO Date <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="tgl_po" name="tgl_po" type="date" required>
                    </div>
                </div>

                <!-- Right Column - Detail Order Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Detail Order Information</h3>
                    
                    <div id="order-details-container">
                        <!-- Dynamic order details will be added here -->
                        <div class="order-detail-row mb-4 p-4 border rounded">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="idbarang_0">
                                        ID Barang <span class="text-red-500">*</span>
                                    </label>
                                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 idbarang-select" 
                                            id="idbarang_0" name="idbarang[]" onchange="loadBarangDataForOrder(this.value, 0)" required>
                                        <option value="">Select Item</option>
                                        <?php foreach ($itemsList as $item): ?>
                                        <option value="<?= htmlspecialchars($item['idbarang']) ?>">
                                            <?= htmlspecialchars($item['kodebarang']) ?> - <?= htmlspecialchars($item['nama_barang']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="qty_0">
                                        Qty <span class="text-red-500">*</span>
                                    </label>
                                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 qty-input" 
                                           id="qty_0" name="qty[]" type="number" min="1" onchange="calculateOrderTotal()" required>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="harga_0">
                                        Harga
                                    </label>
                                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 harga-input" 
                                           id="harga_0" name="harga[]" type="number" step="0.01" readonly>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="total_0">
                                        Total
                                    </label>
                                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 font-bold leading-tight focus:outline-none total-input" 
                                           id="total_0" name="total[]" type="text" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="add-detail-row" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium flex items-center gap-2">
                        <i class="fas fa-plus"></i> Add More Items
                    </button>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-4 mt-8 pt-6 border-t">
                <button type="button" 
                        onclick="closeModal('addOrderModal')" 
                        class="px-6 py-2 bg-gray-500 text-white font-bold rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Create Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Purchase Order Modal -->
<div id="editOrderModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-8 border w-11/12 max-w-6xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Edit Purchase Order</h2>
            <button onclick="closeModal('editOrderModal')" class="text-gray-600 hover:text-gray-800 text-3xl font-bold">&times;</button>
        </div>
        
        <form id="editOrderForm" method="POST" action="">
            <input type="hidden" name="edit_purchase_order" value="1">
            <input type="hidden" name="idpurchaseorder" id="edit_idpurchaseorder_hidden">
            
            <!-- Main Grid Container -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Left Column - Order Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Order Information</h3>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_idpurchaseorder_display">
                            ID Purchase Order
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-100" 
                               id="edit_idpurchaseorder_display" type="text" readonly>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_idrequest">
                            ID Request
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="edit_idrequest" name="idrequest" type="text" readonly placeholder="ID Request akan ditampilkan di sini">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_supplier">
                            Supplier <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="edit_supplier" name="supplier" type="text" required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_tgl_po">
                            PO Date <span class="text-red-500">*</span>
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               id="edit_tgl_po" name="tgl_po" type="date" required>
                    </div>
                </div>

                <!-- Right Column - Detail Order Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Detail Order Information</h3>
                    
                    <div id="edit-order-details-container">
                        <!-- Dynamic order details will be added here -->
                    </div>
                    
                    <button type="button" id="add-edit-detail-row" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium flex items-center gap-2">
                        <i class="fas fa-plus"></i> Add More Items
                    </button>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-4 mt-8 pt-6 border-t">
                <button type="button" 
                        onclick="closeModal('editOrderModal')" 
                        class="px-6 py-2 bg-gray-500 text-white font-bold rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Update Order
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Add event listener for adding new detail rows in edit modal
document.addEventListener('DOMContentLoaded', function() {
    const addButton = document.getElementById('add-edit-detail-row');
    if (addButton) {
        addButton.addEventListener('click', function() {
            const container = document.getElementById('edit-order-details-container');
            const rowCount = container.querySelectorAll('.order-detail-row').length;
            addEditOrderRow(null, rowCount);
        });
    }
});
// Function to open the edit order modal
function openEditOrderModal(data) {
    // Populate the form with order data
    document.getElementById("edit_idpurchaseorder_display").value = data.order.idpurchaseorder || "";
    document.getElementById("edit_idpurchaseorder_hidden").value = data.order.idpurchaseorder || "";
    document.getElementById("edit_idrequest").value = data.order.idrequest || "";
    
    // Ensure the ID Request field is visibly populated
    const idRequestField = document.getElementById("edit_idrequest");
    if (data.order.idrequest) {
        idRequestField.value = data.order.idrequest;
        idRequestField.classList.remove('text-gray-400');
    } else {
        idRequestField.value = "Tidak ada ID Request";
        idRequestField.classList.add('text-gray-400');
    }
    
    document.getElementById("edit_supplier").value = data.order.supplier || "";
    document.getElementById("edit_tgl_po").value = data.order.tgl_po || "";
    
    // Clear existing detail rows
    const container = document.getElementById('edit-order-details-container');
    container.innerHTML = '';
    
    // Add detail rows
    if (data.details && data.details.length > 0) {
        data.details.forEach((detail, index) => {
            addEditOrderRow(detail, index);
        });
    } else {
        // Add at least one empty row
        addEditOrderRow(null, 0);
    }
    
    // Show the modal
    document.getElementById("editOrderModal").style.display = "block";
}

// Function to add a row to the edit order form
function addEditOrderRow(detail, index) {
    const container = document.getElementById('edit-order-details-container');
    
    const newRow = document.createElement('div');
    newRow.className = 'order-detail-row mb-4 p-4 border rounded';
    newRow.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_idbarang_${index}">
                    ID Barang <span class="text-red-500">*</span>
                </label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 idbarang-select" 
                        id="edit_idbarang_${index}" name="idbarang[]" required>
                    <option value="">Select Item</option>
                    <?php foreach ($itemsList as $item): ?>
                    <option value="<?= htmlspecialchars($item['idbarang']) ?>">
                        <?= htmlspecialchars($item['kodebarang']) ?> - <?= htmlspecialchars($item['nama_barang']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_qty_${index}">
                    Qty <span class="text-red-500">*</span>
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 qty-input" 
                       id="edit_qty_${index}" name="qty[]" type="number" min="1" onchange="calculateEditOrderTotal()" required>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_harga_${index}">
                    Harga
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 harga-input" 
                       id="edit_harga_${index}" name="harga[]" type="number" step="0.01">
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_total_${index}">
                    Total
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 font-bold leading-tight focus:outline-none total-input" 
                       id="edit_total_${index}" name="total[]" type="text" readonly>
            </div>
        </div>
    `;
    
    container.appendChild(newRow);
    
    // Set values if detail data is provided
    if (detail) {
        document.getElementById(`edit_idbarang_${index}`).value = detail.idbarang || '';
        document.getElementById(`edit_qty_${index}`).value = detail.qty || '';
        document.getElementById(`edit_harga_${index}`).value = detail.harga || '';
        document.getElementById(`edit_total_${index}`).value = detail.total || '';
    }
}

// Function to calculate total for edit order form
function calculateEditOrderTotal() {
    const rows = document.querySelectorAll("#editOrderModal .order-detail-row");
    rows.forEach(row => {
        const harga = parseFloat(row.querySelector(".harga-input").value) || 0;
        const qty = parseFloat(row.querySelector(".qty-input").value) || 0;
        const total = harga * qty;
        row.querySelector(".total-input").value = total.toFixed(2);
    });
}

// Function to load barang data when ID Barang is selected in order form
function loadBarangDataForOrder(idbarang, index) {
    if (!idbarang) {
        // Clear fields if no item selected
        document.getElementById('harga_' + index).value = '';
        document.getElementById('total_' + index).value = '';
        return;
    }
    
    // Create FormData object
    const formData = new FormData();
    formData.append('get_barang_data', '1');
    formData.append('idbarang', idbarang);
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data) {
            // Populate fields with returned data
            document.getElementById('harga_' + index).value = data.harga || '';
            
            // Recalculate total
            calculateOrderTotal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Function to load purchase order data for editing
function loadPurchaseOrderForEdit(idpurchaseorder) {
    // Create FormData object
    const formData = new FormData();
    formData.append('get_purchase_order_data', '1');
    formData.append('idpurchaseorder', idpurchaseorder);
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.order) {
            openEditOrderModal(data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load purchase order data for editing.');
    });
}

// Function to load request details when ID Request is selected
function loadRequestDetails(idrequest) {
    if (!idrequest) {
        // Clear existing items if no request selected
        clearOrderItems();
        return;
    }
    
    // Create FormData object
    const formData = new FormData();
    formData.append('get_request_details', '1');
    formData.append('idrequest', idrequest);
    
    // Send AJAX request
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.length > 0) {
            // Clear existing items
            clearOrderItems();
            
            // Add items from request
            const container = document.getElementById('order-details-container');
            
            data.forEach((item, index) => {
                if (index === 0) {
                    // Update first row
                    updateOrderRow(0, item);
                } else {
                    // Add new rows for additional items
                    addOrderRow(item);
                }
            });
            
            // Recalculate totals
            calculateOrderTotal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Function to clear all order items except the first one
function clearOrderItems() {
    const container = document.getElementById('order-details-container');
    const rows = container.querySelectorAll('.order-detail-row');
    
    // Keep the first row but clear its values
    if (rows.length > 0) {
        const firstRow = rows[0];
        firstRow.querySelector('.idbarang-select').value = '';
        firstRow.querySelector('.qty-input').value = '';
        firstRow.querySelector('.harga-input').value = '';
        firstRow.querySelector('.total-input').value = '';
    }
    
    // Remove all rows except the first one
    for (let i = 1; i < rows.length; i++) {
        rows[i].remove();
    }
}

// Function to update an existing order row
function updateOrderRow(index, item) {
    const row = document.querySelector(`.order-detail-row:nth-child(${index + 1})`);
    if (row) {
        row.querySelector('.idbarang-select').value = item.idbarang || '';
        row.querySelector('.qty-input').value = item.qty || '';
        row.querySelector('.harga-input').value = item.harga || '';
        row.querySelector('.total-input').value = item.total || '';
    }
}

// Function to add a new order row
function addOrderRow(item) {
    const container = document.getElementById('order-details-container');
    const rowCount = container.querySelectorAll('.order-detail-row').length;
    
    const newRow = document.createElement('div');
    newRow.className = 'order-detail-row mb-4 p-4 border rounded';
    newRow.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="idbarang_${rowCount}">
                    ID Barang <span class="text-red-500">*</span>
                </label>
                <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 idbarang-select" 
                        id="idbarang_${rowCount}" name="idbarang[]" onchange="loadBarangDataForOrder(this.value, ${rowCount})" required>
                    <option value="">Select Item</option>
                    <?php foreach ($itemsList as $item): ?>
                    <option value="<?= htmlspecialchars($item['idbarang']) ?>">
                        <?= htmlspecialchars($item['kodebarang']) ?> - <?= htmlspecialchars($item['nama_barang']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="qty_${rowCount}">
                    Qty <span class="text-red-500">*</span>
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 qty-input" 
                       id="qty_${rowCount}" name="qty[]" type="number" min="1" onchange="calculateOrderTotal()" required>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="harga_${rowCount}">
                    Harga
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 harga-input" 
                       id="harga_${rowCount}" name="harga[]" type="number" step="0.01" readonly>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2" for="total_${rowCount}">
                    Total
                </label>
                <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 font-bold leading-tight focus:outline-none total-input" 
                       id="total_${rowCount}" name="total[]" type="text" readonly>
            </div>
        </div>
    `;
    
    container.appendChild(newRow);
    
    // Set values for the new row
    if (item) {
        document.getElementById(`idbarang_${rowCount}`).value = item.idbarang || '';
        document.getElementById(`qty_${rowCount}`).value = item.qty || '';
        document.getElementById(`harga_${rowCount}`).value = item.harga || '';
        document.getElementById(`total_${rowCount}`).value = item.total || '';
    }
}

// Function to calculate total for order form
function calculateOrderTotal() {
    const rows = document.querySelectorAll("#addOrderModal .order-detail-row");
    rows.forEach(row => {
        const harga = parseFloat(row.querySelector(".harga-input").value) || 0;
        const qty = parseFloat(row.querySelector(".qty-input").value) || 0;
        const total = harga * qty;
        row.querySelector(".total-input").value = total.toFixed(2);
    });
}

// Add event listener for adding new detail rows
document.addEventListener('DOMContentLoaded', function() {
    const addButton = document.getElementById('add-detail-row');
    if (addButton) {
        addButton.addEventListener('click', function() {
            addOrderRow();
        });
    }
});

// No DataTables initialization - using custom PHP pagination instead
// Tables use server-side pagination with custom controls

// Client-side Table Features
let tableData = {
    pr: [],
    po: []
};

let currentSort = {
    pr: { column: -1, direction: 'asc' },
    po: { column: -1, direction: 'asc' }
};

// Initialize table data on page load
document.addEventListener('DOMContentLoaded', function() {
    // Store original table data
    storeTableData('pr');
    storeTableData('po');
});

// Store table data for filtering
function storeTableData(tableType) {
    const table = document.getElementById(`${tableType === 'pr' ? 'purchase-request' : 'purchase-order'}-table`);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    tableData[tableType] = [];
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [];
        cells.forEach(cell => {
            rowData.push(cell.textContent.trim());
        });
        rowData.push(row); // Store reference to the row element
        tableData[tableType].push(rowData);
    });
}

// Search table function
function searchTable(tableType) {
    const searchTerm = document.getElementById(`search-${tableType}`).value.toLowerCase();
    const statusFilter = document.getElementById(`status-filter-${tableType}`).value;
    
    filterAndDisplayTable(tableType, searchTerm, statusFilter);
}

// Clear search
function clearSearch(tableType) {
    document.getElementById(`search-${tableType}`).value = '';
    const statusFilter = document.getElementById(`status-filter-${tableType}`).value;
    filterAndDisplayTable(tableType, '', statusFilter);
}

// Filter table by status
function filterTableByStatus(tableType) {
    const searchTerm = document.getElementById(`search-${tableType}`).value.toLowerCase();
    const statusFilter = document.getElementById(`status-filter-${tableType}`).value;
    
    filterAndDisplayTable(tableType, searchTerm, statusFilter);
}

// Combined filter and display
function filterAndDisplayTable(tableType, searchTerm, statusFilter) {
    const table = document.getElementById(`${tableType === 'pr' ? 'purchase-request' : 'purchase-order'}-table`);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    let visibleCount = 0;
    
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        let rowText = '';
        cells.forEach(cell => {
            rowText += cell.textContent.toLowerCase() + ' ';
        });
        
        // Get status text (column 6 for PR, column 6 for PO)
        const statusIndex = tableType === 'pr' ? 6 : 6;
        const rowStatus = cells[statusIndex]?.textContent.trim() || '';
        
        // Check if row matches search
        const matchesSearch = !searchTerm || rowText.includes(searchTerm);
        
        // Check if row matches status filter
        const matchesStatus = !statusFilter || rowStatus.includes(statusFilter);
        
        // Show/hide row
        if (matchesSearch && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update info text
    updateTableInfo(tableType, visibleCount);
}

// Update table info text
function updateTableInfo(tableType, visibleCount) {
    const table = document.getElementById(`${tableType === 'pr' ? 'purchase-request' : 'purchase-order'}-table`);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const totalRows = tbody.querySelectorAll('tr').length;
    
    // Create or update info text
    let infoDiv = document.getElementById(`info-${tableType}`);
    if (!infoDiv) {
        infoDiv = document.createElement('div');
        infoDiv.id = `info-${tableType}`;
        infoDiv.className = 'text-sm text-gray-700 mt-2';
        table.parentElement.appendChild(infoDiv);
    }
    
    infoDiv.textContent = `Showing ${visibleCount} of ${totalRows} entries`;
}

// Change entries per page (client-side show/hide)
// Change entries per page - reload with new limit
function changeEntries(tableType) {
    const entries = parseInt(document.getElementById(`entries-${tableType}`).value);
    
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Update limit parameter
    urlParams.set('limit', entries);
    urlParams.set('page', '1'); // Reset to first page
    
    // Reload page with new parameters
    window.location.search = urlParams.toString();
}

// Sort table by column
function sortTable(tableType, columnIndex) {
    const table = document.getElementById(`${tableType === 'pr' ? 'purchase-request' : 'purchase-order'}-table`);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Toggle sort direction
    if (currentSort[tableType].column === columnIndex) {
        currentSort[tableType].direction = currentSort[tableType].direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort[tableType].column = columnIndex;
        currentSort[tableType].direction = 'asc';
    }
    
    // Sort rows
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex]?.textContent.trim() || '';
        const bText = b.cells[columnIndex]?.textContent.trim() || '';
        
        // Try to parse as numbers
        const aNum = parseFloat(aText.replace(/[^0-9.-]/g, ''));
        const bNum = parseFloat(bText.replace(/[^0-9.-]/g, ''));
        
        let comparison = 0;
        if (!isNaN(aNum) && !isNaN(bNum)) {
            comparison = aNum - bNum;
        } else {
            comparison = aText.localeCompare(bText);
        }
        
        return currentSort[tableType].direction === 'asc' ? comparison : -comparison;
    });
    
    // Re-append sorted rows
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    updateSortIndicators(tableType, columnIndex);
}

// Update sort indicators in table header
function updateSortIndicators(tableType, columnIndex) {
    const table = document.getElementById(`${tableType === 'pr' ? 'purchase-request' : 'purchase-order'}-table`);
    if (!table) return;
    
    const headers = table.querySelectorAll('thead th');
    headers.forEach((header, index) => {
        // Remove existing arrows
        const existingArrow = header.querySelector('.sort-arrow');
        if (existingArrow) {
            existingArrow.remove();
        }
        
        // Add arrow to sorted column
        if (index === columnIndex) {
            const arrow = document.createElement('i');
            arrow.className = `fas fa-sort-${currentSort[tableType].direction === 'asc' ? 'up' : 'down'} sort-arrow text-blue-600 ml-1`;
            header.querySelector('a')?.appendChild(arrow) || header.appendChild(arrow);
        } else {
            const arrow = document.createElement('i');
            arrow.className = 'fas fa-sort sort-arrow text-gray-400 ml-1';
            header.querySelector('a')?.appendChild(arrow) || header.appendChild(arrow);
        }
    });
}

</script>

</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
$auth->checkAccess();

// Initialize variables
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

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
        $kodebarang = $_POST['kodebarang'] ?? '';
        $satuan = $_POST['satuan'] ?? '';        
        // No need to override with hidden values since we're not using them anymore
        
        // Get current user ID and role
        $iduserrequest = $_SESSION['user_id'] ?? null;
        $currentUserRole = $_SESSION['role'] ?? '';
        $currentUsername = $_SESSION['username'] ?? '';
        
        // Generate ID for purchaserequest using sequences
        $stmt = $pdo->prepare("SELECT last_no FROM sequences WHERE name = 'purchaserequest'");
        $stmt->execute();
        $sequence = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastNo = $sequence ? $sequence['last_no'] + 1 : 1;
        $idrequest = 'PR-' . str_pad($lastNo, 6, '0', STR_PAD_LEFT);
        
        // Update the sequence
        $stmt = $pdo->prepare("INSERT INTO sequences (name, last_no) VALUES ('purchaserequest', ?) ON DUPLICATE KEY UPDATE last_no = ?");
        $stmt->execute([$lastNo, $lastNo]);
        
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
                    $insertDetail = $pdo->prepare("INSERT INTO detailrequest (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject, kodebarang, satuan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $insertDetail->execute([$idbarang, $idrequest, $linkpembelian, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $kodebarang, $satuan]);
                    error_log('Detail request insert result: ' . ($result ? 'success' : 'failed'));
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
            $pdo->rollback();
            // Redirect with error
            header("Location: procurement.php?tab=purchase-request&error=add_failed");
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
                $updateDetail = $pdo->prepare("UPDATE detailrequest SET idbarang = ?, linkpembelian = ?, namaitem = ?, deskripsi = ?, harga = ?, qty = ?, total = ?, kodeproject = ?, kodebarang = ?, satuan = ? WHERE idrequest = ?");
                $updateDetail->execute([$idbarang, $linkpembelian, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $kodebarang, $satuan, $idrequest]);
            } else {
                // Insert new detail request
                $insertDetail = $pdo->prepare("INSERT INTO detailrequest (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject, kodebarang, satuan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertDetail->execute([$idbarang, $idrequest, $linkpembelian, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $kodebarang, $satuan]);
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
        if (empty($idrequest) || !is_numeric($idrequest)) {
            header("Location: procurement.php?tab=purchase-request&error=delete_failed&message=" . urlencode("Invalid request ID: " . $idrequest));
            exit();
        }
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete related records first (foreign key constraints)
            $deleteLogStatus = $pdo->prepare("DELETE FROM logstatusreq WHERE idrequest = ?");
            $deleteLogStatus->execute([$idrequest]);
            error_log("Deleted " . $deleteLogStatus->rowCount() . " log status records");
            
            $deleteDetailRequest = $pdo->prepare("DELETE FROM detailrequest WHERE idrequest = ?");
            $deleteDetailRequest->execute([$idrequest]);
            error_log("Deleted " . $deleteDetailRequest->rowCount() . " detail request records");
            
            // Delete the purchase request
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
$limit = 25;
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
    
    $countQuery = "SELECT COUNT(*) FROM purchaserequest pr 
                   WHERE 1=1 $searchCondition $dateFilter";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
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
        WHERE 1=1 $searchCondition $dateFilter
        GROUP BY pr.idrequest, pr.tgl_req, pr.tgl_butuh, pr.namarequestor, pr.keterangan, pr.idsupervisor, ls.status
        ORDER BY pr.tgl_req DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
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
    
    $countQuery = "SELECT COUNT(*) FROM purchaseorder po 
                   WHERE 1=1 $searchCondition $dateFilter";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
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
        WHERE 1=1 $searchCondition $dateFilter
        ORDER BY po.tgl_po DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">

<div class="flex min-h-screen">
    
    <!-- SIDEBAR -->
    <aside class="w-64 bg-white border-r border-gray-200 fixed h-full flex flex-col">
        <div class="p-6">
            <h1 class="text-xl font-bold text-gray-900">Internal Management<br>System</h1>
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
                            <button onclick="downloadReport()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium flex items-center gap-2">
                                <i class="fas fa-download"></i> Download
                            </button>
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
                                <tbody class="bg-white divide-y divide-gray-200">
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
                    <!-- Toolbar -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex gap-2">
                            <button id="add-pr-button" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium flex items-center gap-2">
                                <i class="fas fa-plus"></i> Add
                            </button>
                            <button id="edit-pr-button" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-medium flex items-center gap-2">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <div class="flex gap-4 items-center">
                            <form method="GET" class="flex gap-2">
                                <input type="hidden" name="tab" value="<?= $activeTab ?>">
                                <?php if ($selectedYear > 0): ?>
                                <input type="hidden" name="year" value="<?= $selectedYear ?>">
                                <?php endif; ?>
                                <?php if ($selectedMonth > 0): ?>
                                <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                                <?php endif; ?>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                                <button type="submit" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </form>
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
                            <button onclick="downloadReport()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium flex items-center gap-2">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table id="purchase-request-table" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" width="50">#</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Request</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Request</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Requestor</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Barang</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Link Pembelian</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Item</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Project</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="14" class="px-6 py-4 text-center text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50 cursor-pointer selectable-row" data-id="<?= htmlspecialchars($item['idrequest']) ?>" data-nama="<?= htmlspecialchars($item['namarequestor']) ?>" data-ket="<?= htmlspecialchars($item['keterangan']) ?>" data-tgl="<?= htmlspecialchars($item['tgl_req']) ?>" data-butuh="<?= htmlspecialchars($item['tgl_butuh']) ?>" data-supervisor="<?= htmlspecialchars($item['idsupervisor']) ?>" data-idbarang="<?= htmlspecialchars($item['idbarang']) ?>" data-kodebarang="<?= htmlspecialchars($item['kodebarang']) ?>" data-satuan="<?= htmlspecialchars($item['satuan']) ?>" data-link="<?= htmlspecialchars($item['linkpembelian']) ?>" data-item="<?= htmlspecialchars($item['namaitem']) ?>" data-desc="<?= htmlspecialchars($item['deskripsi']) ?>" data-harga="<?= htmlspecialchars($item['harga']) ?>" data-qty="<?= htmlspecialchars($item['qty']) ?>" data-total="<?= htmlspecialchars($item['total']) ?>" data-project="<?= htmlspecialchars($item['kodeproject']) ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><strong><?= htmlspecialchars($item['idrequest'] ?? '-') ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['tgl_req'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['namarequestor'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['idbarang'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['kodebarang'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($item['linkpembelian'] ?? '-') ?>"><?= htmlspecialchars($item['linkpembelian'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($item['namaitem'] ?? '-') ?>"><?= htmlspecialchars($item['namaitem'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($item['deskripsi'] ?? '-') ?>"><?= htmlspecialchars($item['deskripsi'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['harga'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['qty'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['total'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['kodeproject'] ?? '-') ?></td>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <a href="detail-request.php?idrequest=<?= urlencode($item['idrequest']) ?>" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs inline-block mr-2">
                                                Detail
                                            </a>
                                            <?php if ($_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'Procurement'): ?>
                                            <button onclick="confirmDelete('<?= urlencode($item['idrequest']) ?>')" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-xs inline-block">
                                                Delete
                                            </button>
                                            <?php endif; ?>                                        </td>                                    </tr>
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
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
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
                    <!-- Toolbar -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex gap-2">
                            <button id="add-po-button" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium flex items-center gap-2">
                                <i class="fas fa-plus"></i> Add
                            </button>
                            <button id="edit-po-button" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-medium flex items-center gap-2">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <div class="flex gap-4 items-center">
                            <form method="GET" class="flex gap-2">
                                <input type="hidden" name="tab" value="<?= $activeTab ?>">
                                <?php if ($selectedYear > 0): ?>
                                <input type="hidden" name="year" value="<?= $selectedYear ?>">
                                <?php endif; ?>
                                <?php if ($selectedMonth > 0): ?>
                                <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                                <?php endif; ?>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                                <button type="submit" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </form>
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
                            <button onclick="downloadReport()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium flex items-center gap-2">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" width="50">#</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Purchase Order</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal PO</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Request</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
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
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><strong><?= htmlspecialchars($item['idpurchaseorder'] ?? '-') ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['tgl_po'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['supplier'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['idrequest'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                    // Determine status color based on status name
                                                    $statusClass = 'bg-gray-100 text-gray-800'; // Default
                                                    if ($item['status_name'] == 'Process Order') {
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                    } elseif ($item['status_name'] == 'Arrived') {
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                    } elseif ($item['status_name'] == 'Pending') {
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    }
                                                    echo $statusClass;
                                                ?>">
                                                <?= htmlspecialchars($item['status_name']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <a href="detail-order.php?id=<?= urlencode($item['idpurchaseorder']) ?>" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs inline-block">
                                                Detail
                                            </a>                                        </td>
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
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?><?php if ($selectedYear > 0) echo '&year=' . $selectedYear; ?><?php if ($selectedMonth > 0) echo '&month=' . $selectedMonth; ?><?php if ($search) echo '&search=' . urlencode($search); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
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
        document.getElementById('kodebarang').value = '';
        document.getElementById('satuan').value = '';
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
    
    // Navigate to the new URL
    window.location.href = url;
}

// Download report function
function downloadReport() {
    alert('Download functionality would be implemented here');
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

// Calculate total for add form
function calculateAddTotal() {
    const harga = parseFloat(document.getElementById("harga").value) || 0;
    const qty = parseFloat(document.getElementById("qty").value) || 0;
    const total = harga * qty;
    
    document.getElementById("total").value = total.toFixed(2);
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
                kodebarang: selectedRow.dataset.kodebarang,
                satuan: selectedRow.dataset.satuan,
                linkpembelian: selectedRow.dataset.link,
                namaitem: selectedRow.dataset.item,
                deskripsi: selectedRow.dataset.desc,
                harga: selectedRow.dataset.harga,
                qty: selectedRow.dataset.qty,
                total: selectedRow.dataset.total,
                kodeproject: selectedRow.dataset.project
            };
            
            openEditRequestModal(requestData);
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
</script>

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
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight focus:outline-none" 
                               id="kodebarang" name="kodebarang" type="text" readonly>
                        <p class="text-xs text-gray-500 mt-1">Auto-filled after selecting ID Barang</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="satuan">
                            Satuan
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight focus:outline-none" 
                               id="satuan" name="satuan" type="text" readonly>
                        <p class="text-xs text-gray-500 mt-1">Auto-filled after selecting ID Barang</p>
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
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_kodebarang">
                                Kode Barang
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                                   id="edit_kodebarang" name="kodebarang" type="text" readonly>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_satuan">
                                Satuan
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
                                   id="edit_satuan" name="satuan" type="text" readonly>
                        </div>
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
                                   id="edit_qty" name="qty" type="number">
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
</html>iv>
</div></body>
</html>
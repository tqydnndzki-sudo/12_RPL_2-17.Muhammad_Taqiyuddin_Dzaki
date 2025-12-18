<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
$auth->checkAccess();

// Get the request ID from URL parameter
$idrequest = $_GET['idrequest'] ?? '';

// Validate request ID
if (empty($idrequest)) {
    die('ID Request tidak ditemukan');
}

/* =========================
   MODE DOWNLOAD (PDF)
========================= */
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    // Fetch all necessary data for PDF
    // Fetch purchase request details
    $queryPR = "
        SELECT 
            pr.idrequest,
            pr.tgl_req,
            pr.namarequestor,
            pr.keterangan,
            pr.tgl_butuh,
            u.nama AS supervisor_name
        FROM purchaserequest pr
        LEFT JOIN users u ON pr.idsupervisor = u.iduser
        WHERE pr.idrequest = ?
    ";

    $stmtPR = $pdo->prepare($queryPR);
    $stmtPR->execute([$idrequest]);
    $pr = $stmtPR->fetch(PDO::FETCH_ASSOC);

    // Fetch detail items for this request
    $queryDetails = "
        SELECT 
            dr.idbarang,
            dr.linkpembelian,
            dr.namaitem,
            dr.deskripsi,
            dr.harga,
            dr.qty,
            dr.total,
            dr.kodeproject,
            mb.kodebarang
        FROM detailrequest dr
        LEFT JOIN m_barang mb ON dr.idbarang = mb.idbarang
        WHERE dr.idrequest = ?
        ORDER BY dr.iddetailrequest
    ";
    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->execute([$idrequest]);
    $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

    // Fetch current status of the purchase request
    $currentStatus = 'Pending';
    $queryStatus = "
        SELECT status
        FROM logstatusreq
        WHERE idrequest = ?
        ORDER BY date DESC
        LIMIT 1
    ";
    $stmtStatus = $pdo->prepare($queryStatus);
    $stmtStatus->execute([$idrequest]);
    $statusResult = $stmtStatus->fetchColumn();
    
    // Map status code to status name
    $statusMap = [
        1 => 'Process Approval Leader',
        2 => 'Process Approval Manager',
        3 => 'Approved',
        4 => 'Hold',
        5 => 'Reject',
        6 => 'Done'
    ];
    $currentStatus = isset($statusMap[$statusResult]) ? $statusMap[$statusResult] : 'Pending';

    // Create new PDF document
    require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Purchase Request ' . $idrequest);
    $pdf->SetSubject('Purchase Request Details');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(15, 15, 15);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Convert logo to base64 for reliable PDF rendering
    $logoPath = __DIR__ . '/../images/logo_ipo.png';
    $logoData = '';
    if (file_exists($logoPath)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }
    
    // Company header with logo
    $html = '
    <table cellpadding="5" style="width:100%; border-collapse: collapse;">
        <tr>
            <td style="width:20%; text-align:left;">
                <img src="' . $logoData . '" alt="Company Logo" style="height:50px;">
            </td>
            <td style="width:60%; text-align:center;">
                <h1 style="margin:0; color:#2c3e50;">PT. IMANI PRIMA</h1>
                <h2 style="margin:5px 0; color:#2c3e50;">PURCHASE REQUEST DETAIL</h2>
                <h3 style="margin:0; color:#2c3e50;">ID: ' . htmlspecialchars($idrequest) . '</h3>
            </td>
            <td style="width:20%; text-align:right;">
                <img src="' . $logoData . '" alt="Company Logo" style="height:50px;">
            </td>
        </tr>
    </table>
    <hr style="border-top: 2px solid #2c3e50; margin: 10px 0;"><br>';

    // Purchase Request Information
    $html .= '
    <h2>PURCHASE REQUEST INFORMATION</h2>
    <table border="1" cellpadding="5">
        <tr>
            <td><b>Request ID:</b></td>
            <td>' . htmlspecialchars($pr['idrequest']) . '</td>
        </tr>
        <tr>
            <td><b>Request Date:</b></td>
            <td>' . (!empty($pr['tgl_req']) ? date('d M Y', strtotime($pr['tgl_req'])) : '-') . '</td>
        </tr>
        <tr>
            <td><b>Requestor:</b></td>
            <td>' . htmlspecialchars($pr['namarequestor']) . '</td>
        </tr>
        <tr>
            <td><b>Supervisor:</b></td>
            <td>' . htmlspecialchars($pr['supervisor_name'] ?? '-') . '</td>
        </tr>
        <tr>
            <td><b>Required Date:</b></td>
            <td>' . (!empty($pr['tgl_butuh']) ? date('d M Y', strtotime($pr['tgl_butuh'])) : '-') . '</td>
        </tr>
        <tr>
            <td><b>Current Status:</b></td>
            <td>' . htmlspecialchars($currentStatus) . '</td>
        </tr>
        <tr>
            <td><b>Description:</b></td>
            <td>' . htmlspecialchars($pr['keterangan']) . '</td>
        </tr>
    </table><br>';

    // Request Items
    $html .= '<h2>REQUEST ITEMS</h2>
    <table border="1" cellpadding="5">
        <thead>
            <tr style="background-color:#f0f0f0;">
                <th><b>Item Code</b></th>
                <th><b>Item Name</b></th>
                <th><b>Description</b></th>
                <th><b>Link</b></th>
                <th><b>Price</b></th>
                <th><b>Qty</b></th>
                <th><b>Total</b></th>
                <th><b>Project Code</b></th>
            </tr>
        </thead>
        <tbody>';
    
    $grandTotal = 0;
    foreach ($details as $detail) {
        $grandTotal += $detail['total'];
        $html .= '
        <tr>
            <td>' . htmlspecialchars($detail['kodebarang'] ?? '-') . '</td>
            <td>' . htmlspecialchars($detail['namaitem']) . '</td>
            <td>' . htmlspecialchars($detail['deskripsi']) . '</td>
            <td>' . htmlspecialchars($detail['linkpembelian']) . '</td>
            <td>Rp ' . number_format($detail['harga'], 0, ',', '.') . '</td>
            <td>' . number_format($detail['qty'], 0, ',', '.') . '</td>
            <td>Rp ' . number_format($detail['total'], 0, ',', '.') . '</td>
            <td>' . htmlspecialchars($detail['kodeproject']) . '</td>
        </tr>';
    }
    
    $html .= '
        <tr style="background-color:#f0f0f0;font-weight:bold;">
            <td colspan="6" align="right">Grand Total:</td>
            <td>Rp ' . number_format($grandTotal, 0, ',', '.') . '</td>
            <td></td>
        </tr>
        </tbody>
    </table>';

    // Approval section
    $html .= '
    <br><br><br>
    <table cellpadding="5" style="width:100%; border-collapse: collapse;">
        <tr>
            <td style="width:50%; text-align:center;">
                <p><b>Mengetahui,</b></p>
                <br><br><br>
                <p>___________________________</p>
                <p><b>Requestor</b></p>
                <p>' . htmlspecialchars($pr['namarequestor']) . '</p>
            </td>
            <td style="width:50%; text-align:center;">
                <p><b>Mengetahui,</b></p>
                <br><br><br>
                <p>___________________________</p>
                <p><b>Supervisor</b></p>
                <p>' . htmlspecialchars($pr['supervisor_name'] ?? '-') . '</p>
            </td>
        </tr>
    </table>';

    // Write HTML content
    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output('PR_' . $idrequest . '.pdf', 'D');
    exit;
}/* =========================
   HANDLE EDIT REQUEST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_request'])) {
    $namarequestor = $_POST['namarequestor'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $tgl_req = $_POST['tgl_req'] ?? '';
    $tgl_butuh = $_POST['tgl_butuh'] ?? '';
    $idsupervisor = $_POST['idsupervisor'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE purchaserequest 
            SET namarequestor = ?, keterangan = ?, tgl_req = ?, tgl_butuh = ?, idsupervisor = ?
            WHERE idrequest = ?
        ");
        $stmt->execute([$namarequestor, $keterangan, $tgl_req, $tgl_butuh, $idsupervisor, $idrequest]);
        
        // Redirect to refresh the page with updated data
        header("Location: detail-request.php?idrequest=$idrequest&updated=1");
        exit;
    } catch (Exception $e) {
        $error_message = "Error updating request: " . $e->getMessage();
    }
}

/* =========================
   HANDLE EDIT ITEM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $iddetailrequest = $_POST['iddetailrequest'] ?? '';
    $namaitem = $_POST['namaitem'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $harga = $_POST['harga'] ?? 0;
    $qty = $_POST['qty'] ?? 0;
    $total = $_POST['total'] ?? 0;
    $kodeproject = $_POST['kodeproject'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE detailrequest 
            SET namaitem = ?, deskripsi = ?, harga = ?, qty = ?, total = ?, kodeproject = ?
            WHERE iddetailrequest = ?
        ");
        $stmt->execute([$namaitem, $deskripsi, $harga, $qty, $total, $kodeproject, $iddetailrequest]);
        
        // Redirect to refresh the page with updated data
        header("Location: detail-request.php?idrequest=$idrequest&updated=1");
        exit;
    } catch (Exception $e) {
        $error_message = "Error updating item: " . $e->getMessage();
    }
}

/* =========================
   AMBIL DATA PURCHASE REQUEST
========================= */
$qPR = $pdo->prepare("
    SELECT pr.*, u.nama AS nama_supervisor
    FROM purchaserequest pr
    LEFT JOIN users u ON pr.idsupervisor = u.iduser
    WHERE pr.idrequest = ?
");
$qPR->execute([$idrequest]);
$pr = $qPR->fetch(PDO::FETCH_ASSOC);

if (!$pr) {
    die('Data Purchase Request tidak ditemukan');
}

/* =========================
   DETAIL BARANG
========================= */
$qDetail = $pdo->prepare("
    SELECT dr.*, mb.kodebarang
    FROM detailrequest dr
    LEFT JOIN m_barang mb ON dr.idbarang = mb.idbarang
    WHERE dr.idrequest = ?
");
$qDetail->execute([$idrequest]);
$items = $qDetail->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   STATUS TERAKHIR PR
========================= */
$qStatus = $pdo->prepare("
    SELECT status
    FROM logstatusreq
    WHERE idrequest = ?
    ORDER BY date DESC
    LIMIT 1
");
$qStatus->execute([$idrequest]);
$statusPR = $qStatus->fetchColumn() ?? 'Pending';

/* =========================
   GET ALL USERS FOR SUPERVISOR DROPDOWN
========================= */
$qUsers = $pdo->prepare("SELECT iduser, nama FROM users WHERE roletype IN ('Leader', 'Manager') ORDER BY nama");
$qUsers->execute();
$users = $qUsers->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   UPDATE STATUS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $map = [
        'approve' => 3,  // Approved
        'reject'  => 5,  // Reject
        'pending' => 1   // Process Approval Leader (default pending state)
    ];

    $status = $map[$_POST['action']] ?? 1;
    $note   = $_POST['note_reject'] ?? null;

    $ins = $pdo->prepare("
        INSERT INTO logstatusreq (status, date, note_reject, idrequest)
        VALUES (?, NOW(), ?, ?)
    ");
    $ins->execute([$status, $note, $idrequest]);

    header("Location: detail-request.php?idrequest=$idrequest&status_updated=1");
    exit;
}

$title = 'Detail Purchase Request';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Internal Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
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
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
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
    </style>
</head>

<body class="bg-gray-50">
    <!-- Edit Request Modal -->
    <div id="editRequestModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editRequestModal')">&times;</span>
            <h2 class="text-xl font-bold mb-4">Edit Purchase Request</h2>
            <form method="POST">
                <input type="hidden" name="edit_request" value="1">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="namarequestor">
                        Requestor Name
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="namarequestor" name="namarequestor" type="text" 
                           value="<?= htmlspecialchars($pr['namarequestor'] ?? '') ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="keterangan">
                        Description
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                              id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars($pr['keterangan'] ?? '') ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="tgl_req">
                        Request Date
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="tgl_req" name="tgl_req" type="date" 
                           value="<?= !empty($pr['tgl_req']) ? date('Y-m-d', strtotime($pr['tgl_req'])) : '' ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="tgl_butuh">
                        Required Date
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="tgl_butuh" name="tgl_butuh" type="date" 
                           value="<?= !empty($pr['tgl_butuh']) ? date('Y-m-d', strtotime($pr['tgl_butuh'])) : '' ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="idsupervisor">
                        Supervisor
                    </label>
                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                            id="idsupervisor" name="idsupervisor">
                        <option value="">Select Supervisor</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['iduser']) ?>" 
                                <?= ($pr['idsupervisor'] ?? '') == $user['iduser'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['nama']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
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
    </div>

    <!-- Edit Item Modal -->
    <div id="editItemModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editItemModal')">&times;</span>
            <h2 class="text-xl font-bold mb-4">Edit Request Item</h2>
            <form method="POST" id="editItemForm">
                <input type="hidden" name="edit_item" value="1">
                <input type="hidden" name="iddetailrequest" id="edit_item_id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_namaitem">
                        Item Name
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="edit_namaitem" name="namaitem" type="text">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_deskripsi">
                        Description
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                              id="edit_deskripsi" name="deskripsi" rows="3"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_harga">
                        Price
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="edit_harga" name="harga" type="number" step="0.01">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_qty">
                        Quantity
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="edit_qty" name="qty" type="number">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_total">
                        Total
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="edit_total" name="total" type="number" step="0.01" readonly>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_kodeproject">
                        Project Code
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="edit_kodeproject" name="kodeproject" type="text">
                </div>
                <div class="flex items-center justify-between">
                    <button type="button" onclick="closeModal('editItemModal')" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow">
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['updated'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <strong>Success!</strong> Data has been updated successfully.
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['status_updated'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <strong>Success!</strong> Status has been updated successfully.
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <strong>Error!</strong> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-4">
                <img src="../images/logo_ipo.png" alt="Logo" class="h-12">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Detail Purchase Request</h1>
                    <p class="text-gray-600">ID: <?= htmlspecialchars($idrequest) ?></p>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button onclick="window.location.href='procurement.php?tab=purchase-request'" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button onclick="window.location.href='detail-request.php?idrequest=<?= urlencode($idrequest) ?>&download=pdf'" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <button onclick="openEditRequestModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>        </div>

        <!-- INFO -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 p-4 bg-gray-50 rounded-lg">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Request Information</h3>
                <div class="space-y-2">
                    <p><b class="text-gray-700">Requestor:</b> <span class="text-gray-900"><?= htmlspecialchars($pr['namarequestor'] ?? '') ?></span></p>
                    <p><b class="text-gray-700">Supervisor:</b> <span class="text-gray-900"><?= htmlspecialchars($pr['nama_supervisor'] ?? '') ?></span></p>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Date Information</h3>
                <div class="space-y-2">
                    <p><b class="text-gray-700">Created Date:</b> <span class="text-gray-900"><?= !empty($pr['tgl_req']) ? date('d M Y', strtotime($pr['tgl_req'])) : '-' ?></span></p>
                    <p><b class="text-gray-700">Required Date:</b> <span class="text-gray-900"><?= !empty($pr['tgl_butuh']) ? date('d M Y', strtotime($pr['tgl_butuh'])) : '-' ?></span></p>
                </div>
                
                <div class="mt-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Current Status</h3>
                    <span class="px-3 py-1 rounded-full text-sm font-medium
                        <?= $statusPR === 'Approved' ? 'bg-green-100 text-green-800' :
                           ($statusPR === 'Reject' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                        <?= htmlspecialchars($statusPR) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ACTION -->
        <div class="mb-6 p-4 bg-white border border-gray-200 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Status Actions</h3>
            <form method="POST" class="flex flex-wrap gap-3">
                <button name="action" value="approve" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Approve
                </button>
                <button name="action" value="pending" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 flex items-center gap-2">
                    <i class="fas fa-clock"></i> Pending
                </button>
                <button name="action" value="reject" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
            </form>
        </div>

        <!-- ITEMS TABLE -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Request Items</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border px-4 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">Item Code</th>
                            <th class="border px-4 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                            <th class="border px-4 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="border px-4 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="border px-4 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                            <th class="border px-4 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="border px-4 py-3 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $i): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($i['kodebarang'] ?? '-') ?></td>
                                <td class="border px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($i['namaitem'] ?? '-') ?></td>
                                <td class="border px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($i['deskripsi'] ?? '-') ?></td>
                                <td class="border px-4 py-3 text-sm text-gray-900">Rp <?= number_format($i['harga'] ?? 0) ?></td>
                                <td class="border px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($i['qty'] ?? 0) ?></td>
                                <td class="border px-4 py-3 text-sm text-gray-900">Rp <?= number_format($i['total'] ?? 0) ?></td>
                                <td class="border px-4 py-3 text-sm text-gray-900">
                                    <button onclick="openEditItemModal(<?= htmlspecialchars(json_encode($i)) ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="border px-4 py-3 text-center text-gray-500">No items found</td>
                            </tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Auto-calculate total when price or quantity changes
        document.addEventListener('input', function(e) {
            if (e.target.id === 'edit_harga' || e.target.id === 'edit_qty') {
                const harga = parseFloat(document.getElementById('edit_harga').value) || 0;
                const qty = parseInt(document.getElementById('edit_qty').value) || 0;
                const total = harga * qty;
                document.getElementById('edit_total').value = total.toFixed(2);
            }
        });

        // Modal functions
        function openEditRequestModal() {
            document.getElementById('editRequestModal').style.display = 'block';
        }

        function openEditItemModal(itemData) {
            // Populate the form with item data
            document.getElementById('edit_item_id').value = itemData.iddetailrequest;
            document.getElementById('edit_namaitem').value = itemData.namaitem || '';
            document.getElementById('edit_deskripsi').value = itemData.deskripsi || '';
            document.getElementById('edit_harga').value = itemData.harga || 0;
            document.getElementById('edit_qty').value = itemData.qty || 0;
            document.getElementById('edit_total').value = itemData.total || 0;
            document.getElementById('edit_kodeproject').value = itemData.kodeproject || '';
            
            document.getElementById('editItemModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
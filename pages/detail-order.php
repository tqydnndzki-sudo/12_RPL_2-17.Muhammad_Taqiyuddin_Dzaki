<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
// Include TCPDF library
require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';

// Check if user is logged in
$auth->checkAccess();

// Get the order ID from URL parameter
$orderId = $_GET['id'] ?? '';

// Validate order ID
if (empty($orderId)) {
    header('Location: procurement.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO logstatusorder (idpurchaseorder, status, date, keterangan) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$orderId, $newStatus, $keterangan]);
        
        // Redirect to refresh the page
        header("Location: detail-order.php?id=" . urlencode($orderId) . "&status_updated=1");
        exit;
    } catch (Exception $e) {
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

// Handle PDF download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    // Fetch all necessary data for PDF
    // Fetch purchase order details
    $queryOrder = "
        SELECT 
            po.idpurchaseorder,
            po.tgl_po,
            po.supplier,
            pr.idrequest,
            pr.keterangan as request_keterangan
        FROM purchaseorder po
        LEFT JOIN purchaserequest pr ON po.idrequest = pr.idrequest
        WHERE po.idpurchaseorder = :idpurchaseorder
    ";

    $stmtOrder = $pdo->prepare($queryOrder);
    $stmtOrder->execute([':idpurchaseorder' => $orderId]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    // Fetch detail items for this order
    $queryDetails = "
        SELECT 
            dor.iddetailorder,
            dor.qty,
            dor.harga,
            dor.total,
            mb.kodebarang,
            mb.nama_barang,
            mb.deskripsi as detail_keterangan
        FROM detailorder dor
        LEFT JOIN m_barang mb ON dor.idbarang = mb.idbarang
        WHERE dor.idpurchaseorder = :idpurchaseorder
        ORDER BY dor.iddetailorder
    ";
    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->execute([':idpurchaseorder' => $orderId]);
    $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

    // Fetch related purchase request details if idrequest exists
    $request = null;
    if (!empty($order['idrequest'])) {
        $queryRequest = "
            SELECT 
                pr.idrequest,
                pr.tgl_req,
                pr.namarequestor,
                pr.keterangan,
                pr.status,
                COALESCE(ls.status, 0) as status_code,
                CASE COALESCE(ls.status, 0)
                    WHEN 1 THEN 'Process Approval Leader'
                    WHEN 2 THEN 'Process Approval Manager'
                    WHEN 3 THEN 'Approved'
                    WHEN 4 THEN 'Hold'
                    WHEN 5 THEN 'Reject'
                    WHEN 6 THEN 'Done'
                    ELSE 'Pending'
                END as status_name
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
            WHERE pr.idrequest = :idrequest
        ";

        $stmtRequest = $pdo->prepare($queryRequest);
        $stmtRequest->execute([':idrequest' => $order['idrequest']]);
        $request = $stmtRequest->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch current status of the purchase order
    $currentStatus = 'Pending';
    $queryStatus = "
        SELECT status
        FROM logstatusorder
        WHERE idpurchaseorder = :idpurchaseorder
        ORDER BY date DESC
        LIMIT 1
    ";
    $stmtStatus = $pdo->prepare($queryStatus);
    $stmtStatus->execute([':idpurchaseorder' => $orderId]);
    $statusResult = $stmtStatus->fetch(PDO::FETCH_ASSOC);
    if ($statusResult) {
        $currentStatus = $statusResult['status'];
    }

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Purchase Order ' . $orderId);
    $pdf->SetSubject('Purchase Order Details');

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
                <h2 style="margin:5px 0; color:#2c3e50;">PURCHASE ORDER DETAIL</h2>
                <h3 style="margin:0; color:#2c3e50;">ID: ' . htmlspecialchars($orderId) . '</h3>
            </td>
            <td style="width:20%; text-align:right;">
                <img src="' . $logoData . '" alt="Company Logo" style="height:50px;">
            </td>
        </tr>
    </table>
    <hr style="border-top: 2px solid #2c3e50; margin: 10px 0;"><br>';

    // Order Information
    $html .= '
    <h2>ORDER INFORMATION</h2>
    <table border="1" cellpadding="5">
        <tr>
            <td><b>Purchase Order ID:</b></td>
            <td>' . htmlspecialchars($order['idpurchaseorder']) . '</td>
        </tr>
        <tr>
            <td><b>Order Date:</b></td>
            <td>' . (!empty($order['tgl_po']) ? date('d M Y', strtotime($order['tgl_po'])) : '-') . '</td>
        </tr>
        <tr>
            <td><b>Supplier:</b></td>
            <td>' . htmlspecialchars($order['supplier']) . '</td>
        </tr>
        <tr>
            <td><b>Current Status:</b></td>
            <td>' . htmlspecialchars($currentStatus) . '</td>
        </tr>';
    
    if (!empty($order['idrequest'])) {
        $html .= '
        <tr>
            <td><b>Related Request ID:</b></td>
            <td>' . htmlspecialchars($order['idrequest']) . '</td>
        </tr>
        <tr>
            <td><b>Request Description:</b></td>
            <td>' . htmlspecialchars($order['request_keterangan']) . '</td>
        </tr>';
    }
    
    $html .= '</table><br>';

    // Related Purchase Request
    if (!empty($request)) {
        $html .= '
        <h2>RELATED PURCHASE REQUEST</h2>
        <table border="1" cellpadding="5">
            <tr>
                <td><b>Request ID:</b></td>
                <td>' . htmlspecialchars($request['idrequest']) . '</td>
            </tr>
            <tr>
                <td><b>Request Date:</b></td>
                <td>' . (!empty($request['tgl_req']) ? date('d M Y', strtotime($request['tgl_req'])) : '-') . '</td>
            </tr>
            <tr>
                <td><b>Requestor:</b></td>
                <td>' . htmlspecialchars($request['namarequestor']) . '</td>
            </tr>
            <tr>
                <td><b>Status:</b></td>
                <td>' . htmlspecialchars($request['status_name']) . '</td>
            </tr>
            <tr>
                <td><b>Description:</b></td>
                <td>' . htmlspecialchars($request['keterangan']) . '</td>
            </tr>
        </table><br>';
    }

    // Order Items
    $html .= '<h2>ORDER ITEMS</h2>
    <table border="1" cellpadding="5">
        <thead>
            <tr style="background-color:#f0f0f0;">
                <th><b>Item Code</b></th>
                <th><b>Item Name</b></th>
                <th><b>Description</b></th>
                <th><b>Price</b></th>
                <th><b>Qty</b></th>
                <th><b>Total</b></th>
            </tr>
        </thead>
        <tbody>';
    
    $grandTotal = 0;
    foreach ($details as $detail) {
        $grandTotal += $detail['total'];
        $html .= '
            <tr>
                <td>' . htmlspecialchars($detail['kodebarang'] ?? '-') . '</td>
                <td>' . htmlspecialchars($detail['nama_barang'] ?? '-') . '</td>
                <td>' . htmlspecialchars($detail['detail_keterangan'] ?? '-') . '</td>
                <td>Rp ' . number_format($detail['harga'] ?? 0, 0, ',', '.') . '</td>
                <td>' . number_format($detail['qty'] ?? 0, 0, ',', '.') . '</td>
                <td>Rp ' . number_format($detail['total'] ?? 0, 0, ',', '.') . '</td>
            </tr>';
    }
    
    $html .= '
            <tr style="background-color:#f0f0f0;font-weight:bold;">
                <td colspan="5" style="text-align:right;">Grand Total:</td>
                <td>Rp ' . number_format($grandTotal, 0, ',', '.') . '</td>
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
                <p><b>Procurement Staff</b></p>
                <p>' . htmlspecialchars($_SESSION['username'] ?? '-') . '</p>
            </td>
            <td style="width:50%; text-align:center;">
                <p><b>Mengetahui,</b></p>
                <br><br><br>
                <p>___________________________</p>
                <p><b>Supervisor</b></p>
                <p>' . htmlspecialchars($request['namarequestor'] ?? '-') . '</p>
            </td>
        </tr>
    </table>';

    // Print text using writeHTMLCell()
    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output('PO_' . $orderId . '.pdf', 'D');
    exit;
}

// Fetch purchase order details for display
$queryOrder = "
    SELECT 
        po.idpurchaseorder,
        po.tgl_po,
        po.supplier,
        pr.idrequest,
        pr.keterangan as request_keterangan
    FROM purchaseorder po
    LEFT JOIN purchaserequest pr ON po.idrequest = pr.idrequest
    WHERE po.idpurchaseorder = :idpurchaseorder
";

$stmtOrder = $pdo->prepare($queryOrder);
$stmtOrder->execute([':idpurchaseorder' => $orderId]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

// If order not found, redirect back
if (!$order) {
    header('Location: procurement.php');
    exit;
}

// Fetch detail items for this order
$queryDetails = "
    SELECT 
        dor.iddetailorder,
        dor.qty,
        dor.harga,
        dor.total,
        mb.kodebarang,
        mb.nama_barang,
        mb.deskripsi as detail_keterangan
    FROM detailorder dor
    LEFT JOIN m_barang mb ON dor.idbarang = mb.idbarang
    WHERE dor.idpurchaseorder = :idpurchaseorder
    ORDER BY dor.iddetailorder
";
$stmtDetails = $pdo->prepare($queryDetails);
$stmtDetails->execute([':idpurchaseorder' => $orderId]);
$details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

// Fetch related purchase request details if idrequest exists
$request = null;
if (!empty($order['idrequest'])) {
    $queryRequest = "
        SELECT 
            pr.idrequest,
            pr.tgl_req,
            pr.namarequestor,
            pr.keterangan,
            pr.status,
            COALESCE(ls.status, 0) as status_code,
            CASE COALESCE(ls.status, 0)
                WHEN 1 THEN 'Process Approval Leader'
                WHEN 2 THEN 'Process Approval Manager'
                WHEN 3 THEN 'Approved'
                WHEN 4 THEN 'Hold'
                WHEN 5 THEN 'Reject'
                WHEN 6 THEN 'Done'
                ELSE 'Pending'
            END as status_name
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
        WHERE pr.idrequest = :idrequest
    ";

    $stmtRequest = $pdo->prepare($queryRequest);
    $stmtRequest->execute([':idrequest' => $order['idrequest']]);
    $request = $stmtRequest->fetch(PDO::FETCH_ASSOC);
}

// Fetch current status of the purchase order
$currentStatus = 'Pending';
$queryStatus = "
    SELECT status
    FROM logstatusorder
    WHERE idpurchaseorder = :idpurchaseorder
    ORDER BY date DESC
    LIMIT 1
";
$stmtStatus = $pdo->prepare($queryStatus);
$stmtStatus->execute([':idpurchaseorder' => $orderId]);
$statusResult = $stmtStatus->fetch(PDO::FETCH_ASSOC);
if ($statusResult) {
    $currentStatus = $statusResult['status'];
}

// Define available statuses
$statusOptions = [
    'Process Order',
    'Process Payment',
    'Process Delivery',
    'Arrived'
];

$title = 'Detail Purchase Order';
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
    </style>
</head>
<body class="bg-gray-50">

<div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow">
    <!-- Success/Error Messages -->
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
                <h1 class="text-2xl font-bold text-gray-900">Detail Purchase Order</h1>
                <p class="text-gray-600">ID: <?= htmlspecialchars($order['idpurchaseorder']) ?></p>
            </div>
        </div>
        
        <div class="flex gap-3">
            <button onclick="window.location.href='procurement.php?tab=purchase-order'" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <button onclick="window.location.href='detail-order.php?id=<?= urlencode($orderId) ?>&download=pdf'" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium flex items-center gap-2">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>
    </div>

    <!-- ORDER INFO -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 p-4 bg-gray-50 rounded-lg">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Order Information</h3>
            <div class="space-y-2">
                <p><b class="text-gray-700">Purchase Order ID:</b> <span class="text-gray-900"><?= htmlspecialchars($order['idpurchaseorder']) ?></span></p>
                <p><b class="text-gray-700">Order Date:</b> <span class="text-gray-900"><?= !empty($order['tgl_po']) ? date('d M Y', strtotime($order['tgl_po'])) : '-' ?></span></p>
                <p><b class="text-gray-700">Supplier:</b> <span class="text-gray-900"><?= htmlspecialchars($order['supplier']) ?></span></p>
                <p><b class="text-gray-700">Current Status:</b> 
                    <span class="px-3 py-1 rounded-full text-sm font-medium
                        <?php 
                            switch($currentStatus) {
                                case 'Process Order': echo 'bg-blue-100 text-blue-800'; break;
                                case 'Process Payment': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'Process Delivery': echo 'bg-purple-100 text-purple-800'; break;
                                case 'Arrived': echo 'bg-green-100 text-green-800'; break;
                                default: echo 'bg-gray-100 text-gray-800'; break;
                            }
                        ?>">
                        <?= htmlspecialchars($currentStatus) ?>
                    </span>
                </p>
            </div>
        </div>
        <?php if (!empty($order['idrequest'])): ?>
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Related Request Information</h3>
            <div class="space-y-2">
                <p><b class="text-gray-700">Request ID:</b> <span class="text-gray-900"><?= htmlspecialchars($order['idrequest']) ?></span></p>
                <p><b class="text-gray-700">Request Description:</b> <span class="text-gray-900"><?= htmlspecialchars($order['request_keterangan']) ?></span></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- STATUS UPDATE FORM -->
    <div class="mb-6 p-4 bg-white border border-gray-200 rounded-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Update Status</h3>
        <form method="POST" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="update_status" value="1">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($statusOptions as $statusOption): ?>
                    <option value="<?= htmlspecialchars($statusOption) ?>" <?= $currentStatus === $statusOption ? 'selected' : '' ?>>
                        <?= htmlspecialchars($statusOption) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                <input type="text" name="keterangan" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Add notes about this status update">
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Update Status
                </button>
            </div>
        </form>
    </div>

    <!-- RELATED PURCHASE REQUEST -->
    <?php if (!empty($request)): ?>
    <div class="mb-6 p-4 bg-white border border-gray-200 rounded-lg">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Related Purchase Request</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p><b class="text-gray-700">Request ID:</b> <span class="text-gray-900"><?= htmlspecialchars($request['idrequest']) ?></span></p>
                <p><b class="text-gray-700">Request Date:</b> <span class="text-gray-900"><?= !empty($request['tgl_req']) ? date('d M Y', strtotime($request['tgl_req'])) : '-' ?></span></p>
                <p><b class="text-gray-700">Requestor:</b> <span class="text-gray-900"><?= htmlspecialchars($request['namarequestor']) ?></span></p>
            </div>
            <div>
                <p><b class="text-gray-700">Status:</b> 
                    <span class="px-3 py-1 rounded-full text-sm font-medium
                        <?php 
                            switch($request['status_code']) {
                                case 1: echo 'bg-yellow-100 text-yellow-800'; break;
                                case 2: echo 'bg-blue-100 text-blue-800'; break;
                                case 3: echo 'bg-green-100 text-green-800'; break;
                                case 4: echo 'bg-orange-100 text-orange-800'; break;
                                case 5: echo 'bg-red-100 text-red-800'; break;
                                case 6: echo 'bg-green-200 text-green-900'; break;
                                default: echo 'bg-gray-100 text-gray-800'; break;
                            }
                        ?>">
                        <?= htmlspecialchars($request['status_name']) ?>
                    </span>
                </p>
                <p><b class="text-gray-700">Description:</b> <span class="text-gray-900"><?= htmlspecialchars($request['keterangan']) ?></span></p>
                <div class="mt-2">
                    <button onclick="window.location.href='detail-request.php?idrequest=<?= urlencode($request['idrequest']) ?>'" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs">
                        View Full Request Details
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ITEMS TABLE -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">Order Items</h3>
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (!empty($details)): ?>
                        <?php 
                        $grandTotal = 0;
                        foreach ($details as $detail): 
                            $grandTotal += $detail['total'];
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($detail['kodebarang'] ?? '-') ?></td>
                            <td class="border px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($detail['nama_barang'] ?? '-') ?></td>
                            <td class="border px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($detail['detail_keterangan'] ?? '-') ?></td>
                            <td class="border px-4 py-3 text-sm text-gray-900">Rp <?= number_format($detail['harga'] ?? 0, 0, ',', '.') ?></td>
                            <td class="border px-4 py-3 text-sm text-gray-900"><?= number_format($detail['qty'] ?? 0, 0, ',', '.') ?></td>
                            <td class="border px-4 py-3 text-sm text-gray-900">Rp <?= number_format($detail['total'] ?? 0, 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="5" class="border px-4 py-3 text-right">Grand Total:</td>
                            <td class="border px-4 py-3">Rp <?= number_format($grandTotal, 0, ',', '.') ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="border px-4 py-3 text-center text-gray-500">No items found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
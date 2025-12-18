<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$trackingData = null;
$detailItems = [];
$orderItems = [];
$completionPercentage = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['idrequest'])) {
    $idrequest = trim($_POST['idrequest']);

    // Ambil data purchase request dengan status terakhir dari logstatusreq
    $stmt = $pdo->prepare("
        SELECT 
            pr.*, 
            u.nama AS nama_supervisor,
            ls.status AS status_code,
            CASE ls.status
                WHEN 1 THEN 'Process Approval Leader'
                WHEN 2 THEN 'Process Approval Manager'
                WHEN 3 THEN 'Approved'
                WHEN 4 THEN 'Hold'
                WHEN 5 THEN 'Reject'
                WHEN 6 THEN 'Done'
                ELSE 'Pending'
            END AS status_request,
            ls.date AS tanggal_status,
            ls.note_reject
        FROM purchaserequest pr
        LEFT JOIN users u ON pr.idsupervisor = u.iduser
        LEFT JOIN LATERAL (
            SELECT status, date, note_reject
            FROM logstatusreq 
            WHERE idrequest = pr.idrequest
            ORDER BY date DESC 
            LIMIT 1
        ) ls ON TRUE
        WHERE pr.idrequest = ?
    ");

    $stmt->execute([$idrequest]);
    $trackingData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($trackingData) {
        // Ambil detail request items
        $stmtDetail = $pdo->prepare("
            SELECT dr.*, mb.kodebarang, mb.nama_barang
            FROM detailrequest dr
            LEFT JOIN m_barang mb ON dr.idbarang = mb.idbarang
            WHERE dr.idrequest = ?
        ");
        $stmtDetail->execute([$idrequest]);
        $detailItems = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

        // Ambil data purchase order terkait dengan status dari logstatusorder
        $stmtOrder = $pdo->prepare("
            SELECT 
                po.*,
                dr.namaitem,
                dr.qty as qty_request,
                dr.harga,
                dr.total,
                dr.kodeproject,
                mb.kodebarang,
                los.status AS status_code,
                CASE los.status
                    WHEN 1 THEN 'Process Order'
                    WHEN 2 THEN 'Process Payment'
                    WHEN 3 THEN 'Process Delivery'
                    WHEN 4 THEN 'Arrived'
                    ELSE 'Pending'
                END AS status_po,
                los.date AS tanggal_status_order,
                los.keterangan
            FROM purchaseorder po
            LEFT JOIN detailrequest dr ON po.idrequest = dr.idrequest
            LEFT JOIN m_barang mb ON dr.idbarang = mb.idbarang
            LEFT JOIN LATERAL (
                SELECT status, date, keterangan
                FROM logstatusorder
                WHERE idpurchaseorder = po.idpurchaseorder
                ORDER BY date DESC
                LIMIT 1
            ) los ON TRUE
            WHERE po.idrequest = ?
        ");
        $stmtOrder->execute([$idrequest]);
        $orderItems = $stmtOrder->fetchAll(PDO::FETCH_ASSOC);

        // Hitung completion percentage berdasarkan logstatusbarang
        $stmtCompletion = $pdo->prepare("
            SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN lsb.status = 4 THEN 1 END) as items_arrived
            FROM detailrequest dr
            LEFT JOIN LATERAL (
                SELECT status
                FROM logstatusbarang
                WHERE iddetailrequest = dr.iddetailrequest
                ORDER BY date DESC
                LIMIT 1
            ) lsb ON TRUE
            WHERE dr.idrequest = ?
        ");
        $stmtCompletion->execute([$idrequest]);
        $completion = $stmtCompletion->fetch(PDO::FETCH_ASSOC);

        if ($completion['total_items'] > 0) {
            $completionPercentage = round(($completion['items_arrived'] / $completion['total_items']) * 100);
        }

        // Update status menjadi 'Done' jika completion 100%
        if ($completionPercentage == 100 && $trackingData['status_code'] != 6) {
            $updateStatus = $pdo->prepare("
                INSERT INTO logstatusreq (idrequest, status, date, note_reject)
                VALUES (?, 6, NOW(), 'Auto-completed: All items arrived')
            ");
            $updateStatus->execute([$idrequest]);
            
            // Refresh tracking data
            $stmt->execute([$idrequest]);
            $trackingData = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Function untuk menentukan warna status
function getStatusColor($status) {
    $colors = [
        'Process Approval Leader' => 'bg-yellow-100 text-yellow-800',
        'Process Approval Manager' => 'bg-blue-100 text-blue-800',
        'Approved' => 'bg-green-100 text-green-800',
        'Hold' => 'bg-orange-100 text-orange-800',
        'Reject' => 'bg-red-100 text-red-800',
        'Done' => 'bg-green-200 text-green-900',
        'Pending' => 'bg-gray-100 text-gray-800',
        'Process Order' => 'bg-yellow-100 text-yellow-800',
        'Process Payment' => 'bg-blue-100 text-blue-800',
        'Process Delivery' => 'bg-purple-100 text-purple-800',
        'Arrived' => 'bg-green-100 text-green-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

// Function untuk status description
function getStatusDescription($status) {
    $descriptions = [
        'Process Approval Leader' => 'Menunggu approval dari Leader',
        'Process Approval Manager' => 'Menunggu approval dari Manager',
        'Approved' => 'Purchase Request telah disetujui Manager',
        'Hold' => 'Purchase Request ditahan sementara',
        'Reject' => 'Purchase Request ditolak',
        'Done' => 'Semua barang telah terpenuhi (100%)',
        'Pending' => 'Menunggu proses approval'
    ];
    return $descriptions[$status] ?? 'Status tidak diketahui';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lacak Purchase Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-50">

<div class="max-w-7xl mx-auto p-6">

    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <img src="../images/logo_ipo.png" alt="Logo" class="h-12">
            <h1 class="text-3xl font-bold text-gray-900">Lacak Purchase Request-Mu Disini</h1>
        </div>
        <a href="../login.php" class="text-blue-600 hover:underline">Kembali</a>
    </div>

    <!-- FORM SEARCH -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="POST" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block font-semibold mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    Masukkan No Purchase Request Disini
                </label>
                <input 
                    type="text" 
                    name="idrequest" 
                    placeholder="Contoh: PR001"
                    class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-blue-500 focus:outline-none"
                    required
                    value="<?= htmlspecialchars($_POST['idrequest'] ?? '') ?>"
                >
            </div>
            <button type="submit" class="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                Lacak Status
            </button>
        </form>
    </div>

    <?php if ($trackingData): ?>
        
        <!-- INFO HEADER -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <!-- Info Purchase Request -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Supervisor</p>
                        <p class="font-semibold text-lg"><?= htmlspecialchars($trackingData['nama_supervisor'] ?? 'undefined') ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Tgl Buat</p>
                        <p class="font-semibold text-lg"><?= date('d M Y H:i', strtotime($trackingData['tgl_req'] ?? 'now')) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Requestor</p>
                        <p class="font-semibold text-lg"><?= htmlspecialchars($trackingData['namarequestor'] ?? 'undefined') ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Tgl Dibutuhkan</p>
                        <p class="font-semibold text-lg"><?= date('d M Y', strtotime($trackingData['tgl_butuh'] ?? 'now')) ?></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-sm text-gray-600 mb-2">Status Saat Ini</p>
                        <div class="flex items-center gap-3">
                            <span class="px-4 py-2 rounded-full font-semibold <?= getStatusColor($trackingData['status_request']) ?>">
                                <?= htmlspecialchars($trackingData['status_request']) ?>
                            </span>
                            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="toggleInfo()">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        <p id="statusInfo" class="text-sm text-gray-600 mt-2 hidden italic">
                            <?= getStatusDescription($trackingData['status_request']) ?>
                        </p>
                        <?php if ($trackingData['note_reject']): ?>
                        <div class="mt-2 p-3 bg-red-50 border border-red-200 rounded">
                            <p class="text-sm font-semibold text-red-800">Catatan Reject:</p>
                            <p class="text-sm text-red-700"><?= htmlspecialchars($trackingData['note_reject']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Completion Percentage -->
            <div class="bg-white rounded-lg shadow p-6 flex flex-col items-center justify-center">
                <canvas id="completionChart" width="150" height="150"></canvas>
                <p class="text-gray-600 mt-3 text-center">Completion Progress</p>
            </div>

        </div>

        <!-- TABEL DETAIL REQUEST -->
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <div class="p-4 bg-gray-50 border-b">
                <h2 class="text-xl font-bold">Detail Purchase Request</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Kode Barang</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Kode Project</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Nama Barang</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Harga</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Qty</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (!empty($detailItems)): ?>
                            <?php foreach ($detailItems as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm"><?= htmlspecialchars($item['kodebarang'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-sm"><?= htmlspecialchars($item['kodeproject'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-sm"><?= htmlspecialchars($item['namaitem'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-sm">Rp <?= number_format($item['harga'] ?? 0, 0, ',', '.') ?></td>
                                <td class="px-4 py-3 text-sm"><?= $item['qty'] ?? 0 ?></td>
                                <td class="px-4 py-3 text-sm font-semibold">Rp <?= number_format($item['total'] ?? 0, 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada data detail request</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABEL PURCHASE ORDER -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 bg-gray-50 border-b">
                <h2 class="text-xl font-bold">Berikut adalah informasi Purchase Order yang terkait</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold">No PO</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Kode Barang</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Kode Project</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Nama Barang</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Harga</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Qty</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Total</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Status PO</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (!empty($orderItems)): ?>
                            <?php foreach ($orderItems as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-semibold"><?= htmlspecialchars($order['nopo'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-sm"><?= htmlspecialchars($order['kodebarang'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-sm"><?= htmlspecialchars($order['kodeproject'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-sm"><?= htmlspecialchars($order['namaitem'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-sm">Rp <?= number_format($order['harga'] ?? 0, 0, ',', '.') ?></td>
                                <td class="px-4 py-3 text-sm"><?= $order['qty_request'] ?? 0 ?></td>
                                <td class="px-4 py-3 text-sm font-semibold">Rp <?= number_format($order['total'] ?? 0, 0, ',', '.') ?></td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs font-semibold <?= getStatusColor($order['status_po'] ?? 'Pending') ?>">
                                        <?= htmlspecialchars($order['status_po'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($order['keterangan'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500">Belum ada Purchase Order terkait</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($_SERVER["REQUEST_METHOD"] === "POST"): ?>
        
        <!-- NOT FOUND MESSAGE -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <svg class="w-16 h-16 mx-auto text-red-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <h3 class="text-xl font-bold text-red-900 mb-2">Purchase Request Tidak Ditemukan</h3>
            <p class="text-red-700">ID Request "<?= htmlspecialchars($_POST['idrequest']) ?>" tidak ada dalam sistem.</p>
        </div>

    <?php endif; ?>

</div>

<script>
// Toggle status info
function toggleInfo() {
    const info = document.getElementById('statusInfo');
    info.classList.toggle('hidden');
}

// Chart.js Donut Chart
<?php if ($trackingData): ?>
const ctx = document.getElementById('completionChart').getContext('2d');
const completionChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Remaining'],
        datasets: [{
            data: [<?= $completionPercentage ?>, <?= 100 - $completionPercentage ?>],
            backgroundColor: ['#10b981', '#e5e7eb'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '70%',
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                enabled: true
            }
        }
    },
    plugins: [{
        beforeDraw: function(chart) {
            const width = chart.width;
            const height = chart.height;
            const ctx = chart.ctx;
            ctx.restore();
            const fontSize = (height / 114).toFixed(2);
            ctx.font = fontSize + "em sans-serif";
            ctx.textBaseline = "middle";
            const text = "<?= $completionPercentage ?>%";
            const textX = Math.round((width - ctx.measureText(text).width) / 2);
            const textY = height / 2;
            ctx.fillText(text, textX, textY);
            ctx.save();
        }
    }]
});
<?php endif; ?>
</script>

</body>
</html>
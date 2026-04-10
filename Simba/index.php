<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in
$auth->checkAccess();

// Initialize variables
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
$selectedYear = $_GET['year'] ?? 0;
$selectedMonth = $_GET['month'] ?? 0;

// Query untuk Overview - Total Amount Purchase Order
$queryTotalPO = "
    SELECT 
        COALESCE(SUM(dor.total), 0) as total_amount
    FROM purchaseorder po
    LEFT JOIN detailorder dor ON po.idpurchaseorder = dor.idpurchaseorder
    WHERE 1=1
        AND (:year = 0 OR YEAR(po.created_at) = :year)
        AND (:month = 0 OR MONTH(po.created_at) = :month)
";

$stmtTotalPO = $pdo->prepare($queryTotalPO);
$stmtTotalPO->execute([
    ':year' => $selectedYear,
    ':month' => $selectedMonth
]);
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
        FROM logstatusreq l1
        WHERE l1.date = (
            SELECT MAX(l2.date)
            FROM logstatusreq l2
            WHERE l2.idrequest = l1.idrequest
        )
    ) ls ON pr.idrequest = ls.idrequest
    WHERE 1=1
        AND COALESCE(ls.status, 0) NOT IN (3, 6)
        AND (:year = 0 OR YEAR(pr.tgl_req) = :year)
        AND (:month = 0 OR MONTH(pr.tgl_req) = :month)
";

$stmtRemainPR = $pdo->prepare($queryRemainPR);
$stmtRemainPR->execute([
    ':year' => $selectedYear,
    ':month' => $selectedMonth
]);
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
        FROM logstatusreq l1
        WHERE l1.date = (
            SELECT MAX(l2.date)
            FROM logstatusreq l2
            WHERE l2.idrequest = l1.idrequest
        )
    ) ls ON pr.idrequest = ls.idrequest
    WHERE 1=1
        AND (:year = 0 OR YEAR(pr.tgl_req) = :year)
        AND (:month = 0 OR MONTH(pr.tgl_req) = :month)
    GROUP BY ls.status
    ORDER BY ls.status
";

$stmtPRStatus = $pdo->prepare($queryPRStatus);
$stmtPRStatus->execute([
    ':year' => $selectedYear,
    ':month' => $selectedMonth
]);
$prStatusData = $stmtPRStatus->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for chart
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

$title = 'Procurement';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php if ($auth->isLoggedIn()): ?>
    <div class="app-container">
        <header class="app-header">
            <div class="header-left">
                <button class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Procurement</h1>
            </div>
            <div class="header-right">
                <div class="user-menu">
                    <span class="user-name">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <div class="user-dropdown">
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container-fluid">
                
                <!-- TABS NAVIGATION -->
                <div class="tabs-container">
                    <ul class="nav-tabs">
                        <li class="<?= $activeTab == 'overview' ? 'active' : '' ?>">
                            <a href="?tab=overview">Overview</a>
                        </li>
                        <li class="<?= $activeTab == 'purchase-request' ? 'active' : '' ?>">
                            <a href="?tab=purchase-request">Purchase Request</a>
                        </li>
                        <li class="<?= $activeTab == 'purchase-order' ? 'active' : '' ?>">
                            <a href="?tab=purchase-order">Purchase Order</a>
                        </li>
                    </ul>
                </div>

                <!-- OVERVIEW TAB -->
                <div id="content-overview" class="tab-content <?= $activeTab != 'overview' ? 'hidden' : '' ?>">
                    
                    <!-- Filter Section -->
                    <div class="filter-section" style="display: flex; justify-content: flex-end; align-items: flex-end; margin-bottom: 20px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="display: flex; gap: 15px;">                            <div>
                                <label class="form-label">Month</label>
                                <select onchange="filterData()" id="filter-month" class="form-control" style="width: 200px;">
                                    <option value="0">Select option</option>
                                    <option value="1" <?= $selectedMonth == 1 ? 'selected' : '' ?>>January</option>
                                    <option value="2" <?= $selectedMonth == 2 ? 'selected' : '' ?>>February</option>
                                    <option value="3" <?= $selectedMonth == 3 ? 'selected' : '' ?>>March</option>
                                    <option value="4" <?= $selectedMonth == 4 ? 'selected' : '' ?>>April</option>
                                    <option value="5" <?= $selectedMonth == 5 ? 'selected' : '' ?>>May</option>
                                    <option value="6" <?= $selectedMonth == 6 ? 'selected' : '' ?>>June</option>
                                    <option value="7" <?= $selectedMonth == 7 ? 'selected' : '' ?>>July</option>
                                    <option value="8" <?= $selectedMonth == 8 ? 'selected' : '' ?>>August</option>
                                    <option value="9" <?= $selectedMonth == 9 ? 'selected' : '' ?>>September</option>
                                    <option value="10" <?= $selectedMonth == 10 ? 'selected' : '' ?>>October</option>
                                    <option value="11" <?= $selectedMonth == 11 ? 'selected' : '' ?>>November</option>
                                    <option value="12" <?= $selectedMonth == 12 ? 'selected' : '' ?>>December</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label">Year</label>
                                <select onchange="filterData()" id="filter-year" class="form-control" style="width: 200px;">
                                    <option value="0">Select option</option>
                                    <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Cards Section -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                        
                        <!-- Total Amount Purchase Order Card -->
                        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #3b82f6;">
                            <h3 style="color: #3b82f6; font-size: 18px; font-weight: 600; margin-bottom: 15px;">Total amount Purchase Order</h3>
                            <div style="font-size: 36px; font-weight: bold; color: #1f2937;">
                                IDR <?= number_format($totalPO['total_amount'], 0, ',', '.') ?>
                            </div>
                        </div>

                        <!-- Remain Purchase Request Card -->
                        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #1f2937;">Remain Purchase Request</h3>
                            <div style="height: 300px; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($prStatusData)): ?>
                                <canvas id="chartRemainPR"></canvas>
                                <?php else: ?>
                                <p style="color: #9ca3af;">No data to display</p>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- PURCHASE REQUEST TAB -->
                <div id="content-purchase-request" class="tab-content <?= $activeTab != 'purchase-request' ? 'hidden' : '' ?>">
                    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-height: 500px;">
                        <div style="height: 400px; display: flex; align-items: center; justify-content: center; border: 2px dashed #e5e7eb; border-radius: 8px;">
                            <p style="color: #9ca3af; font-size: 18px;">No data to display</p>
                        </div>
                    </div>
                </div>

                <!-- PURCHASE ORDER TAB -->
                <div id="content-purchase-order" class="tab-content <?= $activeTab != 'purchase-order' ? 'hidden' : '' ?>">
                    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-height: 500px;">
                        <div style="height: 400px; display: flex; align-items: center; justify-content: center; border: 2px dashed #e5e7eb; border-radius: 8px;">
                            <p style="color: #9ca3af; font-size: 18px;">No data to display</p>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
    
    <script>
        // Toggle sidebar
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.app-sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
        
        // Filter data
        function filterData() {
            const month = document.getElementById('filter-month').value;
            const year = document.getElementById('filter-year').value;
            window.location.href = `procurement.php?tab=overview&month=${month}&year=${year}`;
        }

        // Download report
        function downloadReport() {
            alert('Download functionality will be implemented');
        }

        // Chart for Remain Purchase Request
        <?php if (!empty($prStatusData)): ?>
        const ctx = document.getElementById('chartRemainPR');
        if (ctx) {
            new Chart(ctx, {
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
                    maintainAspectRatio: true,
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
    <?php endif; ?>
</body>
</html>
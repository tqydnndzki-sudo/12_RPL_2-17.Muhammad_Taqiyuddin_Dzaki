<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in
$auth->checkAccess();

// Handle POST requests for add/edit operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // HANDLE TAB INVENTORY - Update m_barang
    if (isset($_POST['add_item_inventory'])) {
        $idbarang = $_POST['idbarang'] ?? '';
        $kodebarang = $_POST['kodebarang'] ?? '';
        $nama_barang = $_POST['nama_barang'] ?? '';
        $idkategori = $_POST['idkategori'] ?? null;
        $lokasi = $_POST['lokasi'] ?? '';
        $kodeproject = $_POST['kodeproject'] ?? '';
        $harga = $_POST['harga'] ?? 0;
        $stok_awal = $_POST['stok_awal'] ?? 0;
        $stok_akhir = $_POST['stok_akhir'] ?? 0;
        $keterangan = $_POST['keterangan'] ?? '';
        
        // Generate ID Inventory
        $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
        $count = $stmt->fetchColumn();
        $idinventory = 'INV' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        
        // Calculate total
        $total = $harga * $stok_akhir;
        
        try {
            $pdo->beginTransaction();
            
            // Insert to m_barang first
            $stmt = $pdo->prepare("INSERT INTO m_barang (idbarang, kodebarang, nama_barang, harga, satuan, idkategori) VALUES (?, ?, ?, ?, 'PCS', ?)");
            $stmt->execute([$idbarang, $kodebarang, $nama_barang, $harga, $idkategori]);
            
            // Insert to inventory
            $stmt = $pdo->prepare("
                INSERT INTO inventory (
                    idinventory, idbarang, kodebarang, nama_barang, 
                    idkategori, lokasi, kodeproject, harga, 
                    stok_awal, stok_akhir, qty_in, qty_out, total, keterangan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)
            ");
            $stmt->execute([
                $idinventory, $idbarang, $kodebarang, $nama_barang,
                $idkategori, $lokasi, $kodeproject, $harga,
                $stok_awal, $stok_akhir, $total, $keterangan
            ]);
            
            $pdo->commit();
            
            header('Location: inventory.php?tab=inventory&message=Item berhasil ditambahkan&message_type=success');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: inventory.php?tab=inventory&message=Error: ' . urlencode($e->getMessage()) . '&message_type=error');
            exit;
        }
    } elseif (isset($_POST['edit_item_inventory'])) {
        $idbarang = $_POST['idbarang'] ?? '';
        $kodebarang = $_POST['kodebarang'] ?? '';
        $nama_barang = $_POST['nama_barang'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $harga = $_POST['harga'] ?? 0;
        $satuan = $_POST['satuan'] ?? '';
        $kodeproject = $_POST['kodeproject'] ?? '';
        $idkategori = $_POST['idkategori'] ?? null;
        
        try {
            $stmt = $pdo->prepare("UPDATE m_barang SET kodebarang = ?, nama_barang = ?, deskripsi = ?, harga = ?, satuan = ?, kodeproject = ?, idkategori = ? WHERE idbarang = ?");
            $stmt->execute([$kodebarang, $nama_barang, $deskripsi, $harga, $satuan, $kodeproject, $idkategori, $idbarang]);
            
            header('Location: inventory.php?tab=inventory&message=Item berhasil diupdate&message_type=success');
            exit;
        } catch (Exception $e) {
            header('Location: inventory.php?tab=inventory&message=Error: ' . urlencode($e->getMessage()) . '&message_type=error');
            exit;
        }
    }
    
    // HANDLE TAB IN - Insert barang masuk
    if (isset($_POST['add_item_in'])) {
        $tgl_masuk = $_POST['tgl_masuk'] ?? date('Y-m-d');
        $idpurchaseorder = $_POST['idpurchaseorder'] ?? null;
        $idbarang = $_POST['idbarang'] ?? '';
        $jumlah = $_POST['jumlah'] ?? 0;
        
        // Validate idpurchaseorder if provided
        if (!empty($idpurchaseorder)) {
            // Check if PO exists
            $checkPO = $pdo->prepare("SELECT idpurchaseorder FROM purchaseorder WHERE idpurchaseorder = ?");
            $checkPO->execute([$idpurchaseorder]);
            if (!$checkPO->fetch()) {
                header('Location: inventory.php?tab=in&message=Error: ID Purchase Order tidak ditemukan&message_type=error');
                exit;
            }
        } else {
            // Set to NULL if empty
            $idpurchaseorder = null;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Insert to barangmasuk
            $idmasuk = 'BM' . date('YmdHis');
            $stmt = $pdo->prepare("INSERT INTO barangmasuk (idmasuk, tgl_masuk, idpurchaseorder) VALUES (?, ?, ?)");
            $stmt->execute([$idmasuk, $tgl_masuk, $idpurchaseorder]);
            
            // Insert to detailmasuk
            $stmt = $pdo->prepare("INSERT INTO detailmasuk (idmasuk, idbarang, jumlah) VALUES (?, ?, ?)");
            $stmt->execute([$idmasuk, $idbarang, $jumlah]);
            
            // Update inventory stok_akhir
            $stmt = $pdo->prepare("UPDATE inventory SET stok_akhir = stok_akhir + ? WHERE idbarang = ?");
            $stmt->execute([$jumlah, $idbarang]);
            
            $pdo->commit();
            
            header('Location: inventory.php?tab=in&message=Barang masuk berhasil ditambahkan&message_type=success');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: inventory.php?tab=in&message=Error: ' . urlencode($e->getMessage()) . '&message_type=error');
            exit;
        }
    } elseif (isset($_POST['edit_item_in'])) {
        $idmasuk = $_POST['idmasuk'] ?? '';
        $tgl_masuk = $_POST['tgl_masuk'] ?? date('Y-m-d');
        $idpurchaseorder = $_POST['idpurchaseorder'] ?? null;
        $idbarang = $_POST['idbarang'] ?? '';
        $jumlah = $_POST['jumlah'] ?? 0;
        
        // Validate idpurchaseorder if provided
        if (!empty($idpurchaseorder)) {
            // Check if PO exists
            $checkPO = $pdo->prepare("SELECT idpurchaseorder FROM purchaseorder WHERE idpurchaseorder = ?");
            $checkPO->execute([$idpurchaseorder]);
            if (!$checkPO->fetch()) {
                header('Location: inventory.php?tab=in&message=Error: ID Purchase Order tidak ditemukan&message_type=error');
                exit;
            }
        } else {
            // Set to NULL if empty
            $idpurchaseorder = null;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get old quantity
            $stmt = $pdo->prepare("SELECT jumlah FROM detailmasuk WHERE idmasuk = ?");
            $stmt->execute([$idmasuk]);
            $oldQty = $stmt->fetchColumn();
            
            // Update barangmasuk
            $stmt = $pdo->prepare("UPDATE barangmasuk SET tgl_masuk = ?, idpurchaseorder = ? WHERE idmasuk = ?");
            $stmt->execute([$tgl_masuk, $idpurchaseorder, $idmasuk]);
            
            // Update detailmasuk
            $stmt = $pdo->prepare("UPDATE detailmasuk SET idbarang = ?, jumlah = ? WHERE idmasuk = ?");
            $stmt->execute([$idbarang, $jumlah, $idmasuk]);
            
            // Update inventory (subtract old, add new)
            $stmt = $pdo->prepare("UPDATE inventory SET stok_akhir = stok_akhir - ? + ? WHERE idbarang = ?");
            $stmt->execute([$oldQty, $jumlah, $idbarang]);
            
            $pdo->commit();
            
            header('Location: inventory.php?tab=in&message=Barang masuk berhasil diupdate&message_type=success');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: inventory.php?tab=in&message=Error: ' . urlencode($e->getMessage()) . '&message_type=error');
            exit;
        }
    }
    
    // HANDLE TAB OUT - Insert barang keluar
    if (isset($_POST['add_item_out'])) {
        $tgl_keluar = $_POST['tgl_keluar'] ?? date('Y-m-d');
        $idbarang = $_POST['idbarang'] ?? '';
        $jumlah = $_POST['jumlah'] ?? 0;
        $keterangan = $_POST['keterangan'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            // Check stock availability
            $stmt = $pdo->prepare("SELECT stok_akhir FROM inventory WHERE idbarang = ?");
            $stmt->execute([$idbarang]);
            $currentStock = $stmt->fetchColumn();
            
            if ($currentStock < $jumlah) {
                throw new Exception('Stok tidak mencukupi!');
            }
            
            // Insert to barangkeluar
            $idkeluar = 'BK' . date('YmdHis');
            $stmt = $pdo->prepare("INSERT INTO barangkeluar (idkeluar, tgl_keluar, keterangan) VALUES (?, ?, ?)");
            $stmt->execute([$idkeluar, $tgl_keluar, $keterangan]);
            
            // Insert to detailkeluar
            $stmt = $pdo->prepare("INSERT INTO detailkeluar (idkeluar, idbarang, jumlah) VALUES (?, ?, ?)");
            $stmt->execute([$idkeluar, $idbarang, $jumlah]);
            
            // Update inventory stok_akhir
            $stmt = $pdo->prepare("UPDATE inventory SET stok_akhir = stok_akhir - ? WHERE idbarang = ?");
            $stmt->execute([$jumlah, $idbarang]);
            
            $pdo->commit();
            
            header('Location: inventory.php?tab=out&message=Barang keluar berhasil ditambahkan&message_type=success');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: inventory.php?tab=out&message=Error: ' . urlencode($e->getMessage()) . '&message_type=error');
            exit;
        }
    } elseif (isset($_POST['edit_item_out'])) {
        $idkeluar = $_POST['idkeluar'] ?? '';
        $tgl_keluar = $_POST['tgl_keluar'] ?? date('Y-m-d');
        $idbarang = $_POST['idbarang'] ?? '';
        $jumlah = $_POST['jumlah'] ?? 0;
        $keterangan = $_POST['keterangan'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            // Get old quantity
            $stmt = $pdo->prepare("SELECT jumlah FROM detailkeluar WHERE idkeluar = ?");
            $stmt->execute([$idkeluar]);
            $oldQty = $stmt->fetchColumn();
            
            // Check stock availability (add back old, subtract new)
            $stmt = $pdo->prepare("SELECT stok_akhir FROM inventory WHERE idbarang = ?");
            $stmt->execute([$idbarang]);
            $currentStock = $stmt->fetchColumn();
            
            if (($currentStock + $oldQty) < $jumlah) {
                throw new Exception('Stok tidak mencukupi!');
            }
            
            // Update barangkeluar
            $stmt = $pdo->prepare("UPDATE barangkeluar SET tgl_keluar = ?, keterangan = ? WHERE idkeluar = ?");
            $stmt->execute([$tgl_keluar, $keterangan, $idkeluar]);
            
            // Update detailkeluar
            $stmt = $pdo->prepare("UPDATE detailkeluar SET idbarang = ?, jumlah = ? WHERE idkeluar = ?");
            $stmt->execute([$idbarang, $jumlah, $idkeluar]);
            
            // Update inventory (add back old, subtract new)
            $stmt = $pdo->prepare("UPDATE inventory SET stok_akhir = stok_akhir + ? - ? WHERE idbarang = ?");
            $stmt->execute([$oldQty, $jumlah, $idbarang]);
            
            $pdo->commit();
            
            header('Location: inventory.php?tab=out&message=Barang keluar berhasil diupdate&message_type=success');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: inventory.php?tab=out&message=Error: ' . urlencode($e->getMessage()) . '&message_type=error');
            exit;
        }
    }
}

// Initialize variables
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

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

// Query untuk Total Inventory
$queryTotalInventory = "
    SELECT 
        COALESCE(SUM(i.total), 0) as total_value,
        COALESCE(SUM(i.stok_akhir), 0) as total_qty
    FROM inventory i
    WHERE 1=1
";

$stmtTotalInventory = $pdo->prepare($queryTotalInventory);
$stmtTotalInventory->execute();
$totalInventory = $stmtTotalInventory->fetch(PDO::FETCH_ASSOC);

// Debug total inventory
// echo "<div>Total Inventory Query: " . $queryTotalInventory . "</div>";

// Query untuk Total Items In
$queryTotalIn = "
    SELECT 
        COALESCE(SUM(dm.total), 0) as total_value,
        COALESCE(SUM(dm.qty), 0) as total_qty
    FROM detailmasuk dm
    LEFT JOIN barangmasuk bm ON dm.idmasuk = bm.idmasuk
    WHERE 1=1
        " . ($selectedYear > 0 ? "AND YEAR(bm.tgl_masuk) = :year" : "") . "
        " . ($selectedMonth > 0 ? "AND MONTH(bm.tgl_masuk) = :month" : "") . "
";

$stmtTotalIn = $pdo->prepare($queryTotalIn);
$params = [];
if ($selectedYear > 0) {
    $params[':year'] = $selectedYear;
}
if ($selectedMonth > 0) {
    $params[':month'] = $selectedMonth;
}
$stmtTotalIn->execute($params);
$totalIn = $stmtTotalIn->fetch(PDO::FETCH_ASSOC);

// Debug total in
// echo "<div>Total In Query: " . $queryTotalIn . "</div>";
// echo "<div>Params: " . print_r($params, true) . "</div>";
// echo "<div>Selected Year: " . $selectedYear . ", Selected Month: " . $selectedMonth . "</div>";

// Query untuk Total Items Out
$queryTotalOut = "
    SELECT 
        COALESCE(SUM(dk.total), 0) as total_value,
        COALESCE(SUM(dk.qty), 0) as total_qty
    FROM detailkeluar dk
    LEFT JOIN barangkeluar bk ON dk.idkeluar = bk.idkeluar
    WHERE 1=1
        " . ($selectedYear > 0 ? "AND YEAR(bk.tgl_keluar) = :year" : "") . "
        " . ($selectedMonth > 0 ? "AND MONTH(bk.tgl_keluar) = :month" : "") . "
";

$stmtTotalOut = $pdo->prepare($queryTotalOut);
$params = [];
if ($selectedYear > 0) {
    $params[':year'] = $selectedYear;
}
if ($selectedMonth > 0) {
    $params[':month'] = $selectedMonth;
}
$stmtTotalOut->execute($params);
$totalOut = $stmtTotalOut->fetch(PDO::FETCH_ASSOC);

// Debug total out
// echo "<div>Total Out Query: " . $queryTotalOut . "</div>";
// echo "<div>Params: " . print_r($params, true) . "</div>";
// echo "<div>Selected Year: " . $selectedYear . ", Selected Month: " . $selectedMonth . "</div>";

// Query untuk Monthly Chart Data (Bar Chart)
$queryMonthlyData = "
    SELECT 
        months.month_num,
        CASE months.month_num
            WHEN 1 THEN 'January'
            WHEN 2 THEN 'February'
            WHEN 3 THEN 'March'
            WHEN 4 THEN 'April'
            WHEN 5 THEN 'May'
            WHEN 6 THEN 'June'
            WHEN 7 THEN 'July'
            WHEN 8 THEN 'August'
            WHEN 9 THEN 'September'
            WHEN 10 THEN 'October'
            WHEN 11 THEN 'November'
            WHEN 12 THEN 'December'
        END as month_name,
        COALESCE(SUM(dm.total), 0) as total_in,
        COALESCE(SUM(dk.total), 0) as total_out
    FROM (
        SELECT 1 as month_num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION 
        SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION 
        SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
    ) months
    LEFT JOIN barangmasuk bm ON MONTH(bm.tgl_masuk) = months.month_num " . ($selectedYear > 0 ? "AND YEAR(bm.tgl_masuk) = :chart_year" : "") . "
    LEFT JOIN detailmasuk dm ON dm.idmasuk = bm.idmasuk
    LEFT JOIN barangkeluar bk ON MONTH(bk.tgl_keluar) = months.month_num " . ($selectedYear > 0 ? "AND YEAR(bk.tgl_keluar) = :chart_year" : "") . "
    LEFT JOIN detailkeluar dk ON dk.idkeluar = bk.idkeluar
    GROUP BY months.month_num
    ORDER BY months.month_num
";

$stmtMonthlyData = $pdo->prepare($queryMonthlyData);
$params = [];
if ($selectedYear > 0) {
    $params[':chart_year'] = $selectedYear;
}
$stmtMonthlyData->execute($params);
$monthlyData = $stmtMonthlyData->fetchAll(PDO::FETCH_ASSOC);

// Debug monthly data
// echo "<pre>"; print_r($monthlyData); echo "</pre>";
// echo "<div>Monthly Chart Query: " . $queryMonthlyData . "</div>";
// echo "<div>Monthly Chart Params: " . print_r($params, true) . "</div>";

// Query untuk Category Chart Data (Doughnut Chart)
// Build query with year filter if selected
$yearCondition = "";
if ($selectedYear > 0) {
    $yearCondition = "AND (YEAR(bm.tgl_masuk) = :chart_year OR YEAR(bk.tgl_keluar) = :chart_year)";
}

$queryCategoryData = "
    SELECT 
        kb.nama_kategori,
        COALESCE(SUM(i.total), 0) as total_value
    FROM kategoribarang kb
    LEFT JOIN inventory i ON kb.idkategori = i.idkategori
    LEFT JOIN m_barang mb ON i.idbarang = mb.idbarang
    LEFT JOIN detailmasuk dm ON mb.idbarang = dm.idbarang
    LEFT JOIN barangmasuk bm ON dm.idmasuk = bm.idmasuk
    LEFT JOIN detailkeluar dk ON mb.idbarang = dk.idbarang
    LEFT JOIN barangkeluar bk ON dk.idkeluar = bk.idkeluar
    WHERE 1=1 $yearCondition
    GROUP BY kb.idkategori, kb.nama_kategori
    HAVING total_value > 0
    ORDER BY total_value DESC
    LIMIT 5
";

$stmtCategoryData = $pdo->prepare($queryCategoryData);
$params = [];
if ($selectedYear > 0) {
    $params[':chart_year'] = $selectedYear;
}
$stmtCategoryData->execute($params);
$categoryData = $stmtCategoryData->fetchAll(PDO::FETCH_ASSOC);

// Debug category data
// echo "<pre>"; print_r($categoryData); echo "</pre>";
// echo "<div>Category Chart Query: " . $queryCategoryData . "</div>";
// echo "<div>Category Chart Params: " . print_r($params, true) . "</div>";

// Initialize items array
$items = [];
$total_pages = 1;

// Queries based on active tab
if ($activeTab == 'inventory') {
    $searchCondition = $search ? "AND (mb.kodebarang LIKE :search OR mb.nama_barang LIKE :search)" : "";
    // Note: Inventory tab doesn't have date filtering as the inventory table doesn't have date columns
    
    $countQuery = "SELECT COUNT(*) FROM inventory i 
                   LEFT JOIN m_barang mb ON i.idbarang = mb.idbarang 
                   WHERE 1=1 $searchCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            i.idinventory,
            mb.kodebarang,
            i.kodeproject,
            k.nama_kategori as kategori,
            i.lokasi,
            mb.nama_barang,
            mb.harga,
            i.stok_awal,
            i.stok_akhir,
            i.qty_in,
            i.qty_out,
            i.total,
            i.keterangan
        FROM inventory i
        LEFT JOIN m_barang mb ON i.idbarang = mb.idbarang
        LEFT JOIN kategoribarang k ON mb.idkategori = k.idkategori
        WHERE 1=1 $searchCondition
        ORDER BY i.idinventory DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($activeTab == 'in') {
    $searchCondition = $search ? "AND (mb.kodebarang LIKE :search OR mb.nama_barang LIKE :search)" : "";
    $dateCondition = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $dateCondition = "AND MONTH(bm.tgl_masuk) = :month AND YEAR(bm.tgl_masuk) = :year";
    } elseif ($selectedMonth > 0) {
        $dateCondition = "AND MONTH(bm.tgl_masuk) = :month";
    } elseif ($selectedYear > 0) {
        $dateCondition = "AND YEAR(bm.tgl_masuk) = :year";
    }
    
    // Debug date condition
    // echo "<div>Date Condition for 'in' tab: " . $dateCondition . "</div>";
    
    $countQuery = "SELECT COUNT(*) FROM barangmasuk bm 
                   LEFT JOIN purchaseorder po ON bm.idpurchaseorder = po.idpurchaseorder
                   LEFT JOIN detailorder dor ON po.idpurchaseorder = dor.idpurchaseorder
                   LEFT JOIN m_barang mb ON dor.idbarang = mb.idbarang
                   WHERE 1=1 $searchCondition $dateCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            bm.idmasuk,
            bm.tgl_masuk,
            bm.idpurchaseorder,
            po.idpurchaseorder as nopo,
            mb.kodebarang,
            mb.nama_barang,
            po.supplier,
            bm.keterangan
        FROM barangmasuk bm
        LEFT JOIN purchaseorder po ON bm.idpurchaseorder = po.idpurchaseorder
        LEFT JOIN detailorder dor ON po.idpurchaseorder = dor.idpurchaseorder
        LEFT JOIN m_barang mb ON dor.idbarang = mb.idbarang
        WHERE 1=1 $searchCondition $dateCondition
        ORDER BY bm.idmasuk DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($activeTab == 'out') {
    $searchCondition = $search ? "AND (mb.kodebarang LIKE :search OR mb.nama_barang LIKE :search)" : "";
    $dateCondition = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $dateCondition = "AND MONTH(bk.tgl_keluar) = :month AND YEAR(bk.tgl_keluar) = :year";
    } elseif ($selectedMonth > 0) {
        $dateCondition = "AND MONTH(bk.tgl_keluar) = :month";
    } elseif ($selectedYear > 0) {
        $dateCondition = "AND YEAR(bk.tgl_keluar) = :year";
    }
    
    // Debug date condition
    // echo "<div>Date Condition for 'out' tab: " . $dateCondition . "</div>";
    
    $countQuery = "SELECT COUNT(*) FROM barangkeluar bk
                   LEFT JOIN detailkeluar dk ON bk.idkeluar = dk.idkeluar
                   LEFT JOIN m_barang mb ON dk.idbarang = mb.idbarang
                   WHERE 1=1 $searchCondition $dateCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            bk.idkeluar,
            bk.tgl_keluar,
            mb.kodebarang,
            mb.nama_barang,
            dk.qty,
            mb.harga,
            dk.total,
            bk.keterangan
        FROM barangkeluar bk
        LEFT JOIN detailkeluar dk ON bk.idkeluar = dk.idkeluar
        LEFT JOIN m_barang mb ON dk.idbarang = mb.idbarang
        WHERE 1=1 $searchCondition $dateCondition
        ORDER BY bk.idkeluar DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($activeTab == 'detail_masuk') {
    $searchCondition = $search ? "AND (mb.kodebarang LIKE :search OR mb.nama_barang LIKE :search)" : "";
    $dateCondition = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $dateCondition = "AND MONTH(bm.tgl_masuk) = :month AND YEAR(bm.tgl_masuk) = :year";
    } elseif ($selectedMonth > 0) {
        $dateCondition = "AND MONTH(bm.tgl_masuk) = :month";
    } elseif ($selectedYear > 0) {
        $dateCondition = "AND YEAR(bm.tgl_masuk) = :year";
    }
    
    // Debug date condition
    // echo "<div>Date Condition for 'detail_masuk' tab: " . $dateCondition . "</div>";
    
    $countQuery = "SELECT COUNT(*) FROM detailmasuk dm
                   LEFT JOIN m_barang mb ON dm.idbarang = mb.idbarang
                   LEFT JOIN barangmasuk bm ON dm.idmasuk = bm.idmasuk
                   WHERE 1=1 $searchCondition $dateCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            dm.iddetailmasuk,
            bm.tgl_masuk,
            mb.kodebarang,
            dm.idmasuk,
            dm.qty,
            dm.harga,
            dm.total
        FROM detailmasuk dm
        LEFT JOIN barangmasuk bm ON dm.idmasuk = bm.idmasuk
        LEFT JOIN m_barang mb ON dm.idbarang = mb.idbarang
        WHERE 1=1 $searchCondition $dateCondition
        ORDER BY dm.iddetailmasuk DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($activeTab == 'detail_keluar') {
    $searchCondition = $search ? "AND (mb.kodebarang LIKE :search OR mb.nama_barang LIKE :search)" : "";
    $dateCondition = "";
    if ($selectedMonth > 0 && $selectedYear > 0) {
        $dateCondition = "AND MONTH(bk.tgl_keluar) = :month AND YEAR(bk.tgl_keluar) = :year";
    } elseif ($selectedMonth > 0) {
        $dateCondition = "AND MONTH(bk.tgl_keluar) = :month";
    } elseif ($selectedYear > 0) {
        $dateCondition = "AND YEAR(bk.tgl_keluar) = :year";
    }
    
    // Debug date condition
    // echo "<div>Date Condition for 'detail_keluar' tab: " . $dateCondition . "</div>";
    
    $countQuery = "SELECT COUNT(*) FROM detailkeluar dk
                   LEFT JOIN m_barang mb ON dk.idbarang = mb.idbarang
                   LEFT JOIN barangkeluar bk ON dk.idkeluar = bk.idkeluar
                   WHERE 1=1 $searchCondition $dateCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $countStmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $countStmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    $stmt = $pdo->prepare("
        SELECT 
            dk.iddetailkeluar,
            bk.tgl_keluar,
            mb.kodebarang,
            dk.idkeluar,
            dk.qty,
            dk.harga,
            dk.total
        FROM detailkeluar dk
        LEFT JOIN barangkeluar bk ON dk.idkeluar = bk.idkeluar
        LEFT JOIN m_barang mb ON dk.idbarang = mb.idbarang
        WHERE 1=1 $searchCondition $dateCondition
        ORDER BY dk.iddetailkeluar DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    if ($selectedMonth > 0) $stmt->bindValue(':month', $selectedMonth, PDO::PARAM_INT);
    if ($selectedYear > 0) $stmt->bindValue(':year', $selectedYear, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$title = 'Inventory';

// Debug filter values
// echo "<div style='background: yellow; padding: 10px; margin: 10px;'>DEBUG: selectedYear = $selectedYear, selectedMonth = $selectedMonth</div>";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Internal Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
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
            <a href="inventory.php" class="flex items-center gap-3 px-4 py-3 bg-gray-100 text-gray-900 rounded-lg">
                <i class="fas fa-boxes w-5 h-5"></i>
                <span class="font-medium">Inventory</span>
            </a>
            
            <a href="procurement.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-shopping-cart w-5 h-5"></i>
                <span class="font-medium">Procurement</span>
            </a>
            
            <a href="master-data.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-database w-5 h-5"></i>
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
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Inventory</h1>

        <div class="bg-white rounded-lg shadow">
            <!-- TABS NAVIGATION -->
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <a href="?tab=overview" class="<?= $activeTab == 'overview' ? 'border-b-2 border-blue-600 text-blue-600' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' ?> py-4 px-6 font-medium text-sm">
                        Overview
                    </a>
                    <a href="?tab=inventory" class="<?= $activeTab == 'inventory' ? 'border-b-2 border-blue-600 text-blue-600' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' ?> py-4 px-6 font-medium text-sm">
                        Inventory
                    </a>
                    <a href="?tab=in" class="<?= $activeTab == 'in' ? 'border-b-2 border-blue-600 text-blue-600' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' ?> py-4 px-6 font-medium text-sm">
                        In
                    </a>
                    <a href="?tab=out" class="<?= $activeTab == 'out' ? 'border-b-2 border-blue-600 text-blue-600' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' ?> py-4 px-6 font-medium text-sm">
                        Out
                    </a>

                </nav>
            </div>

            <div class="p-6">
                
                <!-- Display Success/Error Messages -->
                <?php if (isset($_GET['message'])): ?>
                <div id="alertMessage" class="mb-4 p-4 rounded-lg <?= $_GET['message_type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>">
                    <div class="flex items-center">
                        <i class="fas <?= $_GET['message_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                        <span><?= htmlspecialchars($_GET['message']) ?></span>
                        <button onclick="document.getElementById('alertMessage').style.display='none'" class="ml-auto text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <script>
                    // Auto hide alert after 5 seconds
                    setTimeout(function() {
                        const alert = document.getElementById('alertMessage');
                        if (alert) alert.style.display = 'none';
                    }, 5000);
                </script>
                <?php endif; ?>

                <!-- OVERVIEW TAB -->
                <?php if ($activeTab == 'overview'): ?>
                <div>
                    <!-- Toolbar -->
                    <div class="flex justify-end items-center mb-6">
                        <div class="flex gap-4 items-center">
                            <div class="relative">
                                <input type="text" id="search-<?= $activeTab ?>" placeholder="Search..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64" onkeyup="handleSearch(event)">
                                <button onclick="performSearch()" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            
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

                    <!-- Cards -->
                    <div class="grid grid-cols-3 gap-6 mb-6">
                        <div class="bg-gradient-to-br from-blue-900 to-blue-700 p-6 rounded-xl text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 opacity-10 text-9xl"><i class="fas fa-boxes"></i></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 class="text-sm font-semibold opacity-90">Total Inventory</h3>
                                    <i class="fas fa-boxes text-2xl opacity-80"></i>
                                </div>
                                <div class="text-3xl font-bold mb-2">Rp <?= number_format($totalInventory['total_value'], 0, ',', '.') ?></div>
                                <div class="text-sm opacity-80"><?= number_format($totalInventory['total_qty']) ?> items</div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-teal-700 to-teal-500 p-6 rounded-xl text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 opacity-10 text-9xl"><i class="fas fa-arrow-down"></i></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 class="text-sm font-semibold opacity-90">Total Items In</h3>
                                    <i class="fas fa-arrow-down text-2xl opacity-80"></i>
                                </div>
                                <div class="text-3xl font-bold mb-2">Rp <?= number_format($totalIn['total_value'], 0, ',', '.') ?></div>
                                <div class="text-sm opacity-80"><?= number_format($totalIn['total_qty']) ?> items</div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-red-700 to-red-500 p-6 rounded-xl text-white relative overflow-hidden">
                            <div class="absolute top-0 right-0 opacity-10 text-9xl"><i class="fas fa-arrow-up"></i></div>
                            <div class="relative z-10">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 class="text-sm font-semibold opacity-90">Total Items Out</h3>
                                    <i class="fas fa-arrow-up text-2xl opacity-80"></i>
                                </div>
                                <div class="text-3xl font-bold mb-2">Rp <?= number_format($totalOut['total_value'], 0, ',', '.') ?></div>
                                <div class="text-sm opacity-80"><?= number_format($totalOut['total_qty']) ?> items</div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Bar Chart -->
                        <div class="bg-white p-4 rounded-lg shadow">
                            <h3 class="text-lg font-semibold mb-4">Monthly Trends</h3>
                            <div style="position: relative; height: 200px">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Doughnut Chart -->
                        <div class="bg-white p-4 rounded-lg shadow">
                            <h3 class="text-lg font-semibold mb-4">Inventory by Category</h3>
                            <div style="position: relative; height: 200px">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- INVENTORY TAB -->
                <?php if ($activeTab == 'inventory'): ?>
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
                    
                    <!-- Toolbar with Entries and Search -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Entries Dropdown -->
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-700 font-medium">Show</label>
                                <select id="entries-inventory" onchange="changeEntries('inventory')" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                <input type="text" id="search-inventory" placeholder="Search..." onkeyup="searchTable('inventory')" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                                <button onclick="clearSearch('inventory')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm table-container">
                        <table id="inventory-table" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('inventory', 0)" width="50">#<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('inventory', 1)">Kode Barang<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('inventory', 2)">Nama Barang<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('inventory', 3)">Kategori<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('inventory', 4)">Stok Akhir<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('inventory', 5)">Harga<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('inventory', 6)">Total<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer selectable-row" onclick="selectRow(this)">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><strong><?= htmlspecialchars($item['kodebarang'] ?? '-') ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($item['nama_barang'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($item['kategori'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-green-100 text-green-800 border border-green-200">
                                                <?= number_format($item['stok_akhir'] ?? 0) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">Rp <?= number_format($item['harga'] ?? 0, 0, ',', '.') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">Rp <?= number_format($item['total'] ?? 0, 0, ',', '.') ?></td>
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
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
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

                <!-- IN TAB -->
                <?php if ($activeTab == 'in'): ?>
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
                    
                    <!-- Toolbar with Entries and Search -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Entries Dropdown -->
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-700 font-medium">Show</label>
                                <select id="entries-in" onchange="changeEntries('in')" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                <input type="text" id="search-in" placeholder="Search..." onkeyup="searchTable('in')" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                                <button onclick="clearSearch('in')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm table-container">
                        <table id="in-table" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('in', 0)" width="50">#<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('in', 1)">Tanggal Masuk<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('in', 2)">No PO<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('in', 3)">Kode Barang<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('in', 4)">Nama Barang<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('in', 5)">Qty<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('in', 6)">Keterangan<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            No inbound transactions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer selectable-row" 
                                        data-id="<?= htmlspecialchars($item['idmasuk']) ?>"
                                        data-idpurchaseorder="<?= htmlspecialchars($item['idpurchaseorder'] ?? '') ?>"
                                        onclick="selectRow(this)">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= date('d M Y', strtotime($item['tgl_masuk'] ?? date('Y-m-d'))) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600"><?= htmlspecialchars($item['nopo'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><strong><?= htmlspecialchars($item['kodebarang'] ?? '-') ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($item['nama_barang'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-green-100 text-green-800 border border-green-200">
                                                <?= number_format($item['qty'] ?? $item['jumlah'] ?? 0) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
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
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
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
                
                <!-- OUT TAB -->
                <?php if ($activeTab == 'out'): ?>
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
                    
                    <!-- Toolbar with Entries and Search -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Entries Dropdown -->
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-700 font-medium">Show</label>
                                <select id="entries-out" onchange="changeEntries('out')" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                <input type="text" id="search-out" placeholder="Search..." onkeyup="searchTable('out')" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                                <button onclick="clearSearch('out')" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table -->
                    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm table-container">
                        <table id="out-table" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-blue-50 to-indigo-50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 0)" width="50">#<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 1)">Tanggal Keluar<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 2)">Kode Barang<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 3)">Nama Barang<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 4)">Qty<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 5)">Harga<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 6)">Total<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 cursor-pointer hover:bg-blue-100 transition" onclick="sortTable('out', 7)">Keterangan<i class="fas fa-sort sort-arrow text-gray-400 ml-1"></i></th>
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
                                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="selectRow(this)">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row_number++; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['tgl_keluar'] ?? '-'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><strong><?php echo htmlspecialchars($item['kodebarang'] ?? '-'); ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['nama_barang'] ?? '-'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['qty'] ?? 0); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['harga'] ?? 0, 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['total'] ?? 0, 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['keterangan'] ?? '-'); ?></td>
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
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
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
                
                                
                <!-- DETAIL KELUAR TAB -->
                <?php if ($activeTab == 'detail_keluar'): ?>
                <div>
                    <!-- Toolbar -->
                    <div class="flex justify-between items-center mb-6">

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

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" width="50">#</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Keluar</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Keluar</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row_number++; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['tgl_keluar'] ?? '-'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><strong><?php echo htmlspecialchars($item['kodebarang'] ?? '-'); ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['idkeluar'] ?? '-'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['qty'] ?? 0); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['harga'] ?? 0, 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['total'] ?? 0, 2); ?></td>
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
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
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
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $i ?>" class="<?php echo $i == $page ? 'relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
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

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Initialize charts if on overview tab
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($activeTab == 'overview'): ?>
        // Monthly Chart Data
        const monthlyDataIn = <?php echo json_encode(array_values(array_map(function($item) { 
            return $item['total_in']; 
        }, $monthlyData))); ?>;
        
        const monthlyDataOut = <?php echo json_encode(array_values(array_map(function($item) { 
            return $item['total_out']; 
        }, $monthlyData))); ?>;
        
        const monthlyLabels = <?php echo json_encode(array_values(array_map(function($item) { 
            return $item['month_name']; 
        }, $monthlyData))); ?>;
        
        // Category Chart Data
        const categoryLabels = <?php echo json_encode(array_values(array_map(function($item) { 
            return $item['nama_kategori']; 
        }, $categoryData))); ?>;
        
        const categoryData = <?php echo json_encode(array_values(array_map(function($item) { 
            return $item['total_value']; 
        }, $categoryData))); ?>;
        
        // Bar Chart
        const ctx1 = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Items In',
                    data: monthlyDataIn,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Items Out',
                    data: monthlyDataOut,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
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
                }
            }
        });
        
        // Doughnut Chart
        const ctx2 = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': Rp ' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    });

    // Tab switching functionality
    function showTab(tabName) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.classList.add('hidden'));
        
        // Show selected tab content
        const activeTab = document.getElementById(`content-${tabName}`);
        if (activeTab) {
            activeTab.classList.remove('hidden');
        }
        
        // Update active tab button
        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(button => button.classList.remove('border-blue-600', 'text-blue-600'));
        tabButtons.forEach(button => button.classList.add('border-transparent', 'text-gray-600', 'hover:text-gray-900', 'hover:border-gray-300'));
        
        const activeButton = document.getElementById(`tab-${tabName}`);
        if (activeButton) {
            activeButton.classList.remove('border-transparent', 'text-gray-600', 'hover:text-gray-900', 'hover:border-gray-300');
            activeButton.classList.add('border-blue-600', 'text-blue-600');
        }
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
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
    
    // Show add form function - opens different modal based on active tab
    function showAddForm() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'inventory';
        
        if (activeTab === 'in') {
            document.getElementById('addInModal').classList.remove('hidden');
        } else if (activeTab === 'out') {
            document.getElementById('addOutModal').classList.remove('hidden');
        } else {
            document.getElementById('addItemModal').classList.remove('hidden');
        }
    }
    
    // Show edit form function - opens different modal based on active tab
    function showEditForm() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'inventory';
        
        // Get selected row
        const selectedRow = document.querySelector('tbody tr.bg-blue-50');
        if (!selectedRow) {
            alert('Silakan pilih item yang ingin diedit terlebih dahulu');
            return;
        }
        
        if (activeTab === 'in') {
            // Edit barang masuk
            const cells = selectedRow.querySelectorAll('td');
            document.getElementById('edit_in_idmasuk').value = selectedRow.getAttribute('data-id');
            document.getElementById('edit_in_tgl_masuk').value = cells[1].textContent.trim();
            document.getElementById('edit_in_idbarang').value = cells[2].textContent.trim();
            document.getElementById('edit_in_jumlah').value = cells[5].textContent.trim();
            
            // Set idpurchaseorder if exists
            const idpurchaseorder = selectedRow.getAttribute('data-idpurchaseorder') || '';
            document.getElementById('edit_in_idpurchaseorder').value = idpurchaseorder;
            
            document.getElementById('editInModal').classList.remove('hidden');
        } else if (activeTab === 'out') {
            // Edit barang keluar
            const cells = selectedRow.querySelectorAll('td');
            document.getElementById('edit_out_idkeluar').value = selectedRow.getAttribute('data-id');
            document.getElementById('edit_out_tgl_keluar').value = cells[1].textContent.trim();
            document.getElementById('edit_out_idbarang').value = cells[2].textContent.trim();
            document.getElementById('edit_out_jumlah').value = cells[4].textContent.trim();
            document.getElementById('edit_out_keterangan').value = cells[5].textContent.trim();
            document.getElementById('editOutModal').classList.remove('hidden');
        } else {
            // Edit inventory item
            const cells = selectedRow.querySelectorAll('td');
            const idbarang = cells[1].textContent.trim();
            
            document.getElementById('edit_idbarang').value = idbarang;
            document.getElementById('edit_idbarang_display').value = idbarang;
            document.getElementById('edit_kodebarang').value = cells[2].textContent.trim();
            document.getElementById('edit_nama_barang').value = cells[3].textContent.trim();
            
            document.getElementById('editItemModal').classList.remove('hidden');
        }
    }
    
    // Close modal function
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }
    
    // Select row function
    function selectRow(row) {
        // Remove selection from all rows
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(r => r.classList.remove('bg-blue-50'));
        
        // Add selection to clicked row
        row.classList.add('bg-blue-50');
    }
    
    // Handle search on Enter key
    function handleSearch(event) {
        if (event.key === 'Enter') {
            performSearch();
        }
    }
    
    // Perform search function
    function performSearch() {
        // Get current tab from URL or default to 'overview'
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

// Client-side Table Features for Inventory
let currentSort = { inventory: { column: -1, direction: 'asc' } };

// Search table function
function searchTable(tableType) {
    const searchTerm = document.getElementById(`search-${tableType}`).value.toLowerCase();
    filterAndDisplayTable(tableType, searchTerm);
}

// Clear search
function clearSearch(tableType) {
    document.getElementById(`search-${tableType}`).value = '';
    filterAndDisplayTable(tableType, '');
}

// Combined filter and display
function filterAndDisplayTable(tableType, searchTerm) {
    const table = document.getElementById(`${tableType}-table`);
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
        
        // Check if row matches search
        const matchesSearch = !searchTerm || rowText.includes(searchTerm);
        
        // Show/hide row
        if (matchesSearch) {
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
    const table = document.getElementById(`${tableType}-table`);
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
    const table = document.getElementById(`${tableType}-table`);
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
        
        // Try to parse as numbers (remove 'Rp' and commas)
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
    const table = document.getElementById(`${tableType}-table`);
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
            header.appendChild(arrow);
        } else {
            const arrow = document.createElement('i');
            arrow.className = 'fas fa-sort sort-arrow text-gray-400 ml-1';
            header.appendChild(arrow);
        }
    });
}
</script>

<!-- Add Item Modal -->
<div id="addItemModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add New Item</h3>
                <button onclick="closeModal('addItemModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addItemForm" method="POST" action="">
                <input type="hidden" name="add_item_inventory" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Barang <span class="text-red-500">*</span></label>
                        <input type="text" name="idbarang" placeholder="Contoh: B001" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Barang <span class="text-red-500">*</span></label>
                        <input type="text" name="kodebarang" placeholder="Contoh: KB001" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Barang <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_barang" placeholder="Nama barang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
                        <select name="idkategori" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Pilih Kategori</option>
                            <?php 
                            $stmt = $pdo->query("SELECT idkategori, nama_kategori FROM kategoribarang ORDER BY nama_kategori");
                            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['idkategori']) ?>">
                                <?= htmlspecialchars($category['nama_kategori']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                        <input type="text" name="lokasi" placeholder="Contoh: Gudang A" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Project</label>
                        <input type="text" name="kodeproject" placeholder="Contoh: PROJ-001" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga <span class="text-red-500">*</span></label>
                        <input type="number" name="harga" step="0.01" min="0" placeholder="0" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stok Awal <span class="text-red-500">*</span></label>
                        <input type="number" name="stok_awal" min="0" placeholder="0" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stok Akhir <span class="text-red-500">*</span></label>
                        <input type="number" name="stok_akhir" min="0" placeholder="0" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                        <textarea name="keterangan" rows="3" placeholder="Keterangan tambahan..." class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('addItemModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div id="editItemModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Item</h3>
                <button onclick="closeModal('editItemModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editItemForm" method="POST" action="">
                <input type="hidden" name="edit_item_inventory" value="1">
                <input type="hidden" name="idbarang" id="edit_idbarang">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Barang</label>
                        <input type="text" id="edit_idbarang_display" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-100" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Barang</label>
                        <input type="text" name="kodebarang" id="edit_kodebarang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Barang</label>
                        <input type="text" name="nama_barang" id="edit_nama_barang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="deskripsi" id="edit_deskripsi" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga</label>
                        <input type="number" name="harga" id="edit_harga" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
                        <select name="satuan" id="edit_satuan" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Satuan</option>
                            <option value="pcs">pcs</option>
                            <option value="unit">unit</option>
                            <option value="buah">buah</option>
                            <option value="set">set</option>
                            <option value="box">box</option>
                            <option value="pack">pack</option>
                            <option value="roll">roll</option>
                            <option value="meter">meter</option>
                            <option value="kg">kg</option>
                            <option value="liter">liter</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Project</label>
                        <input type="text" name="kodeproject" id="edit_kodeproject" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                        <select name="idkategori" id="edit_idkategori" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Kategori</option>
                            <?php 
                            // Fetch categories
                            $stmt = $pdo->query("SELECT idkategori, nama_kategori FROM kategoribarang ORDER BY nama_kategori");
                            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['idkategori']) ?>">
                                <?= htmlspecialchars($category['nama_kategori']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('editItemModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD BARANG MASUK MODAL (TAB IN) -->
<div id="addInModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add Barang Masuk</h3>
                <button onclick="closeModal('addInModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_item_in" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Masuk</label>
                        <input type="date" name="tgl_masuk" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Barang</label>
                        <input type="text" name="idbarang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                        <input type="number" name="jumlah" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Purchase Order (Optional)</label>
                        <select name="idpurchaseorder" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Tanpa PO --</option>
                            <?php 
                            // Fetch all valid purchase orders
                            $poStmt = $pdo->query("SELECT idpurchaseorder, supplier, tgl_po FROM purchaseorder ORDER BY tgl_po DESC");
                            while ($po = $poStmt->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                            <option value="<?= htmlspecialchars($po['idpurchaseorder']) ?>">
                                <?= htmlspecialchars($po['idpurchaseorder']) ?> - <?= htmlspecialchars($po['supplier']) ?> (<?= htmlspecialchars($po['tgl_po']) ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('addInModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT BARANG MASUK MODAL -->
<div id="editInModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Barang Masuk</h3>
                <button onclick="closeModal('editInModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="edit_item_in" value="1">
                <input type="hidden" name="idmasuk" id="edit_in_idmasuk">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Masuk</label>
                        <input type="date" name="tgl_masuk" id="edit_in_tgl_masuk" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Barang</label>
                        <input type="text" name="idbarang" id="edit_in_idbarang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                        <input type="number" name="jumlah" id="edit_in_jumlah" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Purchase Order (Optional)</label>
                        <select name="idpurchaseorder" id="edit_in_idpurchaseorder" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Tanpa PO --</option>
                            <?php 
                            // Fetch all valid purchase orders
                            $poStmt2 = $pdo->query("SELECT idpurchaseorder, supplier, tgl_po FROM purchaseorder ORDER BY tgl_po DESC");
                            while ($po2 = $poStmt2->fetch(PDO::FETCH_ASSOC)): 
                            ?>
                            <option value="<?= htmlspecialchars($po2['idpurchaseorder']) ?>">
                                <?= htmlspecialchars($po2['idpurchaseorder']) ?> - <?= htmlspecialchars($po2['supplier']) ?> (<?= htmlspecialchars($po2['tgl_po']) ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('editInModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD BARANG KELUAR MODAL (TAB OUT) -->
<div id="addOutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add Barang Keluar</h3>
                <button onclick="closeModal('addOutModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="add_item_out" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Keluar</label>
                        <input type="date" name="tgl_keluar" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Barang</label>
                        <input type="text" name="idbarang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                        <input type="number" name="jumlah" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                        <textarea name="keterangan" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('addOutModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT BARANG KELUAR MODAL -->
<div id="editOutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Barang Keluar</h3>
                <button onclick="closeModal('editOutModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="edit_item_out" value="1">
                <input type="hidden" name="idkeluar" id="edit_out_idkeluar">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Keluar</label>
                        <input type="date" name="tgl_keluar" id="edit_out_tgl_keluar" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Barang</label>
                        <input type="text" name="idbarang" id="edit_out_idbarang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                        <input type="number" name="jumlah" id="edit_out_jumlah" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                        <textarea name="keterangan" id="edit_out_keterangan" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('editOutModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
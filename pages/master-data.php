<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth->checkAccess();

// Handle POST requests for add/edit/delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_barang'])) {
        // Add new barang
        $kodebarang = $_POST['kodebarang'] ?? '';
        $nama_barang = $_POST['nama_barang'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $harga = $_POST['harga'] ?? 0;
        $satuan = $_POST['satuan'] ?? '';
        $kodeproject = $_POST['kodeproject'] ?? '';
        $idkategori = $_POST['idkategori'] ?? null;
        
        // Generate ID barang (you might want to adjust this logic)
        $idbarang = 'BRG' . date('YmdHis');
        
        try {
            $stmt = $pdo->prepare("INSERT INTO m_barang (idbarang, kodebarang, nama_barang, deskripsi, harga, satuan, kodeproject, idkategori) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$idbarang, $kodebarang, $nama_barang, $deskripsi, $harga, $satuan, $kodeproject, $idkategori]);
            $message = "Barang berhasil ditambahkan";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error menambahkan barang: " . $e->getMessage();
            $message_type = "error";
        }
    } elseif (isset($_POST['edit_barang'])) {
        // Edit existing barang
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
            $message = "Barang berhasil diupdate";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error mengupdate barang: " . $e->getMessage();
            $message_type = "error";
        }
    } elseif (isset($_POST['delete_barang'])) {
        // Delete barang
        $idbarang = $_POST['idbarang'] ?? '';
        
        try {
            $stmt = $pdo->prepare("DELETE FROM m_barang WHERE idbarang = ?");
            $stmt->execute([$idbarang]);
            $message = "Barang berhasil dihapus";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error menghapus barang: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Initialize variables
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'barang';

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

// Initialize items array and total pages
$items = [];
$total_pages = 1;
$totalItems = 0;

// Queries based on active tab
if ($activeTab == 'barang') {
    // Build search condition
    $searchCondition = "";
    if ($search) {
        $searchCondition = "AND (mb.idbarang LIKE :search OR mb.kodebarang LIKE :search OR mb.nama_barang LIKE :search)";
    }
    
    // Count query
    $countQuery = "SELECT COUNT(*) FROM m_barang mb 
                   LEFT JOIN kategoribarang kb ON mb.idkategori = kb.idkategori 
                   WHERE 1=1 $searchCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    // Main query
    $stmt = $pdo->prepare("
        SELECT 
            mb.idbarang,
            mb.kodebarang,
            mb.nama_barang,
            mb.deskripsi,
            mb.harga,
            mb.satuan,
            mb.kodeproject,
            kb.nama_kategori
        FROM m_barang mb
        LEFT JOIN kategoribarang kb ON mb.idkategori = kb.idkategori
        WHERE 1=1 $searchCondition
        ORDER BY mb.idbarang DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($activeTab == 'kategori') {
    // Build search condition
    $searchCondition = "";
    if ($search) {
        $searchCondition = "AND (kb.idkategori LIKE :search OR kb.nama_kategori LIKE :search)";
    }
    
    // Count query
    $countQuery = "SELECT COUNT(*) FROM kategoribarang kb 
                   WHERE 1=1 $searchCondition";
    $countStmt = $pdo->prepare($countQuery);
    if ($search) $countStmt->bindValue(':search', "%$search%");
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $total_pages = ceil($totalItems / $limit);
    
    // Main query
    $stmt = $pdo->prepare("
        SELECT 
            kb.idkategori,
            kb.nama_kategori,
            kb.keterangan
        FROM kategoribarang kb
        WHERE 1=1 $searchCondition
        ORDER BY kb.idkategori DESC
        LIMIT :limit OFFSET :offset
    ");
    if ($search) $stmt->bindValue(':search', "%$search%");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$title = 'Master Data';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data - Internal Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <img src="../images/logo_ipo.png" alt="Logo IPO" class="h-12 w-auto">
        </div>
        
        <nav class="px-4 space-y-1 flex-grow">
            <a href="inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-boxes w-5 h-5"></i>
                <span class="font-medium">Inventory</span>
            </a>
            
            <a href="procurement.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-shopping-cart w-5 h-5"></i>
                <span class="font-medium">Procurement</span>
            </a>
            
            <a href="master-data.php" class="flex items-center gap-3 px-4 py-3 bg-gray-100 text-gray-900 rounded-lg">
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
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Master Data</h1>

        <div class="bg-white rounded-lg shadow">
            <!-- TABS NAVIGATION -->
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <a href="?tab=barang" class="<?= $activeTab == 'barang' ? 'border-b-2 border-blue-600 text-blue-600' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' ?> py-4 px-6 font-medium text-sm">
                        Barang
                    </a>
                    <a href="?tab=kategori" class="<?= $activeTab == 'kategori' ? 'border-b-2 border-blue-600 text-blue-600' : 'border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' ?> py-4 px-6 font-medium text-sm">
                        Kategori
                    </a>
                </nav>
            </div>

            <div class="p-6">

                <!-- BARANG TAB -->
                <?php if ($activeTab == 'barang'): ?>
                <div>
                    <!-- Toolbar -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex gap-2">
                            <button onclick="showAddBarangForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                <i class="fas fa-plus"></i>
                                Add
                            </button>
                            <button onclick="showEditBarangForm()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                        </div>
                        
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
                            
                            <button onclick="downloadReport()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center gap-2">
                                <i class="fas fa-download"></i>
                                Download
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" width="50">#</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Barang</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Project</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="selectRow(this)">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><strong><?= htmlspecialchars($item['idbarang'] ?? '-') ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['kodebarang'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['nama_barang'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['deskripsi'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($item['harga'] ?? 0, 2) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['kodeproject'] ?? '-') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['nama_kategori'] ?? '-') ?></td>
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

                <!-- KATEGORI TAB -->
                <?php if ($activeTab == 'kategori'): ?>
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
                            
                            <button onclick="downloadReport()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center gap-2">
                                <i class="fas fa-download"></i>
                                Download
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" width="50">#</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Kategori</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Kategori</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $row_number = $offset + 1;
                                    foreach ($items as $item): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row_number++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><strong><?= htmlspecialchars($item['idkategori'] ?? '-') ?></strong></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['nama_kategori'] ?? '-') ?></td>
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

            </div>
        </div>

    </main>
</div>

<script>
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
    // Get current tab from URL or default to 'barang'
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'barang';
    
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

// Show add barang form function
function showAddBarangForm() {
    document.getElementById('addBarangModal').classList.remove('hidden');
}

// Show edit barang form function
function showEditBarangForm() {
    // Get selected row
    const selectedRow = document.querySelector('tbody tr.bg-blue-50');
    if (!selectedRow) {
        alert('Silakan pilih barang yang ingin diedit terlebih dahulu');
        return;
    }
    
    // Get data from selected row
    const cells = selectedRow.querySelectorAll('td');
    const idbarang = cells[1].textContent.trim();
    
    // Fetch barang details (in a real implementation, this would be an AJAX call)
    // For now, we'll just populate with the data we have
    document.getElementById('edit_idbarang').value = idbarang;
    document.getElementById('edit_idbarang_display').value = idbarang;
    document.getElementById('edit_kodebarang').value = cells[2].textContent.trim();
    document.getElementById('edit_nama_barang').value = cells[3].textContent.trim();
    document.getElementById('edit_deskripsi').value = cells[4].textContent.trim();
    document.getElementById('edit_harga').value = cells[5].textContent.trim().replace(/[^0-9.-]+/g, '');
    // Set the selected option for the satuan select element
    const satuanValue = cells[6].textContent.trim();
    const satuanSelect = document.getElementById('edit_satuan');
    for (let i = 0; i < satuanSelect.options.length; i++) {
        if (satuanSelect.options[i].value === satuanValue) {
            satuanSelect.selectedIndex = i;
            break;
        }
    }
    document.getElementById('edit_kodeproject').value = cells[7].textContent.trim();
    
    // Show the modal
    document.getElementById('editBarangModal').classList.remove('hidden');
}

// Handle search on Enter key
function handleSearch(event) {
    if (event.key === 'Enter') {
        performSearch();
    }
}

// Perform search function
function performSearch() {
    // Get current tab from URL or default to 'barang'
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = urlParams.get('tab') || 'barang';
    
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

</script>

<!-- Add Barang Modal -->
<div id="addBarangModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Tambah Barang Baru</h3>
                <button onclick="closeModal('addBarangModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="addBarangForm" method="POST" action="">
                <input type="hidden" name="add_barang" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Barang</label>
                        <input type="text" name="kodebarang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Barang</label>
                        <input type="text" name="nama_barang" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="deskripsi" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga</label>
                        <input type="number" name="harga" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
                        <select name="satuan" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
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
                        <input type="text" name="kodeproject" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                        <select name="idkategori" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
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
                    <button type="button" onclick="closeModal('addBarangModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
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

<!-- Edit Barang Modal -->
<div id="editBarangModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Barang</h3>
                <button onclick="closeModal('editBarangModal')" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editBarangForm" method="POST" action="">
                <input type="hidden" name="edit_barang" value="1">
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
                    <button type="button" onclick="closeModal('editBarangModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
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

</body>
</html>
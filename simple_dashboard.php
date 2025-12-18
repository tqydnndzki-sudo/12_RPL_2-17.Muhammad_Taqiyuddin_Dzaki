<?php
require_once 'config/database.php';

// Get the exact same data as in index.php
$stmtProject = $pdo->prepare("
    SELECT 
        kb.nama_kategori as category_name,
        SUM(i.stok_akhir * i.harga) as total_value
    FROM inventory i
    LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
    GROUP BY i.idkategori, kb.nama_kategori
    ORDER BY total_value DESC
");
$stmtProject->execute();
$allProjectData = $stmtProject->fetchAll(PDO::FETCH_ASSOC);

$stmtCategory = $pdo->prepare("
    SELECT 
        kb.nama_kategori as category_name,
        COUNT(i.idinventory) as total_items,
        SUM(i.stok_akhir * i.harga) as total_value
    FROM inventory i
    LEFT JOIN kategoribarang kb ON i.idkategori = kb.idkategori
    GROUP BY i.idkategori, kb.nama_kategori
    ORDER BY total_items DESC
");
$stmtCategory->execute();
$categoryData = $stmtCategory->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>SIMBA OLE - Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #2a9d8f 0%, #1d7a6f 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sidebar {
            width: 250px;
            background: white;
            height: calc(100vh - 70px);
            position: fixed;
            top: 70px;
            left: 0;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-y: auto;
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .sidebar-nav li {
            margin: 5px 0;
        }
        
        .sidebar-nav a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: #e8f4f3;
            border-left: 3px solid #2a9d8f;
            color: #2a9d8f;
        }
        
        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            padding: 20px;
        }
        
        .inventory-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .tab-navigation {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
        }
        
        .tab {
            padding: 8px 16px;
            text-decoration: none;
            color: #666;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .tab:hover, .tab.active {
            background: #2a9d8f;
            color: white;
        }
        
        .charts-container {
            padding: 30px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }
        
        .chart-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .chart-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .chart-legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #666;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">SIMBA OLE</div>
        <div class="user-info">
            <span><i class="fas fa-user-circle"></i> Admin</span>
            <a href="#" style="color: white; text-decoration: none;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <nav>
            <ul class="sidebar-nav">
                <li><a href="#" class="active"><i class="fas fa-boxes"></i> Inventory</a></li>
                <li><a href="#"><i class="fas fa-truck"></i> Procurement</a></li>
                <li><a href="#"><i class="fas fa-database"></i> Master Data</a></li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="inventory-container">
            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <h2 style="margin: 0; font-size: 18px; font-weight: 600;">Inventory</h2>
                <div class="tabs">
                    <a href="#" class="tab active">Overview</a>
                    <a href="#" class="tab">Inventory</a>
                    <a href="#" class="tab">In</a>
                    <a href="#" class="tab">Out</a>
                    <a href="#" class="tab">Detail Masuk</a>
                    <a href="#" class="tab">Detail Keluar</a>
                </div>
            </div>
            
            <!-- Charts Container -->
            <div class="charts-container">
                <div class="charts-grid">
                    <!-- Column Chart -->
                    <div class="chart-section">
                        <div style="margin-bottom: 15px;">
                            <h3 class="chart-title" style="margin: 0;">Total Nilai Inventory berdasarkan Kategori</h3>
                        </div>
                        <div class="chart-legend">
                            <span class="legend-item">
                                <span class="legend-color" style="background: #4169E1"></span>
                                Total Nilai Inventory
                            </span>
                        </div>
                        <canvas id="projectChart" width="600" height="300"></canvas>
                    </div>
                    
                    <!-- Doughnut Chart -->
                    <div class="chart-section">
                        <div style="margin-bottom: 15px;">
                            <h3 class="chart-title" style="margin: 0;">Jumlah Barang berdasarkan Kategori</h3>
                        </div>
                        <div class="chart-legend">
                            <?php 
                            $catColors = ['#4169E1', '#90EE90', '#FF6B6B', '#FFD93D', '#A8DADC'];
                            $catIndex = 0;
                            foreach ($categoryData as $cat): 
                            ?>
                            <span class="legend-item">
                                <span class="legend-color" style="background: <?= $catColors[$catIndex % 5] ?>"></span>
                                <?= htmlspecialchars($cat['category_name'] ?? 'N/A') ?>
                            </span>
                            <?php 
                                $catIndex++;
                            endforeach; 
                            ?>
                        </div>
                        <div style="text-align: center; margin-top: 20px;">
                            <canvas id="categoryChart" width="300" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Prepare data for column chart (by category)
        const categoryChartData = <?php echo json_encode($allProjectData); ?>;
        
        // Create dataset for column chart
        const categoryLabels = categoryChartData.map(item => item.category_name);
        const categoryValues = categoryChartData.map(item => parseFloat(item.total_value));
        
        const datasets = [{
            label: 'Total Nilai Inventory',
            data: categoryValues,
            backgroundColor: '#4169E1',
            borderColor: '#4169E1',
            borderWidth: 0,
            borderRadius: 4,
            barThickness: 'flex',
            maxBarThickness: 40
        }];
        
        // Project Column Chart
        const projectCtx = document.getElementById('projectChart').getContext('2d');
        const projectChart = new Chart(projectCtx, {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += new Intl.NumberFormat('id-ID', { 
                                    style: 'currency', 
                                    currency: 'IDR',
                                    minimumFractionDigits: 0
                                }).format(context.parsed.y);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('id-ID', {
                                    notation: 'compact',
                                    compactDisplay: 'short'
                                }).format(value);
                            }
                        },
                        grid: {
                            color: '#f0f0f0',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Category Doughnut Chart
        const categoryData = <?php echo json_encode($categoryData); ?>;
        const categoryLabels2 = categoryData.map(c => c.category_name);
        const itemCounts = categoryData.map(c => parseInt(c.total_items || 0));
        const categoryColors = ['#4169E1', '#90EE90', '#FF6B6B', '#FFD93D', '#A8DADC'];
        
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels2,
                datasets: [{
                    data: itemCounts,
                    backgroundColor: categoryColors.slice(0, categoryLabels2.length),
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                // For doughnut chart, show item count
                                label += context.parsed + ' items';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
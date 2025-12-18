<?php
require_once 'config/database.php';

// Get category data for column chart (total price by category)
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

// Get category data for doughnut chart (item count by category)
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
    <title>Debug Charts</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            width: 50%;
            float: left;
            padding: 20px;
        }
    </style>
</head>
<body>
    <h1>Debug Charts</h1>
    
    <div class="chart-container">
        <h2>Column Chart</h2>
        <canvas id="projectChart" width="400" height="400"></canvas>
    </div>
    
    <div class="chart-container">
        <h2>Doughnut Chart</h2>
        <canvas id="categoryChart" width="400" height="400"></canvas>
    </div>
    
    <script>
        // Debug: Show the data being used
        console.log("Column chart data:", <?php echo json_encode($allProjectData); ?>);
        console.log("Doughnut chart data:", <?php echo json_encode($categoryData); ?>);
        
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
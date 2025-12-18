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
    <title>Debug JSON Data</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Debug JSON Data for Charts</h1>
    
    <h2>Column Chart Data:</h2>
    <pre id="columnData"><?php echo json_encode($allProjectData, JSON_PRETTY_PRINT); ?></pre>
    
    <h2>Doughnut Chart Data:</h2>
    <pre id="doughnutData"><?php echo json_encode($categoryData, JSON_PRETTY_PRINT); ?></pre>
    
    <h2>JavaScript Variables:</h2>
    <pre id="jsVariables"></pre>
    
    <div style="width: 50%; float: left;">
        <h2>Column Chart Test:</h2>
        <canvas id="columnChart" width="400" height="300"></canvas>
    </div>
    
    <div style="width: 50%; float: left;">
        <h2>Doughnut Chart Test:</h2>
        <canvas id="doughnutChart" width="300" height="300"></canvas>
    </div>
    
    <script>
        // Display the JavaScript variables
        const columnChartData = <?php echo json_encode($allProjectData); ?>;
        const doughnutChartData = <?php echo json_encode($categoryData); ?>;
        
        document.getElementById('jsVariables').textContent = 
            "const columnChartData = " + JSON.stringify(columnChartData, null, 2) + ";\n\n" +
            "const doughnutChartData = " + JSON.stringify(doughnutChartData, null, 2) + ";";
        
        // Test column chart
        const columnLabels = columnChartData.map(item => item.category_name);
        const columnValues = columnChartData.map(item => parseFloat(item.total_value));
        
        const columnCtx = document.getElementById('columnChart').getContext('2d');
        const columnChart = new Chart(columnCtx, {
            type: 'bar',
            data: {
                labels: columnLabels,
                datasets: [{
                    label: 'Total Nilai Inventory',
                    data: columnValues,
                    backgroundColor: '#4169E1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Test doughnut chart
        const doughnutLabels = doughnutChartData.map(item => item.category_name);
        const doughnutValues = doughnutChartData.map(item => parseInt(item.total_items));
        
        const doughnutCtx = document.getElementById('doughnutChart').getContext('2d');
        const doughnutChart = new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: doughnutLabels,
                datasets: [{
                    data: doughnutValues,
                    backgroundColor: ['#4169E1', '#90EE90', '#FF6B6B', '#FFD93D', '#A8DADC']
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
</body>
</html>
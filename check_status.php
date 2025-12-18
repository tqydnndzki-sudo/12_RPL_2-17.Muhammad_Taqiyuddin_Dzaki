<?php
require_once 'config/database.php';

try {
    // Check master_status_pr table
    $stmt = $pdo->query('SELECT * FROM master_status_pr');
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Master Status PR:\n";
    foreach ($statuses as $status) {
        echo "  - ID: " . $status['idstatuspr'] . ", Name: " . $status['status_name'] . "\n";
    }
    
    // Check some sample data from logstatusreq
    echo "\nSample logstatusreq data:\n";
    $stmt = $pdo->query('SELECT * FROM logstatusreq ORDER BY date DESC LIMIT 5');
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $log) {
        echo "  - Request: " . $log['idrequest'] . ", Status: " . $log['status'] . ", Date: " . $log['date'] . "\n";
    }
    
    // Check some sample data from logstatusorder
    echo "\nSample logstatusorder data:\n";
    $stmt = $pdo->query('SELECT * FROM logstatusorder ORDER BY date DESC LIMIT 5');
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $log) {
        echo "  - Order: " . $log['idpurchaseorder'] . ", Status: " . $log['status'] . ", Date: " . $log['date'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
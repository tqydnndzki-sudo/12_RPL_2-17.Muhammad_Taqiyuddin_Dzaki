<?php
require_once 'config/database.php';

try {
    // Check master_status_pr table
    $stmt = $pdo->query('SELECT * FROM master_status_pr');
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Master Status PR Mapping:\n";
    foreach ($statuses as $status) {
        echo "ID: " . $status['idstatus'] . " -> Name: " . $status['nama_status'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
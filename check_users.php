<?php
require_once 'config/database.php';

try {
    // Check users in the system
    echo "=== Users in the system ===\n";
    $stmt = $pdo->query('SELECT iduser, nama, roletype FROM users ORDER BY nama');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID: " . $user['iduser'] . " | Name: " . $user['nama'] . " | Role: " . $user['roletype'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
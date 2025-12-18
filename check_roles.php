<?php
require_once 'config/database.php';

echo "Checking available roles in the system:\n";
$stmt = $pdo->query('SELECT DISTINCT roletype FROM users');
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($roles);

echo "\nChecking permissions mapping:\n";
$permissions = [
    'Admin' => ['view_inventory', 'manage_inventory', 'view_procurement', 'manage_procurement'],
    'Inventory' => ['view_inventory', 'manage_inventory'],
    'Procurement' => ['view_procurement', 'manage_procurement'],
    'Manager' => ['view_inventory', 'view_procurement'],
    'Leader' => ['view_inventory', 'view_procurement']
];

foreach ($permissions as $role => $perms) {
    echo "$role: " . implode(', ', $perms) . "\n";
}
?>
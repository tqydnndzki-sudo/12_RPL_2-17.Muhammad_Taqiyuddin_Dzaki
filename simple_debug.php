<?php
// Simple debug file to check if there are any PHP errors in index.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the index.php file to see if there are any errors
echo "Starting debug...\n";

// Check if database connection works
require_once 'config/database.php';

echo "Database connection successful.\n";

// Check if we can get data
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
    $count = $stmt->fetchColumn();
    echo "Inventory table has {$count} records.\n";
} catch (Exception $e) {
    echo "Error accessing inventory table: " . $e->getMessage() . "\n";
}

// Check if session works
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session started.\n";

echo "Debug completed successfully.\n";
?>
<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("SELECT username, password FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available users:\n";
    foreach($users as $user) {
        echo "- Username: " . $user['username'] . "\n";
    }
    
    echo "\nTry logging in with any of these usernames and the password pattern: [username]123\n";
    echo "For example: admin/admin123, leader/leader123, etc.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
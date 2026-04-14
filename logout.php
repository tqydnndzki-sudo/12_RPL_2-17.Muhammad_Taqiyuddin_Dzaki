<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy all session data
session_destroy();

// Redirect to login page with correct path
header('Location: /login.php');
exit;
?>

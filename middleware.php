<?php
// middleware.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';

function require_login() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /login.php');
        exit;
    }
}

function require_role(array $allowed_roles) {
    require_login();
    $user_role = $_SESSION['role'] ?? '';
    if (!in_array($user_role, $allowed_roles)) {
        http_response_code(403);
        echo "<h2>403 Forbidden</h2><p>Anda tidak punya akses ke halaman ini.</p>";
        echo "<p><strong>Role Anda:</strong> " . htmlspecialchars($user_role) . "</p>";
        echo "<p><strong>Role yang diizinkan:</strong> " . implode(', ', $allowed_roles) . "</p>";
        echo "<br><a href='/index.php'>Kembali ke Dashboard</a>";
        exit;
    }
}

// Khusus untuk Purchase Order - hanya Admin dan Procurement
function require_po_access() {
    require_login();
    $user_role = $_SESSION['role'] ?? '';
    $allowed_roles = ['Admin', 'Procurement'];
    
    if (!in_array($user_role, $allowed_roles)) {
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>403 - Access Denied</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
                .error-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                .error-icon { font-size: 64px; color: #dc3545; margin-bottom: 20px; }
                h2 { color: #dc3545; margin-bottom: 10px; }
                p { color: #666; line-height: 1.6; margin: 10px 0; }
                .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .btn:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <div class='error-icon'>🚫</div>
                <h2>Access Denied</h2>
                <p><strong>Maaf, Anda tidak memiliki akses ke fitur Purchase Order.</strong></p>
                <p>Hanya user dengan role <strong>Admin</strong> atau <strong>Procurement</strong> yang dapat membuat dan menerima Purchase Order.</p>
                <p><strong>Role Anda saat ini:</strong> " . htmlspecialchars($user_role) . "</p>
                <a href='/index.php' class='btn'>Kembali ke Dashboard</a>
            </div>
        </body>
        </html>";
        exit;
    }
}

function current_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'iduser' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'roletype' => $_SESSION['role']
    ];
}

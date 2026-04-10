<?php
// middleware.php
require_once __DIR__ . '/auth.php';

function require_role(array $allowed_roles) {
    require_login();
    $user = current_user();
    if (!$user || !in_array($user['rolestype'], $allowed_roles)) {
        http_response_code(403);
        echo "<h2>403 Forbidden</h2><p>Anda tidak punya akses ke halaman ini.</p>";
        exit;
    }
}

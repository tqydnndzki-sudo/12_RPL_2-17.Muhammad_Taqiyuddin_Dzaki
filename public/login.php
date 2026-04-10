<?php
// public/login.php
require_once __DIR__ . '/../auth.php';

if (is_logged_in()) {
    // redirect based on role
    $r = current_user()['rolestype'];
    if ($r === 1) header('Location: /public/dashboard_admin.php');
    if ($r === 2) header('Location: /public/dashboard_manager.php');
    if ($r === 3) header('Location: /public/dashboard_leader.php');
    if ($r === 4) header('Location: /public/dashboard_staff.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if (attempt_login($u, $p)) {
        $r = current_user()['rolestype'];
        if ($r === 1) header('Location: /public/dashboard_admin.php');
        if ($r === 2) header('Location: /public/dashboard_manager.php');
        if ($r === 3) header('Location: /public/dashboard_leader.php');
        if ($r === 4) header('Location: /public/dashboard_staff.php');
        exit;
    } else {
        $error = "Username atau password salah";
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login SIMBA</title>
<link rel="stylesheet" href="/asset/css/style.css">
</head>
<body>
<div class="login-box">
  <h2>SIMBA - Login</h2>
  <?php if($error): ?><p class="error"><?=htmlspecialchars($error)?></p><?php endif; ?>
  <form method="post">
    <input name="username" placeholder="Username" required>
    <input name="password" type="password" placeholder="Password" required>
    <button type="submit">Masuk</button>
  </form>
  <p style="font-size:0.9em;margin-top:8px;">Atau gunakan <a href="/public/public_form_request.php">Public Request Form</a> (Staff)</p>
</div>
</body>
</html>

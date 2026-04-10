<?php
session_start();
require_once __DIR__ . '/db.php';

// jika tombol login ditekan
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');

    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $u, $p);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        $_SESSION['user'] = $result;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - IMS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">

<div class="login-container">

    <div class="login-left">
        <img src="assets/logo.png" class="logo">
    </div>

    <div class="login-right">

        <h1>Internal Management System</h1>
        <p class="subtitle">Please Login First Before Access IMS.</p>

        <?php if ($error): ?>
            <div class="error-box"><?= $error ?></div>
        <?php endif; ?>

        <form method="post">

            <label>Username</label>
            <input type="text" name="username" class="input-box" required>

            <label>Password</label>
            <input type="password" name="password" class="input-box" required>

            <div class="button-group">
                <a href="form-pr.php" class="btn yellow">Form Purchase Request</a>
                <a href="lacak-pr.php" class="btn yellow">Lacak Purchase Request</a>
                <button type="submit" class="btn blue">Login</button>
            </div>

        </form>

    </div>

</div>

</body>
</html>

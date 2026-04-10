<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';

// Jika sudah login, arahkan ke dashboard
if ($auth->isLoggedIn()) {
    header('Location: pages/inventory.php');
    exit;
}

$error = '';
$staff_error = false; // Flag untuk staff access denial

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi input kosong
    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi';
    } else {

        // Proses login
        if ($auth->login($username, $password)) {
            
            // Cek role user - Staff tidak boleh login ke dashboard
            $stmt = $pdo->prepare("SELECT roletype FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData && $userData['roletype'] === 'Staff') {
                // Staff tidak boleh login ke dashboard
                $auth->logout();
                $staff_error = true; // Set flag untuk staff error khusus
            } else {
                // Redirect ke halaman inventory setelah login (untuk non-Staff)
                $redirect = 'pages/inventory.php';
                header("Location: $redirect");
                exit;
            }

        } else {
            $error = 'Username atau password salah';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login IMS</title>

<style>
/* ============================
        GLOBAL STYLE
===============================*/
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body{
    background:#067A67;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.login-wrapper{
    width:100%;
    max-width:1300px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:40px 60px;
}

/* ============================
        LEFT LOGO
===============================*/
.login-left img{
    width:450px;
    height:auto;
}

/* ============================
      RIGHT FORM CONTAINER
===============================*/
.login-right{
    width:100%;
    max-width:600px;
    background:transparent;
}

.login-header{
    text-align:left;
    margin-bottom:30px;
}

.login-header h1{
    font-size:38px;
    color:white;
    font-weight:700;
}

.login-header p{
    color:white;
    opacity:.9;
    margin-top:8px;
}

/* ============================
            FORM
===============================*/
.form-group{
    margin-bottom:25px;
}

.form-label{
    color:white;
    font-weight:600;
    font-size:16px;
    margin-bottom:8px;
    display:block;
}

.form-control{
    width:100%;
    padding:15px;
    border:2px solid white;
    border-radius:8px;
    background:white;
    font-size:16px;
}

.form-control:focus{
    outline:none;
    border-color:#FFD000;
}

/* ============================
     BUTTONS (PR & LOGIN)
===============================*/

.btn-yellow{
    width:100%;
    padding:15px;
    background:#E5A500;
    border:none;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
    font-size:16px;
    margin-bottom:12px;
}

.btn-yellow:hover{
    background:#c98d00;
}

.btn-blue{
    padding:15px 40px;
    background:#1857E4;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:16px;
    font-weight:bold;
}

.btn-blue:hover{
    background:#0c3fb3;
}

.button-row{
    display:flex;
    align-items:center;
    gap:20px;
    margin-top:20px;
}

/* ============================
        ALERT BOX
===============================*/
.alert{
    padding:15px;
    border-radius:8px;
    margin-bottom:20px;
    font-weight:bold;
}

.alert-danger{
    background:#ffb3b3;
    color:#7a0000;
}

.alert-info{
    background:#bcdfff;
    color:#003d66;
}

.alert-staff {
    background: #fff3cd;
    border-left: 5px solid #ffc107;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.alert-staff h3 {
    margin: 0 0 15px 0;
    color: #856404;
    font-size: 18px;
    font-weight: 600;
}

.alert-staff p {
    margin: 10px 0;
    color: #856404;
    line-height: 1.6;
}

.alert-staff-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn-staff {
    padding: 12px 24px;
    background: #E5A500;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s;
}

.btn-staff:hover {
    background: #c98d00;
}

/* ============================
        RESPONSIVE
===============================*/
@media(max-width:900px){
    .login-wrapper{
        flex-direction:column;
        text-align:center;
        gap:40px;
    }
    .login-left img{
        width:280px;
    }
    .button-row{
        flex-direction:column;
    }
}
</style>

</head>
<body>

<div class="login-wrapper">

    <!-- LEFT LOGO -->
    <div class="login-left">
        <img src="images/logo_ip.png" alt="Logo IP">
    </div>    <!-- RIGHT FORM -->
    <div class="login-right">

        <div class="login-header">
            <h1>Inventory & Procurement System</h1>
            <p>Please Login First Before Access System.</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(isset($_GET['message'])): ?>
            <div class="alert alert-info"><?= htmlspecialchars($_GET['message']) ?></div>
        <?php endif; ?>

        <?php if($staff_error): ?>
            <div class="alert alert-staff">
                <h3>Access Denied - Staff Role</h3>
                <p>Maaf, role "Staff" tidak dapat login ke dashboard system.</p>
                <p><strong>Hak Akses Staff:</strong></p>
                <ul>
                    <li>Mengajukan Purchase Request (PR)</li>
                    <li>Melacak status Purchase Request</li>
                </ul>
                <p>Silakan gunakan tombol di bawah untuk mengakses form Purchase Request. Anda harus terdaftar sebagai user di database untuk dapat mengajukan PR.</p>
                <div class="alert-staff-buttons">
                    <button type="button" class="btn-staff" onclick="window.location.href='pages/purchase-request.php'">
                        Form Purchase Request
                    </button>
                    <button type="button" class="btn-staff" onclick="window.location.href='pages/tracking.php'">
                        Lacak Purchase Request
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- FORM LOGIN -->
        <form method="POST">

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required>
            </div>

            <!-- BUTTON FORM PR -->
            <button type="button" class="btn-yellow" onclick="window.location.href='pages/purchase-request.php'">
                Form Purchase Request
            </button>

            <button type="button" class="btn-yellow" onclick="window.location.href='pages/tracking.php'">
                Lacak Purchase Request
            </button>


            <!-- LOGIN BUTTON -->
            <div class="button-row">
                <button type="submit" class="btn-blue">Login</button>
            </div>

        </form>
    </div>

</div>

</body>
</html>

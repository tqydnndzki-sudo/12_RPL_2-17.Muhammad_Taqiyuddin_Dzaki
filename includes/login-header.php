<?php
if (!isset($title)) { $title = "Login - Internal Management System"; }

if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?></title>

<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}

    body{
        background:#087A68;
        min-height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:40px;
    }

    .login-wrapper{
        display:flex;
        align-items:center;
        justify-content:space-between;
        width:100%;
        max-width:1400px;
        gap:60px;
    }

    .logo-section img{
        width:500px;
        height:auto;
    }

    .form-section{
        flex:1;
        color:white;
    }

    .title{
        font-size:42px;
        font-weight:700;
        margin-bottom:10px;
    }

    .subtitle{
        font-size:18px;
        opacity:.9;
        margin-bottom:30px;
    }

    .form-group{
        margin-bottom:25px;
        width:100%;
    }

    label{
        font-size:17px;
        font-weight:600;
        margin-bottom:7px;
        display:block;
    }

    input{
        width:100%;
        padding:15px 17px;
        font-size:17px;
        border-radius:8px;
        border:2px solid #ccc;
        outline:none;
    }

    input:focus{
        border-color:#00FF41;
        box-shadow:0 0 8px rgba(0,255,100,0.5);
    }

    .login-btn{
        background:#1558EB;
        color:white;
        font-size:18px;
        padding:14px 40px;
        border:none;
        border-radius:8px;
        cursor:pointer;
        margin-left:20px;
        transition:.2s;
    }

    .login-btn:hover{
        background:#194ACC;
        transform:translateY(-2px);
    }

    .gold-btn{
        width:100%;
        background:#DDA600;
        padding:14px;
        border-radius:8px;
        font-size:18px;
        font-weight:600;
        margin-bottom:15px;
        border:none;
        cursor:pointer;
    }

    .gold-btn:hover{
        background:#C89400;
    }

    .button-row{
        display:flex;
        gap:20px;
        align-items:center;
        margin-top:25px;
    }

    @media(max-width:1000px){
        .login-wrapper{flex-direction:column;text-align:center;}
        .form-section{max-width:600px;}
        .logo-section img{width:300px;}
        .button-row{flex-direction:column;}
        .login-btn{width:100%;margin-left:0;}
    }
</style>
</head>

<body>

<div class="login-wrapper">

    <!-- LEFT LOGO -->
    <div class="logo-section">
        <img src="assets/logormv.png">
    </div>

    <!-- RIGHT FORM -->
    <div class="form-section">
        <div class="title">Internal Management System</div>
        <div class="subtitle">Please Login First Before Access IMS.</div>

        <?php if (!empty($_SESSION['login_error'])): ?>
            <div style="background:#FFDDDD;color:#900;padding:12px;border-radius:8px;margin-bottom:20px;">
                <?= $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="auth_login.php">

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="Masukkan username">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Masukkan password">
            </div>

            <div class="button-row">
                <div style="flex:1;">
                    <button type="button" class="gold-btn" onclick="window.location='public_pr.php'">
                        Form Purchase Request
                    </button>

                    <button type="button" class="gold-btn" onclick="window.location='track_pr.php'">
                        Lacak Purchase Request
                    </button>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </div>

        </form>
    </div>

</div>

</body>
</html>

<?php
$id = $_GET['id'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Success</title>
<link rel="stylesheet" href="assets/public_pr.css">
</head>
<body>
<div class="container" style="text-align:center">
    <h2>Purchase Request Berhasil Dibuat</h2>
    <p>ID Request Anda:</p>
    <h1><?= $id ?></h1>
    <a href="public_pr.php" class="submit">Buat PR Baru</a>
</div>
</body>
</html>

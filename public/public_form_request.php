<?php
require_once __DIR__ . '/../db.php';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['namarequestor'] ?? 'Anonymous';
    $keterangan = $_POST['keterangan'] ?? '';
    $idrequest = 'PR'.time();

    $stmt = $mysqli->prepare("INSERT INTO purchaserequest (idrequest, iduserrequest, namarequestor, keterangan, tgl_req) VALUES (?, NULL, ?, ?, NOW())");
    $stmt->bind_param("sss", $idrequest, $nama, $keterangan);
    if ($stmt->execute()) {
        $success = true;
    } else {
        $error = $stmt->error;
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Public Request</title>
<link rel="stylesheet" href="/asset/css/style.css"></head><body>
<div class="card">
  <h2>Form Permintaan (Public)</h2>
  <?php if(isset($success) && $success): ?>
    <p class="success">Request berhasil dikirim. ID: <?=$idrequest?></p>
  <?php endif; ?>
  <form method="post">
    <label>Nama Requestor</label><input name="namarequestor" required>
    <label>Keterangan</label><textarea name="keterangan"></textarea>
    <button type="submit">Kirim Request</button>
  </form>
</div>
</body></html>

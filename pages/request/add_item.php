<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1,2,3,4]);
require_once __DIR__ . '/../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pr = $_POST['idrequest'] ?? '';
    $idbarang = trim($_POST['idbarang'] ?? null);
    $namaitem = trim($_POST['namaitem'] ?? '');
    $des = trim($_POST['deskripsi'] ?? '');
    $harga = (int)($_POST['harga'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    $total = $harga * $qty;

    $s = $mysqli->prepare("INSERT INTO detailrequest (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $link = null; $kode = $_POST['kodeproject'] ?? null;
    $s->bind_param("sssssiiss", $idbarang, $pr, $link, $namaitem, $des, $harga, $qty, $total, $kode);
    if ($s->execute()) {
        header("Location: /pages/request/detail.php?id=" . urlencode($pr));
        exit;
    } else {
        echo "Error: " . $s->error;
    }
}

// minimal form when accessed directly
$pr = $_GET['pr'] ?? '';
?>
<!doctype html><html><head><meta charset="utf-8"><title>Tambah Item PR</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<div class="container card">
  <h2>Tambah Item ke PR <?=$pr?></h2>
  <form method="post">
    <input type="hidden" name="idrequest" value="<?=htmlspecialchars($pr)?>">
    <label>ID Barang (opsional)</label><input name="idbarang">
    <label>Nama Item</label><input name="namaitem" required>
    <label>Deskripsi</label><textarea name="deskripsi"></textarea>
    <label>Harga</label><input name="harga" type="number" value="0">
    <label>Qty</label><input name="qty" type="number" value="1">
    <label>Kode Project</label><input name="kodeproject">
    <button type="submit">Tambah Item</button>
  </form>
</div>
</body></html>

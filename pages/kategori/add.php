<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1]);
require_once __DIR__ . '/../../db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_kategori'] ?? '');
    $des = trim($_POST['deskripsi_kategori'] ?? '');
    if ($nama !== '') {
        $s = $mysqli->prepare("INSERT INTO kategoribarang (nama_kategori, deskripsi_kategori) VALUES (?, ?)");
        $s->bind_param("ss",$nama,$des);
        $s->execute();
        header("Location: list.php"); exit;
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Tambah Kategori</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<div class="container card">
  <h2>Tambah Kategori</h2>
  <form method="post">
    <label>Nama Kategori</label><input name="nama_kategori" required>
    <label>Deskripsi</label><textarea name="deskripsi_kategori"></textarea>
    <button type="submit">Simpan</button>
    <a href="list.php" style="margin-left:10px">Batal</a>
  </form>
</div>
</body></html>

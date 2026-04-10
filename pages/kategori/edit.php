<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1]);
require_once __DIR__ . '/../../db.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit; }
$stmt = $mysqli->prepare("SELECT * FROM kategoribarang WHERE idkategori = ?");
$stmt->bind_param("i",$id); $stmt->execute(); $k = $stmt->get_result()->fetch_assoc();
if (!$k) { echo "Not found"; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_kategori']); $des = trim($_POST['deskripsi_kategori']);
    $u = $mysqli->prepare("UPDATE kategoribarang SET nama_kategori=?, deskripsi_kategori=? WHERE idkategori=?");
    $u->bind_param("ssi",$nama,$des,$id); $u->execute();
    header("Location: list.php"); exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Edit Kategori</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<div class="container card">
  <h2>Edit Kategori</h2>
  <form method="post">
    <label>Nama Kategori</label><input name="nama_kategori" value="<?=htmlspecialchars($k['nama_kategori'])?>" required>
    <label>Deskripsi</label><textarea name="deskripsi_kategori"><?=htmlspecialchars($k['deskripsi_kategori'])?></textarea>
    <button type="submit">Update</button>
    <a href="list.php" style="margin-left:10px">Batal</a>
  </form>
</div>
</body></html>

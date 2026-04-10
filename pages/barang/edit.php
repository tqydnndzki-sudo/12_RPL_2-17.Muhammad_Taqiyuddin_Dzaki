<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1]);
require_once __DIR__ . '/../../db.php';

$id = $_GET['id'] ?? '';
if (!$id) { header("Location: /pages/barang/list.php"); exit; }

$stmt = $mysqli->prepare("SELECT * FROM m_barang WHERE idbarang = ?");
$stmt->bind_param("s",$id); $stmt->execute(); $r = $stmt->get_result()->fetch_assoc();
if (!$r) { echo "Barang tidak ditemukan"; exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_barang'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = (int)($_POST['harga'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? '');
    $kodeproject = trim($_POST['kodeproject'] ?? '');
    $idkategori = (int)($_POST['idkategori'] ?? 0);

    $u = $mysqli->prepare("UPDATE m_barang SET nama_barang=?, deskripsi=?, harga=?, satuan=?, kodeproject=?, idkategori=? WHERE idbarang=?");
    $u->bind_param("ssissis", $nama, $deskripsi, $harga, $satuan, $kodeproject, $idkategori, $id);
    if ($u->execute()) {
        header("Location: /pages/barang/list.php");
        exit;
    } else $errors[] = $u->error;
}

$kq = $mysqli->query("SELECT * FROM kategoribarang ORDER BY nama_kategori ASC");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Edit Barang</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<div class="container card">
  <h2>Edit Barang <?=$id?></h2>
  <?php foreach($errors as $e): ?><p class="error"><?=htmlspecialchars($e)?></p><?php endforeach; ?>
  <form method="post">
    <label>Nama Barang</label><input name="nama_barang" value="<?=htmlspecialchars($r['nama_barang'])?>" required>
    <label>Deskripsi</label><textarea name="deskripsi"><?=htmlspecialchars($r['deskripsi'])?></textarea>
    <label>Harga</label><input name="harga" type="number" value="<?=htmlspecialchars($r['harga'])?>">
    <label>Satuan</label><input name="satuan" value="<?=htmlspecialchars($r['satuan'])?>">
    <label>Kode Project</label><input name="kodeproject" value="<?=htmlspecialchars($r['kodeproject'])?>">
    <label>Kategori</label>
    <select name="idkategori" required>
      <?php while($k = $kq->fetch_assoc()): ?>
        <option value="<?=$k['idkategori']?>" <?=($k['idkategori']==$r['idkategori'])?'selected':''?>><?=htmlspecialchars($k['nama_kategori'])?></option>
      <?php endwhile; ?>
    </select>
    <button type="submit">Update</button>
    <a href="/pages/barang/list.php" style="margin-left:10px">Batal</a>
  </form>
</div>
</body></html>

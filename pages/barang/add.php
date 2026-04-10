<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1]);
require_once __DIR__ . '/../../db.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idbarang = strtoupper(trim($_POST['idbarang'] ?? ''));
    $nama = trim($_POST['nama_barang'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = (int)($_POST['harga'] ?? 0);
    $satuan = trim($_POST['satuan'] ?? '');
    $kodeproject = trim($_POST['kodeproject'] ?? '');
    $idkategori = (int)($_POST['idkategori'] ?? 0);

    if ($idbarang === '' || $nama === '') $errors[] = "ID dan Nama wajib diisi";

    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO m_barang (idbarang, nama_barang, deskripsi, harga, satuan, kodeproject, idkategori) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisii", $idbarang, $nama, $deskripsi, $harga, $satuan, $kodeproject, $idkategori);
        if ($stmt->execute()) {
            header("Location: /pages/barang/list.php");
            exit;
        } else {
            $errors[] = "DB Error: " . $stmt->error;
        }
    }
}

// load kategori
$kq = $mysqli->query("SELECT * FROM kategoribarang ORDER BY nama_kategori ASC");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Tambah Barang</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php include __DIR__ . '/../../_nav.php'; ?>
<div class="container card">
  <h2>Tambah Barang</h2>
  <?php foreach($errors as $e): ?><p class="error"><?=htmlspecialchars($e)?></p><?php endforeach; ?>
  <form method="post">
    <label>ID Barang</label><input name="idbarang" required>
    <label>Nama Barang</label><input name="nama_barang" required>
    <label>Deskripsi</label><textarea name="deskripsi"></textarea>
    <label>Harga</label><input name="harga" type="number" value="0">
    <label>Satuan</label><input name="satuan">
    <label>Kode Project</label><input name="kodeproject">
    <label>Kategori</label>
    <select name="idkategori" required>
      <option value="">-- Pilih Kategori --</option>
      <?php while($k = $kq->fetch_assoc()): ?>
      <option value="<?=$k['idkategori']?>"><?=htmlspecialchars($k['nama_kategori'])?></option>
      <?php endwhile; ?>
    </select>
    <button type="submit">Simpan</button>
    <a href="/pages/barang/list.php" style="margin-left:10px">Batal</a>
  </form>
</div>
</body></html>

<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1]);
require_once __DIR__ . '/../../db.php';
$res = $mysqli->query("SELECT * FROM kategoribarang ORDER BY idkategori");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Kategori</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<div class="container card">
  <h2>Master Kategori</h2>
  <p><a href="add.php">Tambah Kategori</a></p>
  <table class="table"><thead><tr><th>ID</th><th>Kategori</th><th>Aksi</th></tr></thead><tbody>
    <?php while($r = $res->fetch_assoc()): ?>
    <tr>
      <td><?=$r['idkategori']?></td>
      <td><?=htmlspecialchars($r['nama_kategori'])?></td>
      <td><a href="edit.php?id=<?=$r['idkategori']?>">Edit</a> | <a href="delete.php?id=<?=$r['idkategori']?>" onclick="return confirm('Hapus?')">Delete</a></td>
    </tr>
    <?php endwhile; ?>
  </tbody></table>
</div>
</body></html>

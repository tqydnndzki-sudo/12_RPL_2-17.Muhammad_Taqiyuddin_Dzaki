<?php
require_once __DIR__.'/../../middleware.php';
require_role([1]); // admin only for CRUD
require_once __DIR__.'/../../db.php';

$res = $mysqli->query("SELECT * FROM m_barang ORDER BY idbarang ASC");
?>
<!doctype html><html><head><meta charset="utf-8"><title>Master Barang</title>
<link rel="stylesheet" href="/asset/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<h2>Master Barang</h2>
<p><a href="/pages/barang/add.php">Tambah Barang</a></p>
<table class="table">
<thead><tr><th>ID</th><th>Nama</th><th>Harga</th><th>Kategori</th><th>Aksi</th></tr></thead>
<tbody>
<?php while($r = $res->fetch_assoc()): ?>
<tr>
  <td><?=htmlspecialchars($r['idbarang'])?></td>
  <td><?=htmlspecialchars($r['nama_barang'])?></td>
  <td><?=number_format($r['harga'])?></td>
  <td><?=htmlspecialchars($r['idkategori'])?></td>
  <td><a href="edit.php?id=<?=urlencode($r['idbarang'])?>">Edit</a> | <a href="delete.php?id=<?=urlencode($r['idbarang'])?>" onclick="return confirm('Hapus?')">Delete</a></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</body></html>

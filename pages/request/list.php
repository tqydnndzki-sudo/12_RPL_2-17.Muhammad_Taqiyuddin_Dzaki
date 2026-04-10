<?php
// pages/request/list.php
require_once __DIR__.'/../../middleware.php';
require_role([1,2,3]); // admin, manager, leader
require_once __DIR__.'/../../db.php';

$filter = $_GET['filter'] ?? '';
$sql = "SELECT p.*, u.nama as created_by_name FROM purchaserequest p LEFT JOIN user u ON p.iduserrequest = u.username ORDER BY p.tgl_req DESC LIMIT 200";
$res = $mysqli->query($sql);
?>
<!doctype html><html><head><meta charset="utf-8"><title>List PR</title>
<link rel="stylesheet" href="/asset/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<h2>List Purchase Request</h2>
<table class="table">
  <thead><tr><th>ID</th><th>Requestor</th><th>Keterangan</th><th>Tgl Req</th><th>Aksi</th></tr></thead>
  <tbody>
  <?php while($row = $res->fetch_assoc()): ?>
    <tr>
      <td><?=htmlspecialchars($row['idrequest'])?></td>
      <td><?=htmlspecialchars($row['namarequestor']?:$row['created_by_name'])?></td>
      <td><?=htmlspecialchars($row['keterangan'])?></td>
      <td><?=htmlspecialchars($row['tgl_req'])?></td>
      <td>
        <a href="/pages/request/detail.php?id=<?=urlencode($row['idrequest'])?>">Detail</a>
        <?php if(in_array(current_user()['rolestype'], [1,2,3])): ?>
          | <a href="/pages/request/approve.php?id=<?=urlencode($row['idrequest'])?>">Approve</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endwhile; ?>
  </tbody>
</table>
</body></html>

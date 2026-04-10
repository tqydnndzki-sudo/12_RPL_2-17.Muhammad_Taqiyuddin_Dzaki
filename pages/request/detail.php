<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1,2,3]);
require_once __DIR__ . '/../../db.php';
$id = $_GET['id'] ?? '';
$stmt = $mysqli->prepare("SELECT * FROM purchaserequest WHERE idrequest = ?");
$stmt->bind_param("s",$id);
$stmt->execute();
$pr = $stmt->get_result()->fetch_assoc();

$stmt2 = $mysqli->prepare("SELECT * FROM detailrequest WHERE idrequest = ?");
$stmt2->bind_param("s",$id);
$stmt2->execute();
$items = $stmt2->get_result();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Detail PR</title>
<link rel="stylesheet" href="/asset/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<h2>Detail PR: <?=htmlspecialchars($id)?></h2>
<p><strong>Requestor:</strong> <?=htmlspecialchars($pr['namarequestor'])?></p>
<p><strong>Keterangan:</strong> <?=htmlspecialchars($pr['keterangan'])?></p>

<h3>Items</h3>
<table class="table"><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th>Total</th></tr></thead><tbody>
<?php while($it = $items->fetch_assoc()): ?>
  <tr>
    <td><?=htmlspecialchars($it['namaitem'])?></td>
    <td><?=htmlspecialchars($it['qty'])?></td>
    <td><?=number_format($it['harga'])?></td>
    <td><?=number_format($it['total'])?></td>
  </tr>
<?php endwhile; ?>
</tbody></table>

</body></html>

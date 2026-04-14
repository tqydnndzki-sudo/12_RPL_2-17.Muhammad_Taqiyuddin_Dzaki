<?php
require_once __DIR__ . '/../../middleware.php';
require_po_access(); // Hanya Admin dan Procurement
require_once __DIR__ . '/../../db.php';

$pr = $_GET['pr'] ?? '';
if (!$pr) { echo "ID PR diperlukan"; exit; }

// ambil PR dan item
$stmt = $mysqli->prepare("SELECT * FROM purchaserequest WHERE idrequest = ?");
$stmt->bind_param("s",$pr); $stmt->execute(); $prh = $stmt->get_result()->fetch_assoc();
$itq = $mysqli->prepare("SELECT * FROM detailrequest WHERE idrequest = ?");
$itq->bind_param("s",$pr); $itq->execute(); $items = $itq->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idpo = trim($_POST['idpurchaseorder'] ?? ('PO'.time()));
    $vendor = trim($_POST['vendor_name'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    // hitung total dari item form (atau ambil dari DB)
    $total = 0;
    if (isset($_POST['item_harga']) && is_array($_POST['item_harga'])) {
        foreach($_POST['item_harga'] as $i => $h) {
            $q = (int)($_POST['item_qty'][$i] ?? 0);
            $total += ((int)$h) * $q;
        }
    }

    $s = $mysqli->prepare("INSERT INTO purchaseorder (idpurchaseorder, idrequest, vendor_name, tgl_purchase, keterangan, total_amount) VALUES (?, ?, ?, NOW(), ?, ?)");
    $s->bind_param("ssssi",$idpo, $pr, $vendor, $keterangan, $total);
    if ($s->execute()) {
        // insert detailorder
        if (isset($_POST['item_kode'])) {
            $d = $mysqli->prepare("INSERT INTO detailorder (idbarang, idpurchaseorder, qty, currency, harga, kodeproject) VALUES (?, ?, ?, ?, ?, ?)");
            foreach($_POST['item_kode'] as $i => $kode) {
                $h = (int)($_POST['item_harga'][$i] ?? 0);
                $q = (int)($_POST['item_qty'][$i] ?? 0);
                $cur = $_POST['item_currency'][$i] ?? 'IDR';
                $kp = $_POST['item_project'][$i] ?? null;
                $d->bind_param("ssisss", $kode, $idpo, $q, $cur, $h, $kp);
                $d->execute();
            }
        }
        header("Location: /pages/po/list.php");
        exit;
    } else {
        echo "Error: ".$s->error;
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Buat PO dari <?=$pr?></title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php include __DIR__.'/../../_nav.php'; ?>
<div class="container card">
  <h2>Buat PO dari PR <?=$pr?></h2>
  <form method="post">
    <label>ID PO (opsional)</label><input name="idpurchaseorder" value="PO<?=time()?>">
    <label>Vendor</label><input name="vendor_name" required>
    <label>Keterangan</label><textarea name="keterangan"></textarea>

    <h3>Item (otomatis dari PR)</h3>
    <?php while($it = $items->fetch_assoc()): ?>
      <div style="border:1px dashed #eee;padding:8px;margin-bottom:8px">
        <input type="hidden" name="item_kode[]" value="<?=htmlspecialchars($it['idbarang'])?>">
        <label>Item</label><input value="<?=htmlspecialchars($it['namaitem'])?>" disabled>
        <label>Qty</label><input name="item_qty[]" type="number" value="<?=htmlspecialchars($it['qty'])?>">
        <label>Harga</label><input name="item_harga[]" type="number" value="<?=htmlspecialchars($it['harga'])?>">
        <label>Currency</label><input name="item_currency[]" value="IDR">
        <label>Project</label><input name="item_project[]" value="<?=htmlspecialchars($it['kodeproject'])?>">
      </div>
    <?php endwhile; ?>

    <button type="submit">Buat PO</button>
    <a href="/pages/request/detail.php?id=<?=urlencode($pr)?>" style="margin-left:10px">Back</a>
  </form>
</div>
</body></html>

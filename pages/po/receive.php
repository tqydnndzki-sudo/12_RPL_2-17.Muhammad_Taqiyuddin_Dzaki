<?php
require_once __DIR__ . '/../../middleware.php';
require_po_access(); // Hanya Admin dan Procurement
require_once __DIR__ . '/../../db.php';

$po = $_GET['po'] ?? '';
if (!$po) { echo "PO diperlukan"; exit; }

// create barangmasuk record and detailmasuk from detailorder
// load PO items
$it = $mysqli->query("SELECT * FROM detailorder WHERE idpurchaseorder = '".$mysqli->real_escape_string($po)."'");
$idmasuk = 'BM'.time();
if ($it->num_rows > 0) {
    // insert barangmasuk header
    $s = $mysqli->prepare("INSERT INTO barangmasuk (idmasuk, idpurchaseorder, tgl_masuk, total, keterangan, iduserprocurementcreate, iduserprocurementapproval) VALUES (?, ?, NOW(), ?, ?, ?, ?)");
    // estimate total from po
    $sum = 0;
    foreach($it as $r) $sum += ($r['harga'] * $r['qty']);
    $keterangan = "Terima barang dari PO ".$po;
    $uid = current_user()['iduser'] ?? null;
    $uid2 = $uid; // same user as approver for now
    $s->bind_param("ssissi", $idmasuk, $po, $sum, $keterangan, $uid, $uid2);
    $s->execute();

    $d = $mysqli->prepare("INSERT INTO detailmasuk (idbarang, idkategori, idmasuk, qty, lokasi, kodeproject) VALUES (?, ?, ?, ?, ?, ?)");
    foreach($it as $row) {
        // get kategori from m_barang
        $qk = $mysqli->prepare("SELECT idkategori FROM m_barang WHERE idbarang = ?");
        $qk->bind_param("s",$row['idbarang']); $qk->execute(); $krow = $qk->get_result()->fetch_assoc();
        $idkat = $krow['idkategori'] ?? null;
        $lok = 'Gudang A';
        $d->bind_param("siisss", $row['idbarang'], $idkat, $idmasuk, $row['qty'], $lok, $row['kodeproject']);
        $d->execute();

        // update inventory: increase stok_akhir and qty_in
        $check = $mysqli->prepare("SELECT * FROM inventory WHERE idbarang = ?");
        $check->bind_param("s", $row['idbarang']); $check->execute(); $ci = $check->get_result()->fetch_assoc();
        if ($ci) {
            $new_qty_in = intval($ci['qty_in']) + intval($row['qty']);
            $new_stok = intval($ci['stok_akhir']) + intval($row['qty']);
            $u = $mysqli->prepare("UPDATE inventory SET qty_in=?, stok_akhir=?, total=? WHERE idinventory = ?");
            // keep idinventory same
            $totalval = $new_stok * intval($row['harga']);
            $u->bind_param("iisi", $new_qty_in, $new_stok, $totalval, $ci['idinventory']);
            $u->execute();
        } else {
            // create new inventory record
            $idinv = 'INV'.time().rand(10,99);
            $stok = intval($row['qty']);
            $totalval = $stok * intval($row['harga']);
            $ins = $mysqli->prepare("INSERT INTO inventory (idinventory, idbarang, idkategori, lokasi, kodeproject, nama_barang, stok_awal, stok_akhir, qty_in, qty_out, total, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            // nama_barang lookup
            $qnb = $mysqli->prepare("SELECT nama_barang FROM m_barang WHERE idbarang = ?");
            $qnb->bind_param("s", $row['idbarang']); $qnb->execute(); $n = $qnb->get_result()->fetch_assoc();
            $nm = $n['nama_barang'] ?? $row['idbarang'];
            $idkat = $krow['idkategori'] ?? null;
            $lok = 'Gudang A';
            $keterangan = 'Auto create dari receive PO';
            $ins->bind_param("ssisssiiiiis", $idinv, $row['idbarang'], $idkat, $lok, $row['kodeproject'], $nm, 0, $stok, $stok, 0, $totalval, $keterangan);
            $ins->execute();
        }
    }
    header("Location: /pages/po/list.php");
    exit;
} else {
    echo "Tidak ada item pada PO ini";
}

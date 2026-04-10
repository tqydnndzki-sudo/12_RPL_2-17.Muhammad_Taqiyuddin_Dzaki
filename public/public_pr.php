<?php
require_once __DIR__ . "/db.php";
date_default_timezone_set("Asia/Jakarta");

// supervisor list (leader & manager)
$supervisor = $mysqli->query("SELECT iduser, nama FROM users WHERE rolestype IN (2,3) ORDER BY nama ASC");

// barang list
$barang = $mysqli->query("SELECT idbarang, nama_barang, harga FROM m_barang ORDER BY nama_barang ASC");

// submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $idreq = "PR" . time();
    $nama = trim($_POST['nama_requestor']);
    $sup = $_POST['supervisor'];
    $tglreq = $_POST['tgl_request'];
    $tglbutuh = $_POST['tgl_butuh'];
    $ket = trim($_POST['keterangan']);

    // insert header
    $stmt = $mysqli->prepare("INSERT INTO purchaserequest (idrequest, iduserrequest, namarequestor, keterangan, tgl_req, tgl_butuh, idsupervisor)
                              VALUES (?, NULL, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $idreq, $nama, $ket, $tglreq, $tglbutuh, $sup);
    $stmt->execute();

    // insert detail request
    if (isset($_POST['item_type'])) {
        foreach ($_POST['item_type'] as $i => $type) {

            if ($type === "with_code") {
                $idbarang = $_POST['kode_barang'][$i] ?? null;

                // get detail barang
                $q = $mysqli->prepare("SELECT nama_barang, deskripsi, harga, kodeproject FROM m_barang WHERE idbarang=?");
                $q->bind_param("s", $idbarang);
                $q->execute();
                $r = $q->get_result()->fetch_assoc();

                $qty = $_POST['qty'][$i];
                $link = $_POST['link'][$i];
                $total = $qty * $r['harga'];

                $ins = $mysqli->prepare("INSERT INTO detailrequest 
                (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $ins->bind_param("sssssiiss",
                    $idbarang,
                    $idreq,
                    $link,
                    $r['nama_barang'],
                    $r['deskripsi'],
                    $r['harga'],
                    $qty,
                    $total,
                    $r['kodeproject']
                );
                $ins->execute();

            } else { // without code
                $namaitem = $_POST['nama_free'][$i];
                $des = $_POST['deskripsi_free'][$i];
                $harga = (int)$_POST['harga_free'][$i];
                $qty = (int)$_POST['qty_free'][$i];
                $link = $_POST['link_free'][$i];
                $total = $harga * $qty;

                $ins = $mysqli->prepare("INSERT INTO detailrequest 
                (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)");

                $ins->bind_param("ssssiii",
                    $idreq,
                    $link,
                    $namaitem,
                    $des,
                    $harga,
                    $qty,
                    $total
                );
                $ins->execute();
            }
        }
    }

    header("Location: pr_success.php?id=" . $idreq);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Form Purchase Request</title>
<link rel="stylesheet" href="assets/public_pr.css">
</head>
<body>

<div class="container">
    <h2>Form Purchase Request</h2>

    <div class="info-box">
        <b>ℹ Informasi:</b> Sebelum melakukan purchase request mohon cek stok barang pada inventory.
    </div>

    <form method="POST">

        <div class="row">
            <div class="col">
                <label>Nama Requestor</label>
                <input type="text" name="nama_requestor" required>
            </div>

            <div class="col">
                <label>Pilih Supervisor</label>
                <select name="supervisor" required>
                    <option value="">-- Select --</option>
                    <?php while($s = $supervisor->fetch_assoc()): ?>
                        <option value="<?= $s['iduser'] ?>"><?= $s['nama'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col">
                <label>Tanggal Purchase Request</label>
                <input type="datetime-local" name="tgl_request" value="<?= date('Y-m-d\TH:i') ?>" required>
            </div>
            <div class="col">
                <label>Tanggal Dibutuhkan</label>
                <input type="date" name="tgl_butuh" required>
            </div>
        </div>

        <h3>Daftar Barang</h3>

        <div id="item-list"></div>

        <button type="button" class="add-btn" onclick="addItem()">+ Tambah Item</button>

        <label class="mt-20">Keterangan*</label>
        <textarea name="keterangan" required></textarea>

        <div class="action-btn">
            <a href="login.php" class="cancel">Cancel</a>
            <button type="submit" class="submit">Submit</button>
        </div>

    </form>
</div>

<script>
function addItem() {
    const box = document.getElementById("item-list");

    const html = `
    <div class="item-box">
        <button type="button" class="del-btn" onclick="this.parentNode.remove()">−</button>

        <label><input type="checkbox" onchange="toggleFreeForm(this)"> Saya tidak menemukan kode barang</label>

        <div class="with-code">
            <label>Kode Barang</label>
            <select name="kode_barang[]">
                <option value="">Select option</option>
                <?php $barang->data_seek(0); while($b = $barang->fetch_assoc()): ?>
                    <option value="<?= $b['idbarang'] ?>"><?= $b['idbarang'] ?> - <?= $b['nama_barang'] ?></option>
                <?php endwhile; ?>
            </select>

            <label>Qty</label>
            <input type="number" name="qty[]" value="1">

            <label>Link Pembelian</label>
            <input type="text" name="link[]">
            
            <input type="hidden" name="item_type[]" value="with_code">
        </div>

        <div class="free-code" style="display:none">
            <label>Nama Barang</label>
            <input type="text" name="nama_free[]">

            <label>Deskripsi</label>
            <input type="text" name="deskripsi_free[]">

            <label>Harga</label>
            <input type="number" name="harga_free[]" value="0">

            <label>Qty</label>
            <input type="number" name="qty_free[]" value="1">

            <label>Link Pembelian</label>
            <input type="text" name="link_free[]">

            <input type="hidden" name="item_type[]" value="without_code">
        </div>
    </div>
    `;

    box.insertAdjacentHTML('beforeend', html);
}

function toggleFreeForm(cb) {
    const parent = cb.closest('.item-box');
    const withCode = parent.querySelector('.with-code');
    const freeCode = parent.querySelector('.free-code');

    if (cb.checked) {
        withCode.style.display = 'none';
        freeCode.style.display = 'block';
    } else {
        withCode.style.display = 'block';
        freeCode.style.display = 'none';
    }
}
</script>

</body>
</html>

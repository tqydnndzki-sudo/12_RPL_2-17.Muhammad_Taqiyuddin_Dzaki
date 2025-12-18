<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ==============================================
// FETCH ITEM INVENTORY (dropdown kode barang)
// ==============================================
$qItems = $pdo->query("SELECT idbarang, kodebarang, nama_barang, deskripsi, harga, satuan, kodeproject 
                       FROM m_barang ORDER BY kodebarang ASC");
$items = $qItems->fetchAll(PDO::FETCH_ASSOC);

// ==============================================
// FETCH SUPERVISOR (role = Manager / Leader)
// ==============================================
$qSupervisor = $pdo->query("SELECT iduser, nama FROM users WHERE roletype IN ('Manager', 'Leader')");
$supervisors = $qSupervisor->fetchAll(PDO::FETCH_ASSOC);

// ==============================================
// HANDLE FORM SUBMIT
// ==============================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $namarequestor   = $_POST["namarequestor"];
    $idsupervisor    = $_POST["idsupervisor"];
    $tgl_req         = $_POST["tgl_req"];
    $tgl_butuh       = $_POST["tgl_butuh"];
    $keterangan      = $_POST["keterangan"];
    $iduserrequest   = $_SESSION['iduser'] ?? NULL;

    // Generate ID Request otomatis
    $lastReq = $pdo->query("SELECT idrequest FROM purchaserequest ORDER BY idrequest DESC LIMIT 1")->fetch();
    if ($lastReq) {
        $num = intval(substr($lastReq['idrequest'], 2)) + 1;
        $idrequest = "PR" . str_pad($num, 3, "0", STR_PAD_LEFT);
    } else {
        $idrequest = "PR001";
    }

    // Insert ke table purchase request
    $stmt = $pdo->prepare("INSERT INTO purchaserequest (idrequest, iduserrequest, namarequestor, keterangan, tgl_req, tgl_butuh, idsupervisor, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$idrequest, $iduserrequest, $namarequestor, $keterangan, $tgl_req, $tgl_butuh, $idsupervisor]);

    // Insert detail request multiple rows
    foreach ($_POST['kodebarang'] as $index => $idbarang) {

        $qty         = $_POST['qty'][$index];
        $satuan      = $_POST['satuan'][$index];
        $link        = $_POST['link'][$index];
        $kodeproject = $_POST['kodeproject'][$index];
        $namaitem    = $_POST['namaitem'][$index];
        $deskripsi   = $_POST['deskripsi'][$index];
        $harga       = $_POST['harga'][$index];
        $total       = $qty * $harga;

        // Cek apakah idbarang = "NEW" (barang baru belum ada di m_barang)
        if ($idbarang === "NEW") {
            
            // Generate kodebarang dan idbarang otomatis
            $lastBarang = $pdo->query("SELECT idbarang, kodebarang FROM m_barang ORDER BY idbarang DESC LIMIT 1")->fetch();
            
            if ($lastBarang) {
                // Extract nomor dari BRG-001 -> 001
                $numId = intval(substr($lastBarang['idbarang'], 4)) + 1;
                $newIdBarang = "BRG-" . str_pad($numId, 3, "0", STR_PAD_LEFT);
                
                // Extract nomor dari BR-001 -> 001
                $numKode = intval(substr($lastBarang['kodebarang'], 3)) + 1;
                $newKodeBarang = "BR-" . str_pad($numKode, 3, "0", STR_PAD_LEFT);
            } else {
                $newIdBarang = "BRG-001";
                $newKodeBarang = "BR-001";
            }

            // Insert barang baru ke m_barang
            $insBarang = $pdo->prepare("INSERT INTO m_barang (idbarang, kodebarang, nama_barang, deskripsi, harga, satuan, kodeproject, idkategori)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $insBarang->execute([$newIdBarang, $newKodeBarang, $namaitem, $deskripsi, $harga, $satuan, $kodeproject]);

            // Gunakan id barang yang baru dibuat
            $idbarang = $newIdBarang;
        }

        // Insert ke detailrequest
        $d = $pdo->prepare("INSERT INTO detailrequest
            (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

        $d->execute([$idbarang, $idrequest, $link, $namaitem, $deskripsi, $harga, $qty, $total, $kodeproject]);
    }

    $success_message = "Purchase request berhasil dibuat dengan ID: $idrequest";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Form Purchase Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">

<div class="max-w-6xl mx-auto p-6">

    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <strong>Sukses!</strong> <?= $success_message ?>
        </div>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <img src="../images/logo_ipo.png" alt="Logo" class="h-12">
            <h1 class="text-3xl font-bold text-gray-900">Form Purchase Request</h1>
        </div>
        <a href="../login.php" class="text-red-600 hover:underline">Kembali ke Login</a>
    </div>

    <div class="bg-white border p-5 rounded-lg shadow-sm mb-8">
        <p class="text-gray-600">
            Sebelum melakukan purchase request mohon cek stok barang pada inventory dan catat kode barang yang dibutuhkan,
            <a href="master-data.php" class="text-blue-600 underline">disini</a>.  
            Lihat kode project <a href="master-data.php#projects" class="text-blue-600 underline">disini</a>.
        </p>
    </div>

    <form method="POST">

        <!-- ===================== INPUT ATAS ====================== -->
        <div class="grid grid-cols-3 gap-4 mb-8">

            <div>
                <label class="block font-semibold mb-1">Nama Requestor</label>
                <input type="text" name="namarequestor" class="w-full border rounded p-2" required>
            </div>

            <div>
                <label class="block font-semibold mb-1">Pilih Supervisor</label>
                <select name="idsupervisor" class="w-full border p-2 rounded" required>
                    <option value="">Manager Operasional</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['iduser'] ?>"><?= $s['nama'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-semibold mb-1">Tanggal Purchase Request</label>
                <input type="datetime-local" name="tgl_req" class="w-full border p-2 rounded" required>
            </div>

            <div>
                <label class="block font-semibold mb-1">Tanggal Dibutuhkan</label>
                <input type="date" name="tgl_butuh" class="w-full border p-2 rounded" required>
            </div>

        </div>

        <!-- ===================== DAFTAR BARANG ======================== -->
        <h2 class="text-xl font-bold mb-3">Daftar Barang</h2>

        <div id="item-wrapper" class="space-y-6">

            <!-- ITEM TEMPLATE -->
            <div class="border rounded-lg p-4 bg-white shadow-sm item-row">

                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-700">Item Pembelian</h3>
                    <button type="button" class="text-red-500 font-bold remove-item">Hapus</button>
                </div>

                <div class="grid grid-cols-4 gap-4">

                    <div>
                        <label class="font-semibold text-sm">Kode Barang</label>
                        <select name="kodebarang[]" class="w-full border p-2 rounded select-item" required>
                            <option value="">Select item</option>
                            <?php foreach ($items as $it): ?>
                                <option 
                                    value="<?= $it['idbarang'] ?>" 
                                    data-namaitem="<?= htmlspecialchars($it['nama_barang']) ?>"
                                    data-deskripsi="<?= htmlspecialchars($it['deskripsi']) ?>"
                                    data-harga="<?= $it['harga'] ?>"
                                    data-kodeproject="<?= $it['kodeproject'] ?>"
                                >
                                    <?= $it['kodebarang'] ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="NEW" data-new="true">+ Barang Baru</option>
                        </select>
                    </div>

                    <div>
                        <label class="font-semibold text-sm">Nama Item</label>
                        <input name="namaitem[]" class="w-full border p-2 rounded namaitem" required>
                    </div>

                    <div>
                        <label class="font-semibold text-sm">Deskripsi</label>
                        <input name="deskripsi[]" class="w-full border p-2 rounded deskripsi" required>
                    </div>

                    <div>
                        <label class="font-semibold text-sm">Harga</label>
                        <input name="harga[]" type="number" class="w-full border p-2 rounded harga" required>
                    </div>

                    <div>
                        <label class="font-semibold text-sm">Qty</label>
                        <input name="qty[]" type="number" value="2" class="w-full border p-2 rounded" required>
                    </div>

                    <div>
                        <label class="font-semibold text-sm">Satuan</label>
                        <select name="satuan[]" class="w-full border p-2 rounded" required>
                            <option value="">Pilih Satuan</option>
                            <option value="pcs">pcs</option>
                            <option value="unit">unit</option>
                            <option value="buah">buah</option>
                            <option value="set">set</option>
                            <option value="box">box</option>
                            <option value="pack">pack</option>
                            <option value="roll">roll</option>
                            <option value="meter">meter</option>
                            <option value="kg">kg</option>
                            <option value="liter">liter</option>
                        </select>
                    </div>

                    <div>
                        <label class="font-semibold text-sm">Link Pembelian</label>
                        <input name="link[]" class="w-full border p-2 rounded">
                    </div>

                    <div>
                        <label class="font-semibold text-sm">Kode Project</label>
                        <input name="kodeproject[]" class="w-full border p-2 rounded kodeproject" required>
                    </div>

                </div>

            </div>

        </div>

        <div class="mt-4">
            <button type="button" id="add-item" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                + Tambah Barang
            </button>
        </div>

        <!-- ===================== KETERANGAN ======================== -->
        <div class="mt-8">
            <label class="font-semibold">Keterangan *</label>
            <textarea name="keterangan" class="w-full border p-3 rounded h-28" required></textarea>
        </div>

        <!-- ===================== BUTTON ======================== -->
        <div class="flex justify-between mt-6">
            <a href="procuerment.php" class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-blue-700 text-white rounded hover:bg-blue-800">Submit</button>
        </div>

    </form>
</div>

<!-- ======================= JAVASCRIPT ======================= -->
<script>
// Add new item row
document.getElementById("add-item").addEventListener("click", () => {
    let wrapper = document.getElementById("item-wrapper");
    let clone = wrapper.children[0].cloneNode(true);

    // Reset semua field
    clone.querySelectorAll("input").forEach(i => {
        if (i.name === "qty[]") {
            i.value = "2";
        } else {
            i.value = "";
        }
    });
    // Reset all select elements
    clone.querySelectorAll("select").forEach(select => {
        select.selectedIndex = 0;
    });

    wrapper.appendChild(clone);
});

// Remove item row
document.addEventListener("click", function(e){
    if(e.target.classList.contains("remove-item")){
        if(document.querySelectorAll(".item-row").length > 1){
            e.target.closest(".item-row").remove();
        } else {
            alert("Minimal harus ada 1 item!");
        }
    }
});

// Auto fill item details ketika memilih kode barang
document.addEventListener("change", function(e){
    if(e.target.classList.contains("select-item")){
        let opt = e.target.selectedOptions[0];
        let parent = e.target.closest(".item-row");

        // Cek jika pilihan adalah "Barang Baru"
        if (opt.dataset.new === "true") {
            // Kosongkan semua field dan biarkan user input manual
            parent.querySelector(".namaitem").value = "";
            parent.querySelector(".namaitem").readOnly = false;
            parent.querySelector(".deskripsi").value = "";
            parent.querySelector(".deskripsi").readOnly = false;
            parent.querySelector(".harga").value = "";
            parent.querySelector(".harga").readOnly = false;
            parent.querySelector(".kodeproject").value = "";
            parent.querySelector(".kodeproject").readOnly = false;
            
            // Tambahkan notifikasi visual
            parent.style.backgroundColor = "#fef3c7";
            setTimeout(() => {
                parent.style.backgroundColor = "";
            }, 2000);
        } else {
            // Auto fill dari data yang sudah ada
            parent.querySelector(".namaitem").value = opt.dataset.namaitem || "";
            parent.querySelector(".namaitem").readOnly = true;
            parent.querySelector(".deskripsi").value = opt.dataset.deskripsi || "";
            parent.querySelector(".deskripsi").readOnly = true;
            parent.querySelector(".harga").value = opt.dataset.harga || "";
            parent.querySelector(".harga").readOnly = true;
            parent.querySelector(".kodeproject").value = opt.dataset.kodeproject || "";
            parent.querySelector(".kodeproject").readOnly = true;
        }
    }
});
</script>

</body>
</html>
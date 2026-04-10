<?php
/**
 * PURCHASE REQUEST FORM - Redesigned
 * Professional Multi-Section Layout
 */
require_once '../includes/auth.php';
require_once '../config/database.php';

$message = '';
$messageType = '';

// Get next kode barang
$lastBarangStmt = $pdo->query("SELECT kodebarang FROM m_barang WHERE kodebarang LIKE 'BRG-%' ORDER BY kodebarang DESC LIMIT 1");
$lastBarang = $lastBarangStmt->fetchColumn();
$nextBarangNum = $lastBarang ? (int)substr($lastBarang, 4) + 1 : 1;
$nextKodeBarang = 'BRG-' . str_pad($nextBarangNum, 3, '0', STR_PAD_LEFT);

// Get next idbarang from existing data
$maxIdBarang = $pdo->query("SELECT MAX(idbarang) FROM m_barang")->fetchColumn();
$nextIdBarang = $maxIdBarang ? (int)$maxIdBarang + 1 : 1;

// Get next kode project
$lastProjectStmt = $pdo->query("SELECT kodeproject FROM detailrequest WHERE kodeproject LIKE 'PRJ-%' ORDER BY kodeproject DESC LIMIT 1");
$lastProject = $lastProjectStmt->fetchColumn();
$nextProjectNum = $lastProject ? (int)substr($lastProject, 4) + 1 : 1;
$nextKodeProject = 'PRJ-' . str_pad($nextProjectNum, 2, '0', STR_PAD_LEFT);

// Get data
$barangStmt = $pdo->query("SELECT idbarang, kodebarang, nama_barang, harga, satuan FROM m_barang ORDER BY nama_barang");
$barangList = $barangStmt->fetchAll(PDO::FETCH_ASSOC);

$userStmt = $pdo->query("SELECT iduser, username, nama FROM users WHERE roletype IN ('Staff', 'Leader', 'Direktur Utama', 'Sekretaris') ORDER BY nama");
$userList = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$supervisorStmt = $pdo->query("SELECT iduser, username, nama FROM users WHERE roletype = 'Leader' ORDER BY nama");
$supervisorList = $supervisorStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pr'])) {
    try {
        $pdo->beginTransaction();

        $namarequestor = trim($_POST['namarequestor'] ?? '');
        $idsupervisor = trim($_POST['idsupervisor'] ?? '');
        $tgl_req = trim($_POST['tgl_req'] ?? '');
        $tgl_butuh = trim($_POST['tgl_butuh'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if (empty($namarequestor) || empty($idsupervisor) || empty($keterangan)) {
            throw new Exception("Semua field wajib diisi!");
        }

        $prCount = $pdo->query("SELECT COUNT(*) FROM purchaserequest")->fetchColumn();
        $idrequest = 'PR' . date('Y') . str_pad($prCount + 1, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO purchaserequest (idrequest, tgl_req, namarequestor, keterangan, tgl_butuh, idsupervisor, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$idrequest, $tgl_req, $namarequestor, $keterangan, $tgl_butuh, $idsupervisor]);

        $itemTypes = $_POST['item_type'] ?? [];
        $projectCode = $nextKodeProject;
        
        // Get fresh MAX numeric idbarang inside transaction to prevent race condition
        // idbarang is VARCHAR, so we need to find max numeric ID only
        $maxIdResult = $pdo->query("SELECT MAX(CAST(idbarang AS UNSIGNED)) as max_id FROM m_barang WHERE idbarang REGEXP '^[0-9]+$'")->fetch();
        $currentIdBarang = $maxIdResult['max_id'] ? (int)$maxIdResult['max_id'] + 1 : 1;
        
        // Get fresh kodebarang inside transaction
        $lastKodeResult = $pdo->query("SELECT kodebarang FROM m_barang WHERE kodebarang LIKE 'BRG-%' ORDER BY kodebarang DESC LIMIT 1 FOR UPDATE")->fetch();
        $nextBarangNum = $lastKodeResult ? (int)substr($lastKodeResult['kodebarang'], 4) + 1 : 1;

        foreach ($itemTypes as $index => $itemType) {
            $deskripsi = trim($_POST['deskripsi'][$index] ?? '');
            $harga = floatval($_POST['harga'][$index] ?? 0);
            $qty = intval($_POST['qty'][$index] ?? 0);
            $linkpembelian = trim($_POST['linkpembelian'][$index] ?? '');
            $total = $harga * $qty;

            if ($qty <= 0 || $harga <= 0) {
                throw new Exception("Item #" . ($index + 1) . ": Quantity dan harga harus > 0!");
            }

            $idbarang = null;
            $namaitem = '';

            if ($itemType === 'existing') {
                $existingBarang = trim($_POST['existing_barang'][$index] ?? '');
                if (empty($existingBarang)) {
                    throw new Exception("Item #" . ($index + 1) . ": Pilih barang!");
                }

                $stmt = $pdo->prepare("SELECT idbarang, nama_barang FROM m_barang WHERE idbarang = ?");
                $stmt->execute([$existingBarang]);
                $barang = $stmt->fetch();

                if (!$barang) {
                    throw new Exception("Item #" . ($index + 1) . ": Barang tidak ditemukan!");
                }

                $idbarang = $barang['idbarang'];
                $namaitem = $barang['nama_barang'];
            } else {
                $namaitem = trim($_POST['namaitem_manual'][$index] ?? '');
                if (empty($namaitem)) {
                    throw new Exception("Item #" . ($index + 1) . ": Nama item wajib diisi!");
                }

                // Generate kodebarang with auto-increment
                $kodebarang = 'BRG-' . str_pad($nextBarangNum, 3, '0', STR_PAD_LEFT);
                $nextBarangNum++;

                // Use sequential ID from transaction-safe query
                $newBarangId = $currentIdBarang;
                $currentIdBarang++;

                // Determine category automatically based on price
                $idkategori = 1; // Default: Inventory
                if ($harga < 5000) {
                    $idkategori = 5; // RAKO - Komponen dibawah 5000
                } elseif ($harga >= 5000 && $harga <= 100000) {
                    $idkategori = 1; // Inventory - Antara 5000-100000
                } elseif ($harga > 100000) {
                    $idkategori = 2; // Asset - Item diatas 100.000
                }

                $stmt = $pdo->prepare("INSERT INTO m_barang (idbarang, kodebarang, nama_barang, harga, satuan, idkategori) VALUES (?, ?, ?, ?, 'PCS', ?)");
                $stmt->execute([$newBarangId, $kodebarang, $namaitem, $harga, $idkategori]);
                $idbarang = $newBarangId;
            }

            $stmt = $pdo->prepare("INSERT INTO detailrequest (idrequest, idbarang, namaitem, deskripsi, harga, qty, total, linkpembelian, kodeproject) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$idrequest, $idbarang, $namaitem, $deskripsi, $harga, $qty, $total, $linkpembelian, $projectCode]);
        }

        $pdo->commit();
        $message = "Purchase Request berhasil! ID: $idrequest";
        $messageType = 'success';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Request - Simba</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f5f7fa; min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header */
        .page-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px 35px; margin-bottom: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .company-logo { width: 80px; height: auto; display: flex; align-items: center; justify-content: center; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .company-logo img { max-width: 100%; max-height: 80px; object-fit: contain; }
        .header-title h1 { font-size: 24px; font-weight: 700; color: #1a1a1a; margin-bottom: 4px; }
        .header-title p { font-size: 14px; color: #666; }
        .header-badge { background: linear-gradient(135deg, #FF8C00 0%, #FF6B00 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        
        /* Sections */
        .form-section { background: white; padding: 30px; margin-bottom: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .section-icon { width: 40px; height: 40px; background: linear-gradient(135deg, #FF8C00 0%, #FF6B00 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; }
        .section-header h2 { font-size: 18px; font-weight: 700; color: #1a1a1a; }
        .section-header p { font-size: 13px; color: #666; margin-top: 2px; }
        
        /* Form */
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 8px; }
        .required { color: #FF6B00; margin-left: 4px; }
        .auto-badge { background: #28a745; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; margin-left: 8px; font-weight: 600; }
        
        input, select, textarea { padding: 12px 16px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.2s; background: #fafafa; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #FF8C00; box-shadow: 0 0 0 3px rgba(255,140,0,0.1); background: white; }
        input[readonly] { background: #f5f5f5; cursor: not-allowed; color: #999; }
        textarea { resize: vertical; min-height: 80px; }
        
        /* Message */
        .message { padding: 16px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        
        /* Item Card */
        .item-card { background: #fafbfc; border: 1.5px solid #e8e8e8; border-radius: 10px; padding: 25px; margin-bottom: 20px; }
        .item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .item-title { font-size: 16px; font-weight: 700; color: #FF6B00; }
        .btn-remove { background: #dc3545; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        
        /* Mode Toggle */
        .mode-toggle { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
        .mode-btn { padding: 12px 16px; border: 1.5px solid #e0e0e0; background: white; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; text-align: center; }
        .mode-btn.active { background: #28a745; color: white; border-color: #28a745; }
        
        /* Buttons */
        .btn-add { background: #28a745; color: white; border: none; padding: 14px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; width: 100%; margin-bottom: 20px; }
        .btn-submit { background: linear-gradient(135deg, #FF8C00 0%, #FF6B00 100%); color: white; border: none; padding: 16px 32px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 700; width: 100%; box-shadow: 0 4px 12px rgba(255,140,0,0.3); }
        .btn-submit:hover { box-shadow: 0 6px 16px rgba(255,140,0,0.4); transform: translateY(-2px); }
        
        .back-button { display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #FF8C00 0%, #FF6B00 100%); color: white; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 28px; border-radius: 10px; box-shadow: 0 4px 12px rgba(255,140,0,0.3); transition: all 0.3s ease; margin-top: 25px; }
        .back-button:hover { box-shadow: 0 6px 16px rgba(255,140,0,0.4); transform: translateY(-2px); }
        .back-button i { font-size: 16px; transition: transform 0.3s ease; }
        .back-button:hover i { transform: translateX(-4px); }
        .hidden { display: none; }
        
        @media (max-width: 1024px) { .form-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .page-header { flex-direction: column; gap: 20px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="header-left">
                <div class="company-logo">
                    <?php
                    $logoPath = __DIR__ . '/../images/logo_ipo.png';
                    echo file_exists($logoPath) ? '<img src="../images/logo_ipo.png" alt="Logo">' : '<i class="fas fa-building" style="font-size: 32px; color: white;"></i>';
                    ?>
                </div>
                <div class="header-title">
                    <h1>Purchase Request</h1>
                    <p>Form permintaan pembelian barang</p>
                </div>
            </div>
            <div class="header-badge">Simba System</div>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size: 20px;"></i>
            <div><?= htmlspecialchars($message) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="prForm">
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-info-circle"></i></div>
                    <div>
                        <h2>Informasi Request</h2>
                        <p>Detail pengajuan purchase request</p>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nama Requestor <span class="required">*</span></label>
                        <input type="text" name="namarequestor" placeholder="Masukkan nama requestor" required>
                    </div>

                    <div class="form-group">
                        <label>Supervisor <span class="required">*</span></label>
                        <select name="idsupervisor" required>
                            <option value="">-- Pilih Supervisor --</option>
                            <?php foreach ($supervisorList as $sup): ?>
                            <option value="<?= $sup['iduser'] ?>"><?= htmlspecialchars($sup['nama'] ?: $sup['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Request <span class="required">*</span></label>
                        <input type="datetime-local" name="tgl_req" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Dibutuhkan <span class="required">*</span></label>
                        <input type="date" name="tgl_butuh" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Keterangan <span class="required">*</span></label>
                        <textarea name="keterangan" placeholder="Jelaskan tujuan purchase request..." required></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fas fa-box"></i></div>
                    <div>
                        <h2>Daftar Barang</h2>
                        <p>Item yang diminta</p>
                    </div>
                </div>

                <div id="itemsContainer">
                    <div class="item-card" data-index="0">
                        <div class="item-header">
                            <span class="item-title">Item #1</span>
                            <button type="button" class="btn-remove" onclick="removeItem(this)" style="display: none;">Hapus</button>
                        </div>

                        <div class="mode-toggle">
                            <button type="button" class="mode-btn active" onclick="setMode(this, 'existing')">Pilih dari Database</button>
                            <button type="button" class="mode-btn" onclick="setMode(this, 'manual')">Input Manual</button>
                        </div>

                        <input type="hidden" name="item_type[]" value="existing">

                        <div class="existing-mode">
                            <div class="form-grid" style="margin-bottom: 20px;">
                                <div class="form-group full-width">
                                    <label>Pilih Barang <span class="required">*</span></label>
                                    <select name="existing_barang[]" onchange="fillBarangDetails(this)">
                                        <option value="">-- Pilih Barang --</option>
                                        <?php foreach ($barangList as $barang): ?>
                                        <option value="<?= $barang['idbarang'] ?>" data-nama="<?= htmlspecialchars($barang['nama_barang']) ?>" data-harga="<?= $barang['harga'] ?>">
                                            <?= htmlspecialchars($barang['kodebarang']) ?> - <?= htmlspecialchars($barang['nama_barang']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="manual-mode hidden">
                            <div class="form-grid" style="margin-bottom: 20px;">
                                <div class="form-group">
                                    <label>Nama Item <span class="required">*</span></label>
                                    <input type="text" name="namaitem_manual[]" placeholder="Nama barang">
                                </div>
                                <div class="form-group">
                                    <label>Kode Barang <span class="auto-badge">Auto</span></label>
                                    <input type="text" value="<?= $nextKodeBarang ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Harga <span class="required">*</span></label>
                                <input type="number" name="harga[]" min="0" step="1000" placeholder="0" required onchange="calculateTotal(this)" onkeyup="calculateTotal(this)">
                            </div>
                            <div class="form-group">
                                <label>Quantity <span class="required">*</span></label>
                                <input type="number" name="qty[]" min="1" value="1" required onchange="calculateTotal(this)" onkeyup="calculateTotal(this)">
                            </div>
                            <div class="form-group">
                                <label>Total</label>
                                <input type="text" class="total-display" value="Rp 0" readonly style="font-weight: 600; color: #FF6B00;">
                                <input type="hidden" name="total[]" value="0">
                            </div>
                            <div class="form-group">
                                <label>Kode Project <span class="auto-badge">Auto</span></label>
                                <input type="text" value="<?= $nextKodeProject ?>" readonly>
                                <input type="hidden" name="kodeproject[]" value="<?= $nextKodeProject ?>">
                            </div>
                            <div class="form-group full-width">
                                <label>Deskripsi</label>
                                <textarea name="deskripsi[]" placeholder="Spesifikasi tambahan..." rows="2"></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label>Link Pembelian</label>
                                <input type="url" name="linkpembelian[]" placeholder="https://example.com">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-add" onclick="addItem()"><i class="fas fa-plus"></i> Tambah Item</button>
                <button type="submit" name="submit_pr" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Purchase Request</button>
            </div>
        </form>

        <a href="../login.php" class="back-button"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
    </div>

    <script>
        let itemIndex = 1;

        function setMode(btn, mode) {
            const card = btn.closest('.item-card');
            card.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            card.querySelector('input[name="item_type[]"]').value = mode;
            card.querySelector('.existing-mode').classList.toggle('hidden', mode !== 'existing');
            card.querySelector('.manual-mode').classList.toggle('hidden', mode !== 'manual');
        }

        function fillBarangDetails(select) {
            const option = select.options[select.selectedIndex];
            const card = select.closest('.item-card');
            if (option.value) {
                card.querySelector('input[name="harga[]"]').value = option.dataset.harga;
                calculateTotal(card.querySelector('input[name="harga[]"]'));
            }
        }

        function calculateTotal(input) {
            const card = input.closest('.item-card');
            const harga = parseFloat(card.querySelector('input[name="harga[]"]').value) || 0;
            const qty = parseFloat(card.querySelector('input[name="qty[]"]').value) || 0;
            const total = harga * qty;
            card.querySelector('.total-display').value = 'Rp ' + total.toLocaleString('id-ID');
            card.querySelector('input[name="total[]"]').value = total;
        }

        function addItem() {
            const container = document.getElementById('itemsContainer');
            const firstItem = container.querySelector('.item-card');
            const newItem = firstItem.cloneNode(true);
            itemIndex++;
            newItem.dataset.index = itemIndex;
            newItem.querySelector('.item-title').textContent = 'Item #' + itemIndex;
            newItem.querySelector('.btn-remove').style.display = 'block';
            newItem.querySelectorAll('input, select, textarea').forEach(input => {
                if (input.type !== 'hidden' && input.name !== 'item_type[]') input.value = '';
            });
            newItem.querySelector('.total-display').value = 'Rp 0';
            newItem.querySelector('input[name="total[]"]').value = '0';
            newItem.querySelector('input[name="qty[]"]').value = '1';
            container.appendChild(newItem);
        }

        function removeItem(btn) {
            btn.closest('.item-card').remove();
        }

        // Calculate totals on page load for all items
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.item-card').forEach(card => {
                const hargaInput = card.querySelector('input[name="harga[]"]');
                if (hargaInput && hargaInput.value) {
                    calculateTotal(hargaInput);
                }
            });
        });

        document.getElementById('prForm').addEventListener('submit', function(e) {
            const items = document.querySelectorAll('.item-card');
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const mode = item.querySelector('input[name="item_type[]"]').value;
                if (mode === 'existing' && !item.querySelector('select[name="existing_barang[]"]').value) {
                    alert('Item #' + (i + 1) + ': Pilih barang!');
                    e.preventDefault();
                    return;
                }
                if (mode === 'manual' && !item.querySelector('input[name="namaitem_manual[]"]').value.trim()) {
                    alert('Item #' + (i + 1) + ': Nama item wajib diisi!');
                    e.preventDefault();
                    return;
                }
                
                // Recalculate total before submit to ensure correct value
                const hargaInput = item.querySelector('input[name="harga[]"]');
                calculateTotal(hargaInput);
            }
        });
    </script>
</body>
</html>

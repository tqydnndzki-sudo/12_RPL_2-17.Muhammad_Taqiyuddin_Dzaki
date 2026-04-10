<?php
// track_pr.php
// Pastikan file db.php terhubung (sesuaikan path jika perlu)
require_once __DIR__ . '/db.php'; // jika file ada di root; ubah '../db.php' kalau berada di /public

// helper: ambil latest status dari logstatusreq
function get_latest_logstatusreq($mysqli, $idrequest) {
    $stmt = $mysqli->prepare("SELECT status, date, note_reject FROM logstatusreq WHERE idrequest = ? ORDER BY date DESC LIMIT 1");
    $stmt->bind_param("s", $idrequest);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_latest_logstatusorder($mysqli, $idpo) {
    $stmt = $mysqli->prepare("SELECT status, date, keterangan FROM logstatusorder WHERE idpurchaseorder = ? ORDER BY date DESC LIMIT 1");
    $stmt->bind_param("s", $idpo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// default empty
$pr = null;
$details = [];
$pos = [];
$pos_details = [];
$pr_log = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_id = trim($_POST['search_pr'] ?? '');
    if ($search_id === '') {
        $error = "Masukkan nomor Purchase Request terlebih dahulu.";
    } else {
        // ambil purchaserequest
        $s = $mysqli->prepare("SELECT p.*, u.nama as supervisor_name FROM purchaserequest p LEFT JOIN user u ON p.idsupervisor = u.iduser WHERE p.idrequest = ?");
        $s->bind_param("s", $search_id);
        $s->execute();
        $pr = $s->get_result()->fetch_assoc();

        if (!$pr) {
            $error = "Purchase Request dengan ID '{$search_id}' tidak ditemukan.";
        } else {
            // ambil detailrequest
            $dq = $mysqli->prepare("SELECT * FROM detailrequest WHERE idrequest = ? ORDER BY iddetailrequest ASC");
            $dq->bind_param("s", $search_id);
            $dq->execute();
            $resd = $dq->get_result();
            while ($r = $resd->fetch_assoc()) $details[] = $r;

            // ambil PO yang terkait (purchaseorder.idrequest = idrequest)
            $pq = $mysqli->prepare("SELECT * FROM purchaseorder WHERE idrequest = ? ORDER BY tgl_purchase ASC");
            $pq->bind_param("s", $search_id);
            $pq->execute();
            $resp = $pq->get_result();
            while ($row = $resp->fetch_assoc()) {
                $pos[] = $row;
                // ambil detail order tiap PO
                $dq2 = $mysqli->prepare("SELECT * FROM detailorder WHERE idpurchaseorder = ?");
                $dq2->bind_param("s", $row['idpurchaseorder']);
                $dq2->execute();
                $r2 = $dq2->get_result();
                $arr = [];
                while ($rr = $r2->fetch_assoc()) $arr[] = $rr;
                $pos_details[$row['idpurchaseorder']] = $arr;
            }

            // ambil latest logstatusreq
            $pr_log = get_latest_logstatusreq($mysqli, $search_id);
        }
    }
}

// helper for status label
function map_pr_status_label($status_code) {
    // if you used codes: 1=draft,2=approval_leader,3=approved,4=completed etc.
    // adjust mapping to your system if different
    $map = [
        1 => 'Draft',
        2 => 'Approval Leader',
        3 => 'Approved (Manager)',
        4 => 'Procurement / Completed',
        5 => 'Rejected'
    ];
    return $map[$status_code] ?? 'Unknown';
}

// compute a progress percent heuristic
function compute_progress($pr_log, $pos) {
    // basic heuristic:
    // if no log -> 0%
    // if status 1 -> 10
    // status 2 -> 40
    // status 3 -> 70
    // status 4 -> 90 + if PO exists and received -> 100
    $percent = 0;
    if (!$pr_log) return 0;
    $s = (int)$pr_log['status'];
    if ($s <= 1) $percent = 10;
    if ($s == 2) $percent = 40;
    if ($s == 3) $percent = 70;
    if ($s >= 4) $percent = 90;

    // if there is at least one PO and logstatusorder indicates received (status >=3) we bump to 100
    foreach ($pos as $po) {
        // check latest order status
        global $mysqli;
        $ls = get_latest_logstatusorder($mysqli, $po['idpurchaseorder']);
        if ($ls && intval($ls['status']) >= 3) {
            $percent = 100;
            break;
        }
    }
    return $percent;
}

$progress = compute_progress($pr_log, $pos);

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Lacak Purchase Request - SIMBA</title>

  <!-- Tailwind Play CDN (prototyping) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* small style for circle progress */
    .circle {
      width: 180px; height: 180px; border-radius: 50%;
      display:flex;align-items:center;justify-content:center;
      font-weight:700;color:#0f172a;background:conic-gradient(#3b82f6 var(--p), #e6eefb 0);
      box-shadow: 0 8px 20px rgba(2,6,23,0.06);
    }
    .circle-inner {
      width: 120px;height:120px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;flex-direction:column;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800">

  <div class="max-w-7xl mx-auto py-10 px-6">

    <h1 class="text-2xl font-semibold mb-6">Lacak Purchase Request-Mu Disini</h1>

    <form method="post" class="bg-white p-6 rounded shadow mb-8">
      <div class="flex gap-4 items-center">
        <div class="flex items-center text-gray-500">
          <svg class="w-8 h-8 mr-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M3 3h18v4H3z M3 10h18v11H3z"/>
          </svg>
        </div>

        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700">Masukkan No Purchase Request Disini</label>
          <input name="search_pr" placeholder="Contoh: PR163..." value="<?= htmlspecialchars($_POST['search_pr'] ?? '') ?>" class="mt-2 block w-full rounded border-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
        </div>

        <div>
          <button type="submit" class="bg-indigo-700 hover:bg-indigo-800 text-white px-6 py-2 rounded">Lacak Status</button>
        </div>
      </div>
      <?php if($error): ?>
        <p class="mt-3 text-sm text-red-600"><?=htmlspecialchars($error)?></p>
      <?php endif; ?>
    </form>

    <!-- Results section -->
    <div class="grid grid-cols-12 gap-8">
      <div class="col-span-8">

        <div class="bg-white p-6 rounded shadow mb-6">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm text-gray-500">Supervisor :</p>
              <p class="text-lg font-medium mt-2"><?= $pr ? htmlspecialchars($pr['supervisor_name'] ?? '—') : '—' ?></p>
            </div>

            <div class="text-right">
              <p class="text-sm text-gray-500">Tgl Buat :</p>
              <p class="text-lg font-medium mt-2"><?= $pr ? htmlspecialchars($pr['tgl_req']) : '—' ?></p>

              <p class="text-sm text-gray-500 mt-4">Tgl Dibutuhkan :</p>
              <p class="text-lg font-medium mt-2"><?= $pr ? htmlspecialchars($pr['tgl_butuh']) : '—' ?></p>
            </div>
          </div>
        </div>

        <div class="bg-white p-4 rounded shadow">
          <h3 class="font-semibold mb-4">Detail Barang</h3>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kode Barang</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kode Project</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama Barang</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">No PO</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">status_po</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">status_pr</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">
                <?php if(empty($details)): ?>
                  <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Tidak ada record</td></tr>
                <?php else: ?>
                  <?php foreach($details as $d): 
                    // find if there is a related PO for this item (by idbarang matching detailorder)
                    $linked_po = '';
                    $status_po_label = '-';
                    foreach($pos as $po) {
                      if(isset($pos_details[$po['idpurchaseorder']])) {
                        foreach($pos_details[$po['idpurchaseorder']] as $poit) {
                          if($poit['idbarang'] == $d['idbarang']) {
                            $linked_po = $po['idpurchaseorder'];
                            $ls = get_latest_logstatusorder($mysqli, $linked_po);
                            $status_po_label = $ls ? ('Status ' . intval($ls['status'])) : '-';
                            break 2;
                          }
                        }
                      }
                    }
                    // status pr from pr_log
                    $status_pr_label = $pr_log ? map_pr_status_label(intval($pr_log['status'])) : '-';
                  ?>
                    <tr>
                      <td class="px-4 py-3 text-sm"><?= htmlspecialchars($d['idbarang'] ?? '-') ?></td>
                      <td class="px-4 py-3 text-sm"><?= htmlspecialchars($d['kodeproject'] ?? '-') ?></td>
                      <td class="px-4 py-3 text-sm"><?= htmlspecialchars($d['namaitem'] ?? '-') ?></td>
                      <td class="px-4 py-3 text-sm"><?= $d['harga'] ? number_format($d['harga'],0,',','.') : '-' ?></td>
                      <td class="px-4 py-3 text-sm"><?= htmlspecialchars($d['qty']) ?></td>
                      <td class="px-4 py-3 text-sm"><?= $d['total'] ? number_format($d['total'],0,',','.') : '-' ?></td>
                      <td class="px-4 py-3 text-sm"><?= $linked_po ?: '-' ?></td>
                      <td class="px-4 py-3 text-sm"><?= htmlspecialchars($status_po_label) ?></td>
                      <td class="px-4 py-3 text-sm"><?= htmlspecialchars($status_pr_label) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Purchase Order Info & table (below) -->
        <div class="mt-6 bg-white p-4 rounded shadow">
          <h3 class="font-semibold mb-4">Berikut adalah informasi Purchase Order yang terkait,</h3>

          <div class="grid grid-cols-12 gap-6">
            <div class="col-span-8">
              <p class="font-medium">Requestor : <span class="font-normal"><?= $pr ? htmlspecialchars($pr['namarequestor']) : '-' ?></span></p>
              <p class="font-medium mt-2">Tanggal Purchase Order : <span class="font-normal"><?= (!empty($pos) ? htmlspecialchars($pos[0]['tgl_purchase']) : '-') ?></span></p>

              <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">kodebarang</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">kodeproject</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">nama barang</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">harga</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">qty</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">total</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-100">
                    <?php
                      // If there are PO details, list them
                      if(empty($pos)): ?>
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada PO terkait</td></tr>
                      <?php else:
                        // show all PO details grouped
                        foreach ($pos as $po) {
                          $poid = $po['idpurchaseorder'];
                          $poitems = $pos_details[$poid] ?? [];
                          foreach($poitems as $pi) {
                    ?>
                        <tr>
                          <td class="px-4 py-3 text-sm"><?= htmlspecialchars($pi['idbarang'] ?? '-') ?></td>
                          <td class="px-4 py-3 text-sm"><?= htmlspecialchars($pi['kodeproject'] ?? '-') ?></td>
                          <td class="px-4 py-3 text-sm"><?= htmlspecialchars($pi['namaitem'] ?? '-') ?></td>
                          <td class="px-4 py-3 text-sm"><?= $pi['harga'] ? number_format($pi['harga'],0,',','.') : '-' ?></td>
                          <td class="px-4 py-3 text-sm"><?= htmlspecialchars($pi['qty']) ?></td>
                          <td class="px-4 py-3 text-sm"><?= ($pi['harga'] && $pi['qty']) ? number_format($pi['harga'] * $pi['qty'],0,',','.') : '-' ?></td>
                        </tr>
                    <?php } } endif; ?>
                  </tbody>
                </table>
              </div>

            </div>

            <div class="col-span-4 flex items-center justify-center">
              <div>
                <div class="circle" style="--p: <?= $progress ?>deg;">
                  <div class="circle-inner">
                    <div class="text-4xl text-indigo-700"><?= $progress ?>%</div>
                    <div class="text-xs text-gray-400">Completed</div>
                  </div>
                </div>
                <div class="text-center mt-4 text-sm text-gray-500">Progress berdasarkan status PR & PO</div>
              </div>
            </div>
          </div>

        </div>

      </div>

      <div class="col-span-4">
        <!-- side panel / quick info -->
        <div class="bg-white p-6 rounded shadow sticky top-6">
          <h3 class="font-semibold mb-3">Informasi Singkat</h3>
          <p class="text-sm text-gray-600">Gunakan nomor PR yang dikirimkan setelah submit form. Contoh: PR1631234567</p>

          <div class="mt-6">
            <p class="text-sm text-gray-500">Status PR saat ini:</p>
            <p class="mt-2 font-medium"><?= $pr_log ? map_pr_status_label(intval($pr_log['status'])) : '—' ?></p>
          </div>

          <div class="mt-6">
            <p class="text-sm text-gray-500">Catatan / Rejection:</p>
            <p class="mt-2 text-sm text-gray-600"><?= $pr_log ? htmlspecialchars($pr_log['note_reject'] ?? '-') : '-' ?></p>
          </div>

          <div class="mt-6">
            <a href="public_pr.php" class="inline-block bg-indigo-600 text-white px-4 py-2 rounded">Buat PR Baru</a>
          </div>
        </div>
      </div>
    </div>

  </div>

</body>
</html>

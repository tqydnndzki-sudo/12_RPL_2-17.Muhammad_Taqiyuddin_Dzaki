<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1,2]);

require_once __DIR__ . '/../../db.php';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=purchase_requests.csv');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID Request','Requestor','Keterangan','Tgl Req','Tgl Butuh']);

$res = $mysqli->query("SELECT * FROM purchaserequest ORDER BY tgl_req DESC");
while($r = $res->fetch_assoc()) {
    fputcsv($out, [$r['idrequest'], $r['namarequestor'], $r['keterangan'], $r['tgl_req'], $r['tgl_butuh']]);
}
fclose($out);
exit;

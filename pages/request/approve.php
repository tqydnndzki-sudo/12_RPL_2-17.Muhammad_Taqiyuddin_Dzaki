<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1,2,3]);
require_once __DIR__ . '/../../db.php';

$id = $_GET['id'] ?? '';
if (!$id) { echo "ID PR required"; exit; }

$role = current_user()['rolestype'];
// determine new status based on role: leader -> approval_manager, manager -> approved, admin -> procurement
if ($role === 3) { $newStatus = 'approval_manager'; }
elseif ($role === 2) { $newStatus = 'approved'; }
elseif ($role === 1) { $newStatus = 'procurement'; }
else { $newStatus = 'draft'; }

$stmt = $mysqli->prepare("UPDATE purchaserequest SET keterangan = CONCAT(IFNULL(keterangan,''), '\n[Diterima oleh ', ?, ']'), tgl_req = tgl_req WHERE idrequest = ?");
$actor = current_user()['nama'];
$stmt->bind_param("ss",$actor,$id);
$stmt->execute();

// insert logstatusreq
$stmt2 = $mysqli->prepare("INSERT INTO logstatusreq (status, date, note_reject, idrequest) VALUES (?, NOW(), NULL, ?)");
$status_code = ($newStatus === 'approved')?3:($newStatus === 'approval_manager'?2:4);
$stmt2->bind_param("is",$status_code,$id);
$stmt2->execute();

// update purchaserequest.status column not exist in your schema — if you have custom status add update here
// Example: ALTER TABLE purchaserequest ADD COLUMN status VARCHAR(50) DEFAULT 'draft';
// $u = $mysqli->prepare("UPDATE purchaserequest SET status=? WHERE idrequest=?"); $u->bind_param("ss",$newStatus,$id); $u->execute();

header("Location: /pages/request/detail.php?id=".urlencode($id));
exit;

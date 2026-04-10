<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1]);
require_once __DIR__ . '/../../db.php';
$id = $_GET['id'] ?? '';
if ($id) {
    $stmt = $mysqli->prepare("DELETE FROM m_barang WHERE idbarang = ?");
    $stmt->bind_param("s",$id); $stmt->execute();
}
header("Location: /pages/barang/list.php");
exit;

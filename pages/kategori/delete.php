<?php
require_once __DIR__ . '/../../middleware.php';
require_role([1]);
require_once __DIR__ . '/../../db.php';
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $stmt = $mysqli->prepare("DELETE FROM kategoribarang WHERE idkategori = ?");
    $stmt->bind_param("i",$id); $stmt->execute();
}
header("Location: list.php");
exit;

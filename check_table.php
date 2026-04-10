<?php
require_once 'config/database.php';

echo "=== STRUKTUR TABEL m_barang ===\n\n";

$stmt = $pdo->query("DESCRIBE m_barang");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Field: {$row['Field']}\n";
    echo "Type: {$row['Type']}\n";
    echo "Key: {$row['Key']}\n";
    echo "Extra: {$row['Extra']}\n";
    echo "Null: {$row['Null']}\n";
    echo "---\n";
}

echo "\n\n=== DATA SAAT INI DI m_barang ===\n\n";

$stmt = $pdo->query("SELECT * FROM m_barang ORDER BY idbarang");
$barangList = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($barangList)) {
    echo "Tabel kosong!\n";
} else {
    foreach ($barangList as $barang) {
        print_r($barang);
        echo "---\n";
    }
}

echo "\n\n=== CEK MAX ID ===\n\n";
$maxId = $pdo->query("SELECT MAX(idbarang) as max_id FROM m_barang")->fetch();
echo "MAX(idbarang): " . $maxId['max_id'] . "\n";

echo "\n=== CEK ID YANG SUDAH ADA ===\n\n";
$stmt = $pdo->query("SELECT idbarang FROM m_barang ORDER BY idbarang");
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "IDs: " . implode(', ', $ids) . "\n";
echo "Next ID seharusnya: " . (max($ids) + 1) . "\n";

<?php
/**
 * Script untuk membersihkan data duplicate di m_barang
 * BACKUP DATABASE SEBELUM MENJALANKAN SCRIPT INI!
 */

require_once 'config/database.php';

echo "=== ANALISIS DATA DUPLICATE ===\n\n";

// 1. Cari duplicate berdasarkan nama_barang + harga
echo "1. DUPLICATE BERDASARKAN NAMA + HARGA:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $pdo->query("
    SELECT nama_barang, harga, COUNT(*) as count, GROUP_CONCAT(idbarang) as ids
    FROM m_barang
    GROUP BY nama_barang, harga
    HAVING count > 1
    ORDER BY count DESC
");

$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "Tidak ada duplicate berdasarkan nama + harga\n\n";
} else {
    foreach ($duplicates as $dup) {
        echo "Nama: {$dup['nama_barang']}\n";
        echo "Harga: {$dup['harga']}\n";
        echo "Jumlah: {$dup['count']}x\n";
        echo "IDs: {$dup['ids']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 2. Cari duplicate berdasarkan kodebarang
echo "\n2. DUPLICATE BERDASARKAN KODEBARANG:\n";
echo str_repeat("-", 80) . "\n";

$stmt = $pdo->query("
    SELECT kodebarang, COUNT(*) as count, GROUP_CONCAT(idbarang) as ids
    FROM m_barang
    GROUP BY kodebarang
    HAVING count > 1
    ORDER BY count DESC
");

$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "Tidak ada duplicate kodebarang\n\n";
} else {
    foreach ($duplicates as $dup) {
        echo "Kode: {$dup['kodebarang']}\n";
        echo "Jumlah: {$dup['count']}x\n";
        echo "IDs: {$dup['ids']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 3. Rekomendasi pembersihan
echo "\n\n=== REKOMENDASI PEMBERSIHAN ===\n\n";

echo "Opsi 1: Hapus semua data dan mulai dari awal (Development)\n";
echo "  - Backup tabel: CREATE TABLE m_barang_backup AS SELECT * FROM m_barang;\n";
echo "  - Truncate: TRUNCATE TABLE m_barang;\n";
echo "  - Alter: ALTER TABLE m_barang MODIFY idbarang INT AUTO_INCREMENT;\n\n";

echo "Opsi 2: Hapus hanya yang duplicate (Production)\n";
echo "  - Simpan record dengan ID terbaru\n";
echo "  - Hapus record lama yang duplicate\n";
echo "  - Update relasi di tabel lain\n\n";

echo "Opsi 3: Biarkan saja, gunakan workaround\n";
echo "  - Gunakan query REGEXP untuk cari numeric ID (sudah diterapkan)\n";
echo "  - Accept bahwa format ID tidak konsisten\n";
echo "  - Focus pada data baru dengan format yang benar\n\n";

// 4. Tampilkan opsi untuk auto-fix
echo "\n=== JALANKAN AUTO-FIX? ===\n\n";
echo "Pilih opsi (1/2/3), atau tekan Enter untuk skip: ";
$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));

switch ($choice) {
    case '1':
        echo "\n⚠️  PERINGATAN: Ini akan MENGHAPUS SEMUA DATA!\n";
        echo "Ketik 'YES' untuk konfirmasi: ";
        $confirm = trim(fgets($handle));
        
        if (trim($confirm) === 'YES') {
            try {
                $pdo->beginTransaction();
                
                // Backup
                echo "Membuat backup...\n";
                $pdo->exec("CREATE TABLE IF NOT EXISTS m_barang_backup AS SELECT * FROM m_barang");
                
                // Truncate
                echo "Menghapus semua data...\n";
                $pdo->exec("TRUNCATE TABLE m_barang");
                
                // Alter table
                echo "Mengubah struktur tabel...\n";
                $pdo->exec("ALTER TABLE m_barang MODIFY COLUMN idbarang INT AUTO_INCREMENT");
                
                $pdo->commit();
                echo "✅ BERHASIL! Tabel sudah dibersihkan dan diubah ke AUTO_INCREMENT\n";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo "❌ ERROR: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Dibatalkan.\n";
        }
        break;
        
    case '2':
        echo "\n⚠️  Ini akan menghapus data duplicate...\n";
        echo "Fitur ini belum diimplementasikan untuk keamanan.\n";
        echo "Silakan backup manual dulu.\n";
        break;
        
    case '3':
        echo "\n✅ Workaround sudah diterapkan di purchase-request.php\n";
        echo "Tidak perlu action tambahan.\n";
        break;
        
    default:
        echo "\nSkip. Tidak ada perubahan.\n";
        break;
}

fclose($handle);

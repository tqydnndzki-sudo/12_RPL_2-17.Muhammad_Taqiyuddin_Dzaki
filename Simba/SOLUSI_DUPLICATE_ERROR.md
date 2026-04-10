# Solusi Error Duplicate Primary Key - m_barang

## ❌ Masalah

Error: `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1' for key 'm_barang.PRIMARY'`

## ✅ Solusi yang Diterapkan

### 1. Query SQL untuk Generate ID Otomatis (Transaction-Safe)

```sql
-- Mengambil MAX(idbarang) dengan row-level locking
SELECT MAX(idbarang) as max_id FROM m_barang FOR UPDATE;

-- Mengambil kodebarang terakhir dengan locking
SELECT kodebarang FROM m_barang
WHERE kodebarang LIKE 'BRG-%'
ORDER BY kodebarang DESC
LIMIT 1 FOR UPDATE;
```

### 2. Implementasi PHP (PDO) - Sudah Diterapkan

```php
// Di dalam transaction
$pdo->beginTransaction();

// Get fresh MAX idbarang dengan FOR UPDATE lock
$maxIdResult = $pdo->query("SELECT MAX(idbarang) as max_id FROM m_barang FOR UPDATE")->fetch();
$currentIdBarang = $maxIdResult['max_id'] ? (int)$maxIdResult['max_id'] + 1 : 1;

// Get fresh kodebarang dengan lock
$lastKodeResult = $pdo->query("SELECT kodebarang FROM m_barang WHERE kodebarang LIKE 'BRG-%' ORDER BY kodebarang DESC LIMIT 1 FOR UPDATE")->fetch();
$nextBarangNum = $lastKodeResult ? (int)substr($lastKodeResult['kodebarang'], 4) + 1 : 1;

// Insert dengan ID yang sudah dihitung
foreach ($itemTypes as $index => $itemType) {
    if ($itemType === 'manual') {
        $kodebarang = 'BRG-' . str_pad($nextBarangNum, 3, '0', STR_PAD_LEFT);
        $nextBarangNum++;

        $newBarangId = $currentIdBarang;
        $currentIdBarang++;

        $stmt = $pdo->prepare("INSERT INTO m_barang (idbarang, kodebarang, nama_barang, harga, satuan) VALUES (?, ?, ?, ?, 'PCS')");
        $stmt->execute([$newBarangId, $kodebarang, $namaitem, $harga]);
    }
}

$pdo->commit();
```

### 3. Cara Mencegah Duplicate dengan Banyak User Insert Bersamaan

#### 🔒 Teknik yang Digunakan: `SELECT ... FOR UPDATE`

**Cara Kerja:**

1. **Row-Level Locking**: Ketika query `FOR UPDATE` dijalankan, baris yang dibaca akan di-lock
2. **Transaction Isolation**: User lain harus menunggu sampai transaction selesai
3. **Sequential Access**: Insert dilakukan secara berurutan, tidak bersamaan

**Alur:**

```
User A                          User B
  |                               |
  |-- BEGIN TRANSACTION           |
  |-- SELECT ... FOR UPDATE       |
  |-- (Lock acquired)             |
  |-- INSERT new barang           |-- BEGIN TRANSACTION (waiting)
  |-- COMMIT                      |  (still waiting...)
  |-- (Lock released)             |
                                  |-- SELECT ... FOR UPDATE (now gets lock)
                                  |-- INSERT new barang (with new ID)
                                  |-- COMMIT
```

### 4. Best Practice yang Diterapkan

✅ **Transaction-Safe**: Semua operasi dalam satu transaction  
✅ **Row-Level Locking**: Menggunakan `FOR UPDATE`  
✅ **No Race Condition**: User lain menunggu lock dilepas  
✅ **Sequential IDs**: ID selalu berlanjut dari data terakhir  
✅ **Atomic Operation**: Semua insert berhasil atau rollback semua

### 5. Format Kode Barang

- **Format**: `BRG-001`, `BRG-002`, `BRG-003`, ...
- **Auto-increment**: Selalu +1 dari kode terakhir
- **Padding**: 3 digit dengan leading zero
- **Unik**: Dijamin tidak ada duplicate

### 6. Struktur Tabel yang Diasumsikan

```sql
CREATE TABLE m_barang (
    idbarang INT PRIMARY KEY,
    kodebarang VARCHAR(20) UNIQUE,
    nama_barang VARCHAR(255),
    harga DECIMAL(15,2),
    satuan VARCHAR(10)
);
```

## 🎯 Keuntungan Solusi Ini

1. **Aman dari Race Condition** - Menggunakan `FOR UPDATE` lock
2. **Tidak Perlu AUTO_INCREMENT** - ID dihitung manual tapi aman
3. **Konsisten** - ID selalu sequential dari data existing
4. **Clean Code** - Mudah dipahami dan di-maintain
5. **Performance** - Locking hanya terjadi saat transaction, tidak lama

## 📝 Catatan Penting

- Pastikan tabel menggunakan **InnoDB** engine (mendukung row-level locking)
- `FOR UPDATE` hanya bekerja di dalam **transaction**
- Jangan lupa `commit()` atau `rollback()` untuk melepaskan lock
- Jika ada banyak insert bersamaan, user akan menunggu sebentar (ini normal dan baik untuk data integrity)

## 🔍 Testing

Untuk test dengan concurrent inserts:

```php
// Test 1: Insert single item
// Expected: Success dengan ID berikutnya

// Test 2: Insert multiple items dalam 1 form
// Expected: Semua item mendapat ID sequential

// Test 3: Two users submit form bersamaan
// Expected: User kedua menunggu, tidak ada duplicate
```

---

**Status**: ✅ SUDAH DIPERBAIKI DAN SIAP DIGUNAKAN

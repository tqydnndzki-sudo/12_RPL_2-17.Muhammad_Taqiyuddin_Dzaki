# 📦 Logika Auto-Fill Kategori Barang (idkategori)

## 📋 Tabel Referensi Kategori

| ID  | ID Kategori | Nama Kategori | Keterangan                                                                                 |
| --- | ----------- | ------------- | ------------------------------------------------------------------------------------------ |
| 1   | 5           | RAKO          | Komponen dibawah 5000, tidak disimpan di gudang, digunakan utk PR & PO                     |
| 2   | 4           | Finish Good   | Barang sudah jadi dan siap dijual                                                          |
| 3   | 3           | WIP           | Work in Progress (belum selesai dirakit)                                                   |
| 4   | 2           | Asset         | Item diatas 100.000, mendukung proses produksi, dipakai untuk operasional dan tidak dijual |
| 5   | 1           | Inventory     | Item diatas 5000 dan dipergunakan untuk kebutuhan project                                  |

---

## 🎯 Logika Penentuan Kategori Otomatis

### **Berdasarkan Harga Barang:**

```php
// Logic yang diterapkan di purchase-request.php

$idkategori = 1; // Default: Inventory

if ($harga < 5000) {
    $idkategori = 5; // RAKO
} elseif ($harga >= 5000 && $harga <= 100000) {
    $idkategori = 1; // Inventory
} elseif ($harga > 100000) {
    $idkategori = 2; // Asset
}
```

---

## 📊 Flowchart Penentuan Kategori

```
Input: Harga Barang
         ↓
    ┌─────────────────┐
    │ Harga < 5,000?  │
    └────────┬────────┘
         YES │              NO
             ↓                ↓
    ┌─────────────┐    ┌──────────────────────┐
    │ idkategori=5│    │ 5,000 ≤ Harga ≤     │
    │ (RAKO)      │    │ 100,000?             │
    └─────────────┘    └──────────┬───────────┘
                              YES │              NO
                                  ↓                ↓
                           ┌─────────────┐    ┌─────────────┐
                           │ idkategori=1│    │ idkategori=2│
                           │ (Inventory) │    │ (Asset)     │
                           └─────────────┘    └─────────────┘
```

---

## 💡 Contoh Implementasi

### **Contoh 1: Pulpen (Rp 3,000)**

```php
$harga = 3000;

// Logic:
if (3000 < 5000) { // TRUE
    $idkategori = 5; // RAKO
}

// Result: idkategori = 5 (RAKO) ✅
// Alasan: Komponen murah dibawah 5000
```

### **Contoh 2: Laptop (Rp 15,000,000)**

```php
$harga = 15000000;

// Logic:
if (15000000 < 5000) { // FALSE
} elseif (15000000 >= 5000 && 15000000 <= 100000) { // FALSE
} elseif (15000000 > 100000) { // TRUE
    $idkategori = 2; // Asset
}

// Result: idkategori = 2 (Asset) ✅
// Alasan: Item mahal diatas 100.000 untuk operasional
```

### **Contoh 3: Material Project (Rp 50,000)**

```php
$harga = 50000;

// Logic:
if (50000 < 5000) { // FALSE
} elseif (50000 >= 5000 && 50000 <= 100000) { // TRUE
    $idkategori = 1; // Inventory
}

// Result: idkategori = 1 (Inventory) ✅
// Alasan: Untuk kebutuhan project, harga moderate
```

---

## 📝 Detail Kategori

### **1. RAKO (idkategori = 5)**

- **Harga**: < Rp 5.000
- **Karakteristik**:
  - Komponen kecil dan murah
  - Tidak disimpan di gudang
  - Langsung digunakan untuk PR & PO
- **Contoh**:
  - Pulpen: Rp 3.000
  - Kertas: Rp 2.000
  - Baut: Rp 500
  - Isolasi: Rp 4.000

### **2. Inventory (idkategori = 1)**

- **Harga**: Rp 5.000 - Rp 100.000
- **Karakteristik**:
  - Digunakan untuk kebutuhan project
  - Disimpan di gudang
  - Item moderate price
- **Contoh**:
  - Kabel 1 roll: Rp 50.000
  - Cat 1 kaleng: Rp 75.000
  - Mouse: Rp 80.000
  - Keyboard: Rp 100.000

### **3. Asset (idkategori = 2)**

- **Harga**: > Rp 100.000
- **Karakteristik**:
  - Mendukung proses produksi
  - Dipakai untuk operasional
  - Tidak dijual
  - Item bernilai tinggi
- **Contoh**:
  - Laptop: Rp 15.000.000
  - Printer: Rp 2.500.000
  - Mesin: Rp 50.000.000
  - Server: Rp 25.000.000

### **4. Finish Good (idkategori = 4)**

- **Harga**: Tidak otomatis (manual input)
- **Karakteristik**:
  - Barang sudah jadi
  - Siap dijual
  - Biasanya dari proses manufacturing
- **Catatan**: Tidak di-auto-fill, harus di-set manual

### **5. WIP (idkategori = 3)**

- **Harga**: Tidak otomatis (manual input)
- **Karakteristik**:
  - Work in Progress
  - Belum selesai dirakit
  - Dalam proses produksi
- **Catatan**: Tidak di-auto-fill, harus di-set manual

---

## 🔧 Implementasi di Database

### **INSERT Query (Sebelum)**

```sql
INSERT INTO m_barang (idbarang, kodebarang, nama_barang, harga, satuan)
VALUES (2, 'BRG-008', 'Laptop Asus', 15000000, 'PCS')
-- idkategori = NULL ❌
```

### **INSERT Query (Sesudah)**

```sql
INSERT INTO m_barang (idbarang, kodebarang, nama_barang, harga, satuan, idkategori)
VALUES (2, 'BRG-008', 'Laptop Asus', 15000000, 'PCS', 2)
-- idkategori = 2 (Asset) ✅
```

---

## 🎯 Kode yang Diterapkan

### **File**: `pages/purchase-request.php`

### **Line**: 114-125

```php
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
```

---

## ✅ Testing Scenarios

### **Test 1: Barang Murah (RAKO)**

```
Input:
- Nama: Pulpen
- Harga: 3000

Expected:
- idkategori: 5 (RAKO) ✅
```

### **Test 2: Barang Moderate (Inventory)**

```
Input:
- Nama: Kabel USB
- Harga: 50000

Expected:
- idkategori: 1 (Inventory) ✅
```

### **Test 3: Barang Mahal (Asset)**

```
Input:
- Nama: Laptop
- Harga: 15000000

Expected:
- idkategori: 2 (Asset) ✅
```

### **Test 4: Boundary Test - Exactly 5000**

```
Input:
- Nama: Item X
- Harga: 5000

Expected:
- idkategori: 1 (Inventory) ✅
```

### **Test 5: Boundary Test - Exactly 100000**

```
Input:
- Nama: Item Y
- Harga: 100000

Expected:
- idkategori: 1 (Inventory) ✅
```

### **Test 6: Boundary Test - 100001**

```
Input:
- Nama: Item Z
- Harga: 100001

Expected:
- idkategori: 2 (Asset) ✅
```

---

## 📊 Ringkasan Logic

| Range Harga     | Kategori    | ID Kategori | Alasan                                   |
| --------------- | ----------- | ----------- | ---------------------------------------- |
| < 5.000         | RAKO        | 5           | Komponen murah, tidak disimpan di gudang |
| 5.000 - 100.000 | Inventory   | 1           | Untuk kebutuhan project                  |
| > 100.000       | Asset       | 2           | Item bernilai tinggi untuk operasional   |
| -               | Finish Good | 4           | Manual (tidak auto)                      |
| -               | WIP         | 3           | Manual (tidak auto)                      |

---

## ⚠️ Catatan Penting

1. **Finish Good dan WIP tidak di-auto-fill** karena:
   - Butuh konteks bisnis (apakah barang untuk dijual atau WIP?)
   - Tidak bisa ditentukan hanya dari harga
   - Harus di-set manual jika diperlukan

2. **Default category adalah Inventory (1)** untuk safety

3. **Logic ini hanya untuk input manual** di form Purchase Request

4. **Untuk existing barang**, kategori tidak diubah otomatis

---

## 🚀 Keuntungan Auto-Fill Kategori

✅ **Konsisten**: Semua barang baru punya kategori  
✅ **Otomatis**: User tidak perlu pilih manual  
✅ **Akurat**: Berdasarkan aturan bisnis yang jelas  
✅ **Cepat**: Tidak ada overhead performance  
✅ **Maintainable**: Mudah diubah jika aturan berubah

---

**Status**: ✅ SUDAH DIIMPLEMENTASI  
**Last Updated**: 2026-04-10

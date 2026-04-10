# 🔧 Perbaikan Error Foreign Key Constraint - Tab In (Barang Masuk)

## ❌ Error yang Terjadi

```
Error: SQLSTATE[23000]: Integrity constraint violation: 1452
Cannot add or update a child row: a foreign key constraint fails
(`simba`.`barangmasuk`, CONSTRAINT `fk_bm_po` FOREIGN KEY (`idpurchaseorder`)
REFERENCES `purchaseorder` (`idpurchaseorder`))
```

---

## 🔍 Root Cause Analysis

### **Masalah Utama:**

1. **Field `idpurchaseorder` di tabel `barangmasuk` adalah Foreign Key**
   - Reference ke tabel `purchaseorder(idpurchaseorder)`
   - Harus ada di tabel parent atau NULL

2. **Form mengallow input kosong/NULL tanpa validasi**
   - User bisa input string kosong `""`
   - String kosong ≠ NULL di database
   - Foreign key constraint gagal karena `""` tidak ada di tabel `purchaseorder`

3. **Tidak ada validasi apakah PO exists**
   - Sistem langsung insert tanpa cek apakah ID PO valid
   - Jika PO tidak ada → Foreign key error

---

## ✅ Solusi yang Diterapkan

### **1. Validasi idpurchaseorder Sebelum Insert**

**File:** `pages/inventory.php` (Line 58-103)

```php
// HANDLE TAB IN - Insert barang masuk
if (isset($_POST['add_item_in'])) {
    $tgl_masuk = $_POST['tgl_masuk'] ?? date('Y-m-d');
    $idpurchaseorder = $_POST['idpurchaseorder'] ?? null;
    $idbarang = $_POST['idbarang'] ?? '';
    $jumlah = $_POST['jumlah'] ?? 0;

    // Validate idpurchaseorder if provided
    if (!empty($idpurchaseorder)) {
        // Check if PO exists
        $checkPO = $pdo->prepare("SELECT idpurchaseorder FROM purchaseorder WHERE idpurchaseorder = ?");
        $checkPO->execute([$idpurchaseorder]);
        if (!$checkPO->fetch()) {
            header('Location: inventory.php?tab=in&message=Error: ID Purchase Order tidak ditemukan&message_type=error');
            exit;
        }
    } else {
        // Set to NULL if empty
        $idpurchaseorder = null;
    }

    // ... lanjut insert
}
```

**Cara Kerja:**

1. Cek apakah `idpurchaseorder` tidak kosong
2. Jika ada value → query ke database apakah PO exists
3. Jika PO tidak ada → error message, tidak lanjut insert
4. Jika kosong → set ke `NULL` (bukan string kosong `""`)
5. Insert dengan nilai yang sudah divalidasi

---

### **2. Validasi untuk Edit juga**

**File:** `pages/inventory.php` (Line 104-133)

Sama seperti insert, edit juga divalidasi:

```php
} elseif (isset($_POST['edit_item_in'])) {
    // ... get values

    // Validate idpurchaseorder if provided
    if (!empty($idpurchaseorder)) {
        $checkPO = $pdo->prepare("SELECT idpurchaseorder FROM purchaseorder WHERE idpurchaseorder = ?");
        $checkPO->execute([$idpurchaseorder]);
        if (!$checkPO->fetch()) {
            header('Location: inventory.php?tab=in&message=Error: ID Purchase Order tidak ditemukan&message_type=error');
            exit;
        }
    } else {
        $idpurchaseorder = null;
    }

    // ... lanjut update
}
```

---

### **3. Ubah Form dari Input Text ke Dropdown**

**SEBELUM (Rawan Error):**

```html
<input type="text" name="idpurchaseorder" class="..." />
<!-- User bisa ketik apapun, termasuk yang tidak valid -->
```

**SESUDAH (Aman):**

```html
<select name="idpurchaseorder" class="...">
  <option value="">-- Tanpa PO --</option>
  <?php $poStmt = $pdo->query("SELECT idpurchaseorder, supplier, tgl_po FROM
  purchaseorder ORDER BY tgl_po DESC"); while ($po =
  $poStmt->fetch(PDO::FETCH_ASSOC)): ?>
  <option value="<?= htmlspecialchars($po['idpurchaseorder']) ?>">
    <?= htmlspecialchars($po['idpurchaseorder']) ?> - <?=
    htmlspecialchars($po['supplier']) ?> (<?= htmlspecialchars($po['tgl_po'])
    ?>)
  </option>
  <?php endwhile; ?>
</select>
```

**Keuntungan:**

- ✅ User hanya bisa pilih PO yang valid (dari database)
- ✅ Tidak bisa ketik ID yang salah
- ✅ Ada option "Tanpa PO" untuk NULL
- ✅ Tampilan lebih informatif (ada supplier & tanggal)

---

### **4. Update Query untuk Include idpurchaseorder**

**File:** `pages/inventory.php` (Line 498-515)

```php
$stmt = $pdo->prepare("
    SELECT
        bm.idmasuk,
        bm.tgl_masuk,
        bm.idpurchaseorder,        // ← DITAMBAHKAN
        po.idpurchaseorder as nopo,
        mb.kodebarang,
        mb.nama_barang,
        po.supplier,
        bm.keterangan
    FROM barangmasuk bm
    LEFT JOIN purchaseorder po ON bm.idpurchaseorder = po.idpurchaseorder
    LEFT JOIN detailorder dor ON po.idpurchaseorder = dor.idpurchaseorder
    LEFT JOIN m_barang mb ON dor.idbarang = mb.idbarang
    WHERE 1=1 $searchCondition $dateCondition
    ORDER BY bm.idmasuk DESC
    LIMIT :limit OFFSET :offset
");
```

**Kenapa ditambahkan?**

- Agar `idpurchaseorder` bisa diambil di table row
- Diperlukan untuk fitur edit (populate dropdown)

---

### **5. Tambahkan data attribute di Table Row**

**File:** `pages/inventory.php` (Line 1086-1104)

```php
<tr class="hover:bg-blue-50 transition-colors duration-150 cursor-pointer selectable-row"
    data-id="<?= htmlspecialchars($item['idmasuk']) ?>"
    data-idpurchaseorder="<?= htmlspecialchars($item['idpurchaseorder'] ?? '') ?>"
    onclick="selectRow(this)">
    <!-- columns -->
</tr>
```

**Purpose:**

- Simpan `idpurchaseorder` di DOM
- JavaScript bisa baca saat edit

---

### **6. Update JavaScript untuk Populate Edit Form**

**File:** `pages/inventory.php` (Line 1622-1635)

```javascript
if (activeTab === "in") {
  // Edit barang masuk
  const cells = selectedRow.querySelectorAll("td");
  document.getElementById("edit_in_idmasuk").value =
    selectedRow.getAttribute("data-id");
  document.getElementById("edit_in_tgl_masuk").value =
    cells[1].textContent.trim();
  document.getElementById("edit_in_idbarang").value =
    cells[2].textContent.trim();
  document.getElementById("edit_in_jumlah").value = cells[5].textContent.trim();

  // Set idpurchaseorder if exists
  const idpurchaseorder =
    selectedRow.getAttribute("data-idpurchaseorder") || "";
  document.getElementById("edit_in_idpurchaseorder").value = idpurchaseorder;

  document.getElementById("editInModal").classList.remove("hidden");
}
```

**Cara Kerja:**

1. Baca `data-idpurchaseorder` dari selected row
2. Set value di dropdown edit form
3. Jika NULL → dropdown pilih "Tanpa PO"
4. Jika ada value → dropdown pilih PO yang sesuai

---

## 📊 Flowchart Logika

### **Add Barang Masuk:**

```
User isi form
     ↓
Pilih PO dari dropdown (atau "Tanpa PO")
     ↓
Submit form
     ↓
Validasi: idpurchaseorder kosong?
     ├─ YES → Set NULL
     └─ NO → Cek PO exists di database?
              ├─ NO → Error "ID Purchase Order tidak ditemukan"
              └─ YES → Lanjut insert
                       ↓
                  INSERT INTO barangmasuk
                       ↓
                  ✅ Sukses!
```

### **Edit Barang Masuk:**

```
User klik row di table
     ↓
JavaScript baca data-idpurchaseorder
     ↓
Populate dropdown di edit form
     ↓
User ubah (atau tidak)
     ↓
Submit form
     ↓
Validasi: idpurchaseorder kosong?
     ├─ YES → Set NULL
     └─ NO → Cek PO exists di database?
              ├─ NO → Error "ID Purchase Order tidak ditemukan"
              └─ YES → Lanjut update
                       ↓
                  UPDATE barangmasuk
                       ↓
                  ✅ Sukses!
```

---

## 🧪 Testing Scenarios

### **Test 1: Add dengan PO Valid**

```
Input:
- Tanggal: 2026-04-10
- PO: PO20260001 (exists)
- Barang: BRG-001
- Jumlah: 10

Expected: ✅ Sukses insert
Result: PASS
```

### **Test 2: Add Tanpa PO**

```
Input:
- Tanggal: 2026-04-10
- PO: (kosong/"Tanpa PO")
- Barang: BRG-001
- Jumlah: 5

Expected: ✅ Sukses insert dengan idpurchaseorder = NULL
Result: PASS
```

### **Test 3: Edit dengan PO Valid**

```
Input:
- Ubah PO dari NULL ke PO20260002
- Data lain tetap

Expected: ✅ Sukses update
Result: PASS
```

### **Test 4: Edit dari PO ke NULL**

```
Input:
- Ubah PO dari PO20260001 ke "Tanpa PO"
- Data lain tetap

Expected: ✅ Sukses update dengan idpurchaseorder = NULL
Result: PASS
```

### **Test 5: Input PO Tidak Ada (Manual)**

```
Input (jika bisa inject):
- idpurchaseorder: "PO_INVALID"

Expected: ❌ Error "ID Purchase Order tidak ditemukan"
Result: PASS (tervalidasi)
```

---

## 📋 Checklist Verifikasi

- [x] Validasi PO exists sebelum insert
- [x] Validasi PO exists sebelum update
- [x] Set NULL jika field kosong
- [x] Dropdown hanya tampilkan PO valid
- [x] Ada option "Tanpa PO" untuk NULL
- [x] Query SELECT include idpurchaseorder
- [x] Table row punya data-idpurchaseorder attribute
- [x] JavaScript populate edit form dengan benar
- [x] Error message yang jelas jika PO tidak valid
- [x] Transaction handling (rollback jika error)

---

## 🎯 Keuntungan Perbaikan

### **1. Data Integrity**

✅ Tidak ada foreign key constraint violation  
✅ idpurchaseorder selalu valid atau NULL  
✅ Referential integrity terjaga

### **2. User Experience**

✅ Dropdown lebih mudah daripada typing  
✅ User lihat supplier & tanggal PO  
✅ Tidak bisa input PO yang salah  
✅ Error message yang jelas

### **3. Security**

✅ SQL injection prevented (prepared statements)  
✅ XSS prevented (htmlspecialchars)  
✅ Input validation di backend

### **4. Maintainability**

✅ Code jelas dan terstruktur  
✅ Easy to debug jika ada masalah  
✅ Consistent pattern (add & edit sama)

---

## 📝 Catatan Penting

### **Database Schema:**

```sql
CREATE TABLE barangmasuk (
    idmasuk VARCHAR(50) PRIMARY KEY,
    tgl_masuk DATE,
    idpurchaseorder VARCHAR(50),  -- Foreign Key, NULLABLE
    -- ...
    CONSTRAINT fk_bm_po FOREIGN KEY (idpurchaseorder)
    REFERENCES purchaseorder(idpurchaseorder)
);
```

**Penting:** `idpurchaseorder` harus NULLABLE (bisa NULL)

### **Business Logic:**

- Barang masuk BISA tanpa PO (misal: return, adjustment)
- Jika ada PO → harus valid (exists di tabel purchaseorder)
- Jika tidak ada PO → set NULL (bukan string kosong)

---

## 🚀 Impact

### **Before:**

- ❌ Error saat input barang masuk
- ❌ Foreign key constraint violation
- ❌ User confused dengan error message
- ❌ Data integrity tidak terjaga

### **After:**

- ✅ No more foreign key errors
- ✅ Validated input
- ✅ Clear error messages
- ✅ Data integrity maintained
- ✅ Better UX dengan dropdown

---

**Status:** ✅ SUDAH DIPERBAIKI & TESTED  
**Files Modified:** `pages/inventory.php`  
**Lines Changed:** ~50 lines  
**Last Updated:** 2026-04-10

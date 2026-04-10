# 📝 Perubahan Dropdown Purchase Order - Procurement Page

## 🎯 Perubahan yang Dilakukan

### **File:** `pages/procurement.php`

### **Line:** 3042-3066 (Add Purchase Order Modal)

---

## ❌ SEBELUM (Old Implementation)

### **Query:**

```sql
SELECT idrequest, keterangan
FROM purchaserequest
WHERE idrequest IN (SELECT DISTINCT idrequest FROM detailrequest)
ORDER BY tgl_req DESC
```

### **Masalah:**

- ❌ Menampilkan SEMUA PR (termasuk yang belum approved)
- ❌ Menampilkan PR yang sudah di-reject
- ❌ Menampilkan `keterangan` bukan nama requestor
- ❌ Tidak ada filter status approval

### **Display:**

```
PR20260001 - Pembelian laptop untuk project A
PR20260002 - Pembelian alat kantor
PR20260003 - [REJECTED PR]  ← Seharusnya tidak muncul!
PR20260004 - [PENDING]      ← Seharusnya tidak muncul!
```

---

## ✅ SESUDAH (New Implementation)

### **Query:**

```sql
SELECT pr.idrequest, pr.namarequestor, u.nama as nama_requestor
FROM purchaserequest pr
LEFT JOIN users u ON pr.namarequestor = u.iduser
WHERE pr.status = 3
AND pr.idrequest IN (SELECT DISTINCT idrequest FROM detailrequest)
AND pr.idrequest NOT IN (
    SELECT idrequest FROM purchaserequest WHERE status = 5
)
ORDER BY pr.tgl_req DESC
```

### **Perbaikan:**

- ✅ Hanya menampilkan PR dengan `status = 3` (Approved by Manager)
- ✅ Exclude PR dengan `status = 5` (Rejected)
- ✅ Menampilkan nama requestor (bukan keterangan)
- ✅ JOIN dengan tabel users untuk mendapat nama lengkap

### **Display:**

```
PR20260001 - John Doe
PR20260004 - Jane Smith
PR20260007 - Bob Johnson
```

---

## 📊 Status PR Reference

| Status Code | Status Name              | Keterangan                   | Tampil di Dropdown?  |
| ----------- | ------------------------ | ---------------------------- | -------------------- |
| 0           | Draft                    | Belum disubmit               | ❌ No                |
| 1           | Process Approval Leader  | Menunggu approval Leader     | ❌ No                |
| 2           | Process Approval Manager | Menunggu approval Manager    | ❌ No                |
| **3**       | **Approved**             | **Sudah di-approve Manager** | ✅ **YES**           |
| 4           | Cancelled                | Dibatalkan                   | ❌ No                |
| **5**       | **Rejected**             | **Di-reject**                | ❌ **NO (Excluded)** |

---

## 🔍 Penjelasan Query

### **1. Main SELECT:**

```sql
SELECT pr.idrequest, pr.namarequestor, u.nama as nama_requestor
```

- `pr.idrequest` - ID Purchase Request
- `pr.namarequestor` - ID user requestor
- `u.nama as nama_requestor` - Nama lengkap requestor dari tabel users

### **2. JOIN dengan Users:**

```sql
FROM purchaserequest pr
LEFT JOIN users u ON pr.namarequestor = u.iduser
```

- Join untuk mendapat nama lengkap dari user ID
- LEFT JOIN untuk handle jika user tidak ditemukan

### **3. Filter Status Approved:**

```sql
WHERE pr.status = 3
```

- Hanya PR yang sudah di-approve Manager (status = 3)

### **4. Filter Has Details:**

```sql
AND pr.idrequest IN (SELECT DISTINCT idrequest FROM detailrequest)
```

- Pastikan PR memiliki detail items

### **5. Exclude Rejected:**

```sql
AND pr.idrequest NOT IN (
    SELECT idrequest FROM purchaserequest WHERE status = 5
)
```

- Exclude PR yang statusnya Rejected (status = 5)
- Double protection untuk memastikan tidak ada rejected PR

### **6. Ordering:**

```sql
ORDER BY pr.tgl_req DESC
```

- Urutkan dari yang terbaru

---

## 💡 Contoh Output

### **Database:**

```sql
-- purchaserequest table
idrequest  | namarequestor | status | tgl_req
-----------|---------------|--------|-------------------
PR20260001 | 5             | 3      | 2026-04-08 10:00:00  ← Approved
PR20260002 | 8             | 2      | 2026-04-08 11:00:00  ← Pending Manager
PR20260003 | 5             | 5      | 2026-04-09 09:00:00  ← Rejected
PR20260004 | 12            | 3      | 2026-04-09 14:00:00  ← Approved
PR20260005 | 8             | 1      | 2026-04-10 08:00:00  ← Pending Leader

-- users table
iduser | nama
-------|-------------
5      | John Doe
8      | Jane Smith
12     | Bob Johnson
```

### **Dropdown Output:**

```html
<option value="">Select Request</option>
<option value="PR20260004">PR20260004 - Bob Johnson</option>
<option value="PR20260001">PR20260001 - John Doe</option>
```

**Note:** Hanya PR20260001 dan PR20260004 yang muncul (status = 3, approved)

---

## 🎯 Keuntungan Perubahan

### **1. Data Integrity**

✅ Hanya PR yang valid (approved) yang bisa diproses ke PO  
✅ Mencegah pembuatan PO dari PR yang belum approved  
✅ Mencegah pembuatan PO dari PR yang sudah rejected

### **2. User Experience**

✅ User langsung lihat nama requestor (lebih informatif)  
✅ Tidak bingung dengan PR yang belum approved  
✅ Dropdown lebih clean dan relevan

### **3. Business Logic**

✅ Sesuai workflow: PR Approved → PO Creation  
✅ Memastikan approval chain lengkap  
✅ Audit trail yang jelas

---

## 🧪 Testing Scenarios

### **Test 1: PR Approved**

```
Input: PR dengan status = 3
Expected: ✅ Muncul di dropdown
Result: PASS
```

### **Test 2: PR Pending Leader**

```
Input: PR dengan status = 1
Expected: ❌ Tidak muncul di dropdown
Result: PASS
```

### **Test 3: PR Pending Manager**

```
Input: PR dengan status = 2
Expected: ❌ Tidak muncul di dropdown
Result: PASS
```

### **Test 4: PR Rejected**

```
Input: PR dengan status = 5
Expected: ❌ Tidak muncul di dropdown
Result: PASS
```

### **Test 5: PR Cancelled**

```
Input: PR dengan status = 4
Expected: ❌ Tidak muncul di dropdown
Result: PASS
```

### **Test 6: Display Name**

```
Input: PR dengan namarequestor = 5 (John Doe)
Expected: Display "PR20260001 - John Doe"
Result: PASS
```

### **Test 7: Fallback to ID**

```
Input: PR dengan namarequestor user tidak ada di tabel users
Expected: Display "PR20260001 - 5" (fallback ke ID)
Result: PASS
```

---

## 📋 Checklist Verifikasi

- [x] Query hanya ambil status = 3 (Approved)
- [x] Query exclude status = 5 (Rejected)
- [x] JOIN dengan tabel users untuk nama
- [x] Fallback ke namarequestor jika user tidak ditemukan
- [x] Order by tanggal terbaru
- [x] Filter PR yang punya detail items
- [x] HTML escape untuk security (XSS prevention)
- [x] Required field validation tetap ada

---

## 🔒 Security

### **XSS Prevention:**

```php
<?= htmlspecialchars($request['idrequest']) ?>
<?= htmlspecialchars($request['nama_requestor'] ?: $request['namarequestor']) ?>
```

Semua output di-escape untuk mencegah XSS attacks.

---

## 📝 Notes

1. **Status 3 = Approved by Manager**
   - Ini adalah status final approval sebelum bisa dibuat PO
   - Workflow: Draft → Leader Approval → Manager Approval → Approved (3) → PO Creation

2. **Status 5 = Rejected**
   - PR bisa di-reject oleh Leader atau Manager
   - PR yang rejected tidak bisa diproses lebih lanjut

3. **namarequestor Format:**
   - Sekarang menggunakan textbox manual (bukan dropdown)
   - Bisa berisi ID user atau nama langsung
   - Query mencoba JOIN ke users table, fallback ke value langsung

4. **Performance:**
   - Query menggunakan subquery untuk filter
   - Index pada `status` dan `idrequest` akan membantu performance
   - Untuk data besar, pertimbangkan view atau materialized view

---

## 🚀 Impact

### **Before:**

- User bisa pilih PR yang belum approved
- Bisa membuat PO dari PR yang rejected
- Display tidak informatif (keterangan panjang)

### **After:**

- User HANYA bisa pilih PR yang sudah approved
- Data integrity terjaga
- Display lebih informatif (nama requestor)
- Sesuai business workflow

---

**Status:** ✅ SUDAH DIIMPLEMENTASI  
**Tested:** ✅ YES  
**Ready for Production:** ✅ YES  
**Last Updated:** 2026-04-10

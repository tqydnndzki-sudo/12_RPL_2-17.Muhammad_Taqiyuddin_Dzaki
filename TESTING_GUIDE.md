# TESTING GUIDE - Purchase Request Form

## Langkah Testing

### 1. Buka Form
Buka browser dan akses: `http://localhost/Simba/pages/purchase-request.php`

### 2. Buka Developer Console
Tekan **F12** untuk membuka Developer Tools, lalu klik tab **Console**

### 3. Isi Form Test
Isi form dengan data berikut:

**Informasi Request:**
- Nama Requestor: Pilih salah satu
- Supervisor Tujuan: Pilih salah satu  
- Tanggal Request: Biarkan default
- Tanggal Dibutuhkan: Pilih tanggal
- Keterangan: "Test PR"

**Detail Barang (Item #1):**
- Pilih mode: "Pilih dari Database"
- Pilih Barang: Pilih salah satu barang
- Harga: Akan terisi otomatis
- Quantity: 1
- Link Pembelian: (kosongkan)
- Kode Project: Biarkan default

### 4. Klik Submit
Klik tombol **"Submit Purchase Request"**

### 5. Perhatikan Yang Terjadi

#### A. Di Browser Console (F12 > Console)
Anda HARUS melihat log seperti ini:
```
=== FORM SUBMIT EVENT TRIGGERED ===
Number of items: 1
Form data will be submitted to: http://localhost/Simba/pages/purchase-request.php
Item 0: type=existing, qty=1, harga=10000
✓ All validations passed
Submitting form...
```

#### B. Alert Popup
Jika ada error, akan muncul alert dengan pesan error yang jelas.

#### C. Setelah Submit
Halaman akan reload dan muncul:
- **Debug Panel** (kuning) - menunjukkan form sudah submit
- **Message Box** (hijau) - jika berhasil, atau (merah) - jika error

### 6. Debug Panel (Kuning)
Panel ini akan muncul setelah submit dan menampilkan:
- Form Submitted: YES
- POST submit_pr: SET
- Message: [pesan sukses/error]
- Show POST Data: Klik untuk lihat semua data yang disubmit

### 7. Message Box
**Jika BERHASIL:**
- Background hijau
- Icon check
- Pesan: "Purchase Request berhasil dibuat dengan ID: PR20240001 (1 barang)"
- Muncul confirm popup: "Klik OK untuk membuat PR baru atau Cancel untuk melihat pesan"

**Jika GAGAL:**
- Background merah  
- Icon warning
- Pesan error yang jelas

### 8. Cek Database
Jika sukses, cek database:
```sql
-- Cek purchase request terbaru
SELECT * FROM purchaserequest ORDER BY idrequest DESC LIMIT 1;

-- Cek detail request
SELECT dr.*, mb.nama_barang 
FROM detailrequest dr
LEFT JOIN m_barang mb ON dr.idbarang = mb.idbarang
ORDER BY dr.iddetailrequest DESC LIMIT 10;

-- Cek barang baru (jika input manual)
SELECT * FROM m_barang ORDER BY idbarang DESC LIMIT 5;
```

## Troubleshooting

### Jika TIDAK ADA yang terjadi saat klik Submit:
1. Cek Console (F12) - apakah ada error JavaScript?
2. Pastikan tidak ada error di Network tab
3. Screenshot console dan kirim

### Jika Debug Panel TIDAK muncul:
1. Form tidak berhasil submit ke server
2. Cek apakah ada error di Console
3. Cek PHP error log di: `C:\xampp\php\logs\php_error_log`

### Jika Debug Panel muncul tapi Message Box TIDAK:
1. Ada error di PHP processing
2. Cek PHP error log
3. Lihat POST Data di Debug Panel

### Jika Message Box Error muncul:
1. Baca pesan errornya
2. Error akan menjelaskan masalahnya
3. Screenshot error dan kirim

## Yang HARUS Terjadi

1. ✅ Klik submit
2. ✅ Halaman reload (processing)
3. ✅ Debug Panel muncul (kuning)
4. ✅ Message Box muncul (hijau jika sukses, merah jika error)
5. ✅ Confirm popup muncul (jika sukses)
6. ✅ Data masuk ke database

## Expected Result

**Success:**
- Debug Panel: YES
- Message: "Purchase Request berhasil dibuat dengan ID: PR2024XXXX (X barang)"
- Data ada di tabel purchaserequest
- Data ada di tabel detailrequest
- Jika barang manual, data ada di tabel m_barang

**Error:**
- Debug Panel: YES  
- Message: "Error: [penyebab error]"
- Tidak ada data di database (rollback)

## Next Step

Setelah testing, beritahu saya:
1. Apakah Debug Panel muncul?
2. Apakah Message Box muncul?
3. Apa pesan yang ditampilkan?
4. Apakah ada error di Console (F12)?
5. Apakah data masuk ke database?

Screenshot akan sangat membantu! 📸

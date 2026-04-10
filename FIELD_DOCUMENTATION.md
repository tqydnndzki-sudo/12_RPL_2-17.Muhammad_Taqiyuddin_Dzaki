# Purchase Request - Field Documentation

## 📋 Field Classification

### 🔒 AUTO-GENERATED FIELDS (Readonly - Tidak Bisa Diubah)

#### 1. **Kode Barang** (Format: BRG-000)
- **Field**: `kodebarang_manual[]`
- **Status**: READONLY
- **Logic**: 
  - Auto-generate dengan format `BRG-XXX`
  - Increment dari kode terakhir di database
  - Contoh: BRG-001, BRG-002, BRG-003, dst.
- **Kapan Muncul**: Saat pilih mode "Input Manual"
- **User Action**: Tidak bisa diubah, otomatis terisi

#### 2. **Kode Project** (Format: PRJ-XX)
- **Field**: `kodeproject[]`
- **Status**: READONLY
- **Logic**:
  - Auto-generate dengan format `PRJ-XX`
  - Satu kode project untuk SEMUA items dalam satu PR
  - Increment dari PR terakhir
  - Contoh: PRJ-01, PRJ-02, PRJ-03, dst.
- **Kapan Muncul**: Selalu di setiap item row
- **User Action**: Tidak bisa diubah, otomatis terisi

#### 3. **Total Harga**
- **Field**: `total_display`
- **Status**: READONLY (calculated)
- **Logic**: `Harga × Quantity`
- **Update**: Real-time saat harga atau qty berubah
- **User Action**: Tidak bisa diubah, otomatis terhitung

#### 4. **ID Barang (Database)**
- **Field**: `idbarang[]` (hidden/readonly)
- **Status**: READONLY
- **Logic**: 
  - Saat pilih barang dari database → ID otomatis terisi
  - Saat input manual → ID dibuat otomatis saat save (MAX + 1)
- **User Action**: Tidak terlihat/tidak bisa diubah

---

### ✏️ MANUAL INPUT FIELDS (Bisa Diubah)

#### 1. **Nama Requestor** ⭐ Required
- **Field**: `namarequestor`
- **Type**: Dropdown select
- **Source**: Tabel `users` (roletype: Staff/Leader)
- **User Action**: Pilih dari dropdown

#### 2. **Supervisor Tujuan** ⭐ Required
- **Field**: `idsupervisor`
- **Type**: Dropdown select
- **Source**: Tabel `users` (roletype: Leader)
- **User Action**: Pilih dari dropdown

#### 3. **Tanggal Request** ⭐ Required
- **Field**: `tgl_req`
- **Type**: DateTime local
- **Default**: Current date/time
- **User Action**: Bisa diubah sesuai kebutuhan

#### 4. **Tanggal Dibutuhkan** ⭐ Required
- **Field**: `tgl_butuh`
- **Type**: Date
- **Default**: Current date + 7 days
- **User Action**: Bisa diubah sesuai kebutuhan

#### 5. **Keterangan** ⭐ Required
- **Field**: `keterangan`
- **Type**: Textarea
- **User Action**: Wajib diisi, bebas teks

#### 6. **Mode Input Barang** ⭐ Required
- **Field**: `item_type[]`
- **Type**: Toggle button
- **Options**: 
  - "Pilih dari Database" → Mode existing
  - "Input Manual" → Mode manual
- **User Action**: Pilih mode per item

---

### 📦 ITEM FIELDS (Per Barang)

#### MODE EXISTING (Pilih dari Database):

1. **Pilih Barang** ⭐ Required
   - **Field**: `existing_barang[]`
   - **Type**: Dropdown select
   - **Source**: Tabel `m_barang`
   - **Auto-fill**: Nama item, kode barang, harga, satuan
   - **User Action**: Pilih barang

2. **Nama Item** - Auto-filled
   - **Field**: `namaitem[]`
   - **Status**: READONLY
   - **Source**: Dari database

3. **Harga Satuan** ⭐ Required
   - **Field**: `harga[]`
   - **Type**: Number
   - **Default**: Dari database (bisa diubah)
   - **User Action**: Bisa diubah jika perlu

4. **Quantity** ⭐ Required
   - **Field**: `qty[]`
   - **Type**: Number
   - **Default**: 1
   - **User Action**: Wajib diisi > 0

5. **Deskripsi** - Optional
   - **Field**: `deskripsi[]`
   - **Type**: Textarea
   - **User Action**: Bebas diisi/kosong

6. **Link Pembelian** - Optional
   - **Field**: `linkpembelian[]`
   - **Type**: URL
   - **User Action**: Bebas diisi/kosong

---

#### MODE MANUAL (Input Manual):

1. **Nama Item** ⭐ Required
   - **Field**: `namaitem_manual[]`
   - **Type**: Text
   - **User Action**: Wajib diisi

2. **Kode Barang** - Auto-generated
   - **Field**: `kodebarang_manual[]`
   - **Status**: READONLY
   - **Format**: BRG-XXX
   - **User Action**: TIDAK BISA DIUBAH

3. **Harga Satuan** ⭐ Required
   - **Field**: `harga[]`
   - **Type**: Number
   - **User Action**: Wajib diisi > 0

4. **Quantity** ⭐ Required
   - **Field**: `qty[]`
   - **Type**: Number
   - **User Action**: Wajib diisi > 0

5. **Deskripsi** - Optional
   - **Field**: `deskripsi[]`
   - **Type**: Textarea
   - **User Action**: Bebas diisi/kosong

6. **Link Pembelian** - Optional
   - **Field**: `linkpembelian[]`
   - **Type**: URL
   - **User Action**: Bebas diisi/kosong

7. **Kode Project** - Auto-generated
   - **Field**: `kodeproject[]`
   - **Status**: READONLY
   - **Format**: PRJ-XX
   - **User Action**: TIDAK BISA DIUBAH

---

## 🔄 FLOW DATA INSERTION

### Saat Form Submit:

```
1. User klik Submit
   ↓
2. JavaScript Validation
   - Cek semua required fields
   - Cek qty > 0, harga > 0
   ↓
3. PHP Processing
   ↓
4. Insert to purchaserequest
   - idrequest: Auto-generate (PR2024XXXX)
   - namarequestor: Dari form (user ID)
   - idsupervisor: Dari form (user ID)
   - status: 1 (default)
   ↓
5. For Each Item:
   ↓
   A. If MODE EXISTING:
      - idbarang: Dari database (sudah ada)
      - namaitem: Dari database
      - Insert to detailrequest
   ↓
   B. If MODE MANUAL:
      - Auto-generate kodebarang (BRG-XXX)
      - Get next idbarang (MAX + 1)
      - Insert to m_barang FIRST
      - Use new idbarang
      - Insert to detailrequest
   ↓
6. Commit Transaction
   ↓
7. Success/Error Message
```

---

## 📊 Database Tables

### 1. purchaserequest
| Field | Source | Editable? |
|-------|--------|-----------|
| idrequest | Auto (PR2024XXXX) | ❌ No |
| tgl_req | User input | ✅ Yes |
| namarequestor | User select | ✅ Yes |
| keterangan | User input | ✅ Yes |
| tgl_butuh | User input | ✅ Yes |
| idsupervisor | User select | ✅ Yes |
| status | Default (1) | ❌ No |

### 2. m_barang (Only for Manual Items)
| Field | Source | Editable? |
|-------|--------|-----------|
| idbarang | Auto (MAX + 1) | ❌ No |
| kodebarang | Auto (BRG-XXX) | ❌ No |
| nama_barang | User input | ✅ Yes (saat input) |
| harga | User input | ✅ Yes (saat input) |
| satuan | Default (PCS) | ❌ No |

### 3. detailrequest
| Field | Source | Editable? |
|-------|--------|-----------|
| idrequest | From PR | ❌ No |
| idbarang | From m_barang | ❌ No |
| namaitem | User/DB | ✅ Yes (saat input) |
| deskripsi | User input | ✅ Yes |
| harga | User input | ✅ Yes |
| qty | User input | ✅ Yes |
| total | Calculated | ❌ No |
| linkpembelian | User input | ✅ Yes |
| kodeproject | Auto (PRJ-XX) | ❌ No |

---

## ✅ Summary

### READONLY (Auto-Generated):
1. ✅ Kode Barang (BRG-XXX)
2. ✅ Kode Project (PRJ-XX)
3. ✅ Total Harga (harga × qty)
4. ✅ ID Barang (saat create baru)
5. ✅ ID Request (PR2024XXXX)

### EDITABLE (User Input):
1. ✅ Nama Requestor (select)
2. ✅ Supervisor (select)
3. ✅ Tanggal Request
4. ✅ Tanggal Dibutuhkan
5. ✅ Keterangan
6. ✅ Nama Item (manual mode)
7. ✅ Harga Satuan
8. ✅ Quantity
9. ✅ Deskripsi
10. ✅ Link Pembelian

---

## 🎯 Key Points

1. **Auto-generated fields** → READONLY, tidak bisa diubah user
2. **Manual input fields** → Editable, user bisa isi sesuai kebutuhan
3. **Database integrity** → Semua foreign keys validated
4. **Sequential codes** → BRG-XXX dan PRJ-XX auto-increment
5. **Transaction safety** → Rollback jika ada error

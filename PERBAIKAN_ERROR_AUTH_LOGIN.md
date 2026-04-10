# Perbaikan Error Auth.php dan Login

## 🐛 Error yang Diperbaiki

### 1. **Undefined variable $pdo in auth.php line 56** (sebenarnya line 100)

#### Masalah:
```php
public function getLeaderType() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    global $pdo;  // ❌ ERROR: $pdo tidak ada di global scope
    $stmt = $pdo->prepare("SELECT leader_type FROM users WHERE iduser = ?");
    // ...
}
```

#### Penyelesaian:
✅ Menggunakan `$this->pdo` yang sudah tersedia di class Auth:
```php
public function getLeaderType() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    // Use $this->pdo instead of global $pdo
    $stmt = $this->pdo->prepare("SELECT leader_type FROM users WHERE iduser = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['leader_type'] : null;
}
```

**File:** `includes/auth.php` line 100

---

### 2. **Failed to open stream: includes/login-header.php**

#### Kemungkinan Penyebab:
1. **Path yang berbeda**: Error menyebut `C:\laragon\www\SIMBA\` bukan `C:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba`
2. **File tidak ada di folder tersebut**
3. **Working directory berbeda**

#### Solusi:
✅ Pastikan menggunakan absolute path dengan `__DIR__`:
```php
// SALAH ❌
include('includes/login-header.php');

// BENAR ✅
include(__DIR__ . '/includes/login-header.php');
// atau
include(__DIR__ . '/../includes/login-header.php');
```

**File yang perlu dicek:** `login.php` (di folder `C:\laragon\www\SIMBA\`)

---

## ✅ Perubahan yang Sudah Dilakukan

### File: `includes/auth.php`

**Sebelum:**
```php
public function getLeaderType() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    global $pdo;  // ❌
    $stmt = $pdo->prepare("SELECT leader_type FROM users WHERE iduser = ?");
    // ...
}
```

**Sesudah:**
```php
public function getLeaderType() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    // Use $this->pdo instead of global $pdo
    $stmt = $this->pdo->prepare("SELECT leader_type FROM users WHERE iduser = ?");
    // ...
}
```

---

## 📋 Checklist Perbaikan

- [x] **Fix undefined $pdo** - Menggunakan `$this->pdo` di method `getLeaderType()`
- [x] **Verifikasi file login-header.php** - File ada di workspace ini
- [ ] **Cek folder C:\laragon\www\SIMBA\** - Pastikan file ada di sana juga
- [ ] **Update path di login.php** - Gunakan `__DIR__` untuk absolute path

---

## 🔧 Rekomendasi Tambahan

### 1. **Pastikan Database Connection Ada**
Di `config/database.php`, pastikan `$pdo` didefinisikan:
```php
<?php
$host = 'localhost';
$dbname = 'simba';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
```

### 2. **Gunakan Autoload atau Require yang Konsisten**
Di setiap file PHP, pastikan urutan include benar:
```php
<?php
// 1. Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database connection
require_once __DIR__ . '/config/database.php';

// 3. Auth class
require_once __DIR__ . '/includes/auth.php';

// 4. Lainnya...
```

### 3. **Cek Folder Laragon**
Jika error masih muncul dari `C:\laragon\www\SIMBA\`:
- Pastikan file `includes/login-header.php` ada di folder tersebut
- Atau symlink/copy file dari workspace ke folder laragon

---

## 🧪 Testing

Setelah perbaikan, test dengan:
1. ✅ Login ke aplikasi
2. ✅ Cek apakah error `$pdo` masih muncul
3. ✅ Test fungsi `getLeaderType()` untuk user dengan role Leader
4. ✅ Verifikasi login page bisa diakses tanpa error

---

**Status:** ✅ Fixed (auth.php)  
**Tanggal:** 2026-04-10  
**File:** includes/auth.php

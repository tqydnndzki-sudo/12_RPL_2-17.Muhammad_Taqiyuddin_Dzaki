<?php
// Database initialization script
require_once 'config/database.php';

echo "Initializing SIMBA database...\n";

try {
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS simba CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "Database 'simba' created or already exists.\n";
    
    // Use database
    $pdo->exec("USE simba");
    echo "Using database 'simba'.\n";
    
    // Disable foreign key checks to allow dropping tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "Foreign key checks disabled.\n";
    
    // Drop tables if they exist
    $tables = [
        'logstatusbarang',
        'logstatusorder',
        'logstatusreq',
        'detailkeluar',
        'detailmasuk',
        'detailorder',
        'detailrequest',
        'barangkeluar',
        'barangmasuk',
        'purchaseorder',
        'purchaserequest',
        'inventory',
        'm_barang',
        'users',
        'kategoribarang',
        'sequences'
    ];
    
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "Dropped table '$table' if it existed.\n";
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Foreign key checks enabled.\n";
    
    // Create tables in correct order to handle foreign key constraints
    // Create sequences table
    $pdo->exec("CREATE TABLE sequences (
        name VARCHAR(100) PRIMARY KEY,
        last_no INT NOT NULL DEFAULT 0
    )");
    echo "Table 'sequences' created.\n";
    
    // Create kategoribarang table
    $pdo->exec("CREATE TABLE kategoribarang (
        idkategori INT PRIMARY KEY,
        nama_kategori VARCHAR(100) NOT NULL,
        keterangan TEXT NULL
    )");
    echo "Table 'kategoribarang' created.\n";
    
    // Create users table
    $pdo->exec("CREATE TABLE users (
        iduser VARCHAR(50) PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        nama VARCHAR(200) NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(150),
        roletype VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'users' created.\n";
    
    // Create m_barang table
    $pdo->exec("CREATE TABLE m_barang (
        idbarang VARCHAR(30) PRIMARY KEY,
        kodebarang VARCHAR(50) NOT NULL,
        nama_barang VARCHAR(255) NOT NULL,
        deskripsi TEXT,
        harga DECIMAL(15,2) DEFAULT 0,
        satuan VARCHAR(50),
        kodeproject VARCHAR(50),
        idkategori INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_m_barang_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori)
    )");
    echo "Table 'm_barang' created.\n";
    
    // Create inventory table
    $pdo->exec("CREATE TABLE inventory (
        idinventory VARCHAR(30) PRIMARY KEY,
        idbarang VARCHAR(30),
        kodebarang VARCHAR(50),
        idkategori INT,
        lokasi VARCHAR(100),
        kodeproject VARCHAR(50),
        nama_barang VARCHAR(255),
        harga DECIMAL(15,2) DEFAULT 0,
        stok_awal INT DEFAULT 0,
        stok_akhir INT DEFAULT 0,
        qty_in INT DEFAULT 0,
        qty_out INT DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        keterangan TEXT,
        CONSTRAINT fk_inventory_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_inventory_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori)
    )");
    echo "Table 'inventory' created.\n";
    
    // Create purchaserequest table
    $pdo->exec("CREATE TABLE purchaserequest (
        idrequest VARCHAR(30) PRIMARY KEY,
        iduserrequest VARCHAR(50),
        namarequestor VARCHAR(200),
        keterangan VARCHAR(255),
        tgl_req DATETIME,
        tgl_butuh DATE,
        idsupervisor VARCHAR(50),
        status VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pr_userrequest FOREIGN KEY (iduserrequest) REFERENCES users(iduser),
        CONSTRAINT fk_pr_supervisor FOREIGN KEY (idsupervisor) REFERENCES users(iduser)
    )");
    echo "Table 'purchaserequest' created.\n";
    
    // Create detailrequest table
    $pdo->exec("CREATE TABLE detailrequest (
        iddetailrequest INT AUTO_INCREMENT PRIMARY KEY,
        idbarang VARCHAR(30),
        idrequest VARCHAR(30),
        linkpembelian VARCHAR(255),
        namaitem VARCHAR(255),
        deskripsi TEXT,
        harga DECIMAL(15,2) DEFAULT 0,
        qty INT DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        kodeproject VARCHAR(50),
        CONSTRAINT fk_dr_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_dr_request FOREIGN KEY (idrequest) REFERENCES purchaserequest(idrequest)
    )");
    echo "Table 'detailrequest' created.\n";
    
    // Create purchaseorder table
    $pdo->exec("CREATE TABLE purchaseorder (
        idpurchaseorder VARCHAR(30) PRIMARY KEY,
        idrequest VARCHAR(30),
        supplier VARCHAR(200),
        tgl_po DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_po_request FOREIGN KEY (idrequest) REFERENCES purchaserequest(idrequest)
    )");
    echo "Table 'purchaseorder' created.\n";
    
    // Create detailorder table
    $pdo->exec("CREATE TABLE detailorder (
        iddetailorder INT AUTO_INCREMENT PRIMARY KEY,
        idpurchaseorder VARCHAR(30),
        idbarang VARCHAR(30),
        qty INT DEFAULT 0,
        harga DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        CONSTRAINT fk_do_po FOREIGN KEY (idpurchaseorder) REFERENCES purchaseorder(idpurchaseorder),
        CONSTRAINT fk_do_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang)
    )");
    echo "Table 'detailorder' created.\n";
    
    // Create barangmasuk table
    $pdo->exec("CREATE TABLE barangmasuk (
        idmasuk VARCHAR(30) PRIMARY KEY,
        idpurchaseorder VARCHAR(30),
        iduserprocurementcreate VARCHAR(50),
        iduserprocurementapproval VARCHAR(50),
        tgl_masuk DATETIME,
        keterangan TEXT,
        CONSTRAINT fk_bm_po FOREIGN KEY (idpurchaseorder) REFERENCES purchaseorder(idpurchaseorder),
        CONSTRAINT fk_bm_usercreate FOREIGN KEY (iduserprocurementcreate) REFERENCES users(iduser),
        CONSTRAINT fk_bm_userapproval FOREIGN KEY (iduserprocurementapproval) REFERENCES users(iduser)
    )");
    echo "Table 'barangmasuk' created.\n";
    
    // Create detailmasuk table
    $pdo->exec("CREATE TABLE detailmasuk (
        iddetailmasuk INT AUTO_INCREMENT PRIMARY KEY,
        idbarang VARCHAR(30),
        idmasuk VARCHAR(30),
        qty INT DEFAULT 0,
        harga DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        idkategori INT,
        CONSTRAINT fk_dm_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_dm_masuk FOREIGN KEY (idmasuk) REFERENCES barangmasuk(idmasuk),
        CONSTRAINT fk_dm_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori)
    )");
    echo "Table 'detailmasuk' created.\n";
    
    // Create barangkeluar table
    $pdo->exec("CREATE TABLE barangkeluar (
        idkeluar VARCHAR(30) PRIMARY KEY,
        iduserprocurementcreated VARCHAR(50),
        iduserprocurementapproved VARCHAR(50),
        tgl_keluar DATETIME,
        keterangan TEXT,
        CONSTRAINT fk_bk_user_created FOREIGN KEY (iduserprocurementcreated) REFERENCES users(iduser),
        CONSTRAINT fk_bk_user_approved FOREIGN KEY (iduserprocurementapproved) REFERENCES users(iduser)
    )");
    echo "Table 'barangkeluar' created.\n";
    
    // Create detailkeluar table
    $pdo->exec("CREATE TABLE detailkeluar (
        iddetailkeluar INT AUTO_INCREMENT PRIMARY KEY,
        idbarang VARCHAR(30),
        idkategori INT,
        idkeluar VARCHAR(30),
        qty INT DEFAULT 0,
        harga DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        CONSTRAINT fk_dk_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_dk_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori),
        CONSTRAINT fk_dk_keluar FOREIGN KEY (idkeluar) REFERENCES barangkeluar(idkeluar)
    )");
    echo "Table 'detailkeluar' created.\n";
    
    // Create logstatusreq table
    $pdo->exec("CREATE TABLE logstatusreq (
        idlogstatusreq INT AUTO_INCREMENT PRIMARY KEY,
        status INT NOT NULL,
        date DATETIME NOT NULL,
        note_reject TEXT,
        idrequest VARCHAR(30),
        CONSTRAINT fk_lsr_request FOREIGN KEY (idrequest) REFERENCES purchaserequest(idrequest)
    )");
    echo "Table 'logstatusreq' created.\n";
    
    // Create logstatusorder table
    $pdo->exec("CREATE TABLE logstatusorder (
        idlogstatusorder INT AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(50) NOT NULL,
        date DATETIME NOT NULL,
        keterangan TEXT,
        idpurchaseorder VARCHAR(30),
        CONSTRAINT fk_lso_po FOREIGN KEY (idpurchaseorder) REFERENCES purchaseorder(idpurchaseorder)
    )");
    echo "Table 'logstatusorder' created.\n";
    
    // Create logstatusbarang table
    $pdo->exec("CREATE TABLE logstatusbarang (
        idlogstatusbarang INT AUTO_INCREMENT PRIMARY KEY,
        iddetailrequest INT,
        status VARCHAR(50),
        date DATETIME,
        keterangan TEXT,
        CONSTRAINT fk_lsb_detailrequest FOREIGN KEY (iddetailrequest) REFERENCES detailrequest(iddetailrequest)
    )");
    echo "Table 'logstatusbarang' created.\n";
    
    // Insert kategoribarang data
    $stmt = $pdo->prepare("INSERT INTO kategoribarang (idkategori, nama_kategori, keterangan) VALUES (?, ?, ?)");
    $categories = [
        [1, 'Inventory', 'Item diatas 5000 dan dipergunakan untuk kebutuhan project'],
        [2, 'Asset', 'Item diatas 100.000, mendukung proses produksi, dipakai untuk operasional dan tidak dijual'],
        [3, 'WIP', 'Work in Progress (belum selesai dirakit)'],
        [4, 'Finish Good', 'Barang sudah jadi dan siap dijual'],
        [5, 'RAKO', 'Komponen dibawah 5000, tidak disimpan di gudang, digunakan utk PR & PO']
    ];
    
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
    echo "Inserted kategoribarang data.\n";
    
    // Create users table
    $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("CREATE TABLE users (
        iduser VARCHAR(50) PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        nama VARCHAR(200) NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(150),
        roletype VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'users' created.\n";
    
    // Insert users data
    $stmt = $pdo->prepare("INSERT INTO users (iduser, username, nama, password, email, roletype) VALUES (?, ?, ?, ?, ?, ?)");
    $users = [
        ['USR-001','admin','Admin User','admin123','admin@example.com','Admin'],
        ['USR-002','leader','Leader One','leader123','leader@example.com','Leader'],
        ['USR-003','manager','Manager Ops','manager123','manager@example.com','Manager'],
        ['USR-004','procure','Procurement','procure123','procure@example.com','Procurement'],
        ['USR-005','inventory','Inventory','inventory123','inventory@example.com','Inventory'],
        ['USR-006','nur','Nur','nur123','nur@example.com','Procurement'],
        ['USR-007','staff','Staff','staff123','staff@example.com','Staff']
    ];
    
    foreach ($users as $user) {
        $stmt->execute($user);
    }
    echo "Inserted users data.\n";
    
    // Create m_barang table
    $pdo->exec("DROP TABLE IF EXISTS m_barang");
    $pdo->exec("CREATE TABLE m_barang (
        idbarang VARCHAR(30) PRIMARY KEY,
        kodebarang VARCHAR(50) NOT NULL,
        nama_barang VARCHAR(255) NOT NULL,
        deskripsi TEXT,
        harga DECIMAL(15,2) DEFAULT 0,
        satuan VARCHAR(50),
        kodeproject VARCHAR(50),
        idkategori INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_m_barang_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori)
    )");
    echo "Table 'm_barang' created.\n";
    
    // Create inventory table
    $pdo->exec("DROP TABLE IF EXISTS inventory");
    $pdo->exec("CREATE TABLE inventory (
        idinventory VARCHAR(30) PRIMARY KEY,
        idbarang VARCHAR(30),
        kodebarang VARCHAR(50),
        idkategori INT,
        lokasi VARCHAR(100),
        kodeproject VARCHAR(50),
        nama_barang VARCHAR(255),
        harga DECIMAL(15,2) DEFAULT 0,
        stok_awal INT DEFAULT 0,
        stok_akhir INT DEFAULT 0,
        qty_in INT DEFAULT 0,
        qty_out INT DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        keterangan TEXT,
        CONSTRAINT fk_inventory_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_inventory_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori)
    )");
    echo "Table 'inventory' created.\n";
    
    // Create purchaserequest table
    $pdo->exec("DROP TABLE IF EXISTS purchaserequest");
    $pdo->exec("CREATE TABLE purchaserequest (
        idrequest VARCHAR(30) PRIMARY KEY,
        iduserrequest VARCHAR(50),
        namarequestor VARCHAR(200),
        keterangan VARCHAR(255),
        tgl_req DATETIME,
        tgl_butuh DATE,
        idsupervisor VARCHAR(50),
        status VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_pr_userrequest FOREIGN KEY (iduserrequest) REFERENCES users(iduser),
        CONSTRAINT fk_pr_supervisor FOREIGN KEY (idsupervisor) REFERENCES users(iduser)
    )");
    echo "Table 'purchaserequest' created.\n";
    
    // Create detailrequest table
    $pdo->exec("DROP TABLE IF EXISTS detailrequest");
    $pdo->exec("CREATE TABLE detailrequest (
        iddetailrequest INT AUTO_INCREMENT PRIMARY KEY,
        idbarang VARCHAR(30),
        idrequest VARCHAR(30),
        linkpembelian VARCHAR(255),
        namaitem VARCHAR(255),
        deskripsi TEXT,
        harga DECIMAL(15,2) DEFAULT 0,
        qty INT DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        kodeproject VARCHAR(50),
        CONSTRAINT fk_dr_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_dr_request FOREIGN KEY (idrequest) REFERENCES purchaserequest(idrequest)
    )");
    echo "Table 'detailrequest' created.\n";
    
    // Create purchaseorder table
    $pdo->exec("DROP TABLE IF EXISTS purchaseorder");
    $pdo->exec("CREATE TABLE purchaseorder (
        idpurchaseorder VARCHAR(30) PRIMARY KEY,
        idrequest VARCHAR(30),
        supplier VARCHAR(200),
        tgl_po DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_po_request FOREIGN KEY (idrequest) REFERENCES purchaserequest(idrequest)
    )");
    echo "Table 'purchaseorder' created.\n";
    
    // Create detailorder table
    $pdo->exec("DROP TABLE IF EXISTS detailorder");
    $pdo->exec("CREATE TABLE detailorder (
        iddetailorder INT AUTO_INCREMENT PRIMARY KEY,
        idpurchaseorder VARCHAR(30),
        idbarang VARCHAR(30),
        qty INT DEFAULT 0,
        harga DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        CONSTRAINT fk_do_po FOREIGN KEY (idpurchaseorder) REFERENCES purchaseorder(idpurchaseorder),
        CONSTRAINT fk_do_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang)
    )");
    echo "Table 'detailorder' created.\n";
    
    // Create barangmasuk table
    $pdo->exec("DROP TABLE IF EXISTS barangmasuk");
    $pdo->exec("CREATE TABLE barangmasuk (
        idmasuk VARCHAR(30) PRIMARY KEY,
        idpurchaseorder VARCHAR(30),
        iduserprocurementcreate VARCHAR(50),
        iduserprocurementapproval VARCHAR(50),
        tgl_masuk DATETIME,
        keterangan TEXT,
        CONSTRAINT fk_bm_po FOREIGN KEY (idpurchaseorder) REFERENCES purchaseorder(idpurchaseorder),
        CONSTRAINT fk_bm_usercreate FOREIGN KEY (iduserprocurementcreate) REFERENCES users(iduser),
        CONSTRAINT fk_bm_userapproval FOREIGN KEY (iduserprocurementapproval) REFERENCES users(iduser)
    )");
    echo "Table 'barangmasuk' created.\n";
    
    // Create detailmasuk table
    $pdo->exec("DROP TABLE IF EXISTS detailmasuk");
    $pdo->exec("CREATE TABLE detailmasuk (
        iddetailmasuk INT AUTO_INCREMENT PRIMARY KEY,
        idbarang VARCHAR(30),
        idmasuk VARCHAR(30),
        qty INT DEFAULT 0,
        harga DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        idkategori INT,
        CONSTRAINT fk_dm_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_dm_masuk FOREIGN KEY (idmasuk) REFERENCES barangmasuk(idmasuk),
        CONSTRAINT fk_dm_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori)
    )");
    echo "Table 'detailmasuk' created.\n";
    
    // Create barangkeluar table
    $pdo->exec("DROP TABLE IF EXISTS barangkeluar");
    $pdo->exec("CREATE TABLE barangkeluar (
        idkeluar VARCHAR(30) PRIMARY KEY,
        iduserprocurementcreated VARCHAR(50),
        iduserprocurementapproved VARCHAR(50),
        tgl_keluar DATETIME,
        keterangan TEXT,
        CONSTRAINT fk_bk_user_created FOREIGN KEY (iduserprocurementcreated) REFERENCES users(iduser),
        CONSTRAINT fk_bk_user_approved FOREIGN KEY (iduserprocurementapproved) REFERENCES users(iduser)
    )");
    echo "Table 'barangkeluar' created.\n";
    
    // Create detailkeluar table
    $pdo->exec("DROP TABLE IF EXISTS detailkeluar");
    $pdo->exec("CREATE TABLE detailkeluar (
        iddetailkeluar INT AUTO_INCREMENT PRIMARY KEY,
        idbarang VARCHAR(30),
        idkategori INT,
        idkeluar VARCHAR(30),
        qty INT DEFAULT 0,
        harga DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(20,2) DEFAULT 0,
        CONSTRAINT fk_dk_barang FOREIGN KEY (idbarang) REFERENCES m_barang(idbarang),
        CONSTRAINT fk_dk_kategori FOREIGN KEY (idkategori) REFERENCES kategoribarang(idkategori),
        CONSTRAINT fk_dk_keluar FOREIGN KEY (idkeluar) REFERENCES barangkeluar(idkeluar)
    )");
    echo "Table 'detailkeluar' created.\n";
    
    // Create logstatusreq table
    $pdo->exec("DROP TABLE IF EXISTS logstatusreq");
    $pdo->exec("CREATE TABLE logstatusreq (
        idlogstatusreq INT AUTO_INCREMENT PRIMARY KEY,
        status INT NOT NULL,
        date DATETIME NOT NULL,
        note_reject TEXT,
        idrequest VARCHAR(30),
        CONSTRAINT fk_lsr_request FOREIGN KEY (idrequest) REFERENCES purchaserequest(idrequest)
    )");
    echo "Table 'logstatusreq' created.\n";
    
    // Create logstatusorder table
    $pdo->exec("DROP TABLE IF EXISTS logstatusorder");
    $pdo->exec("CREATE TABLE logstatusorder (
        idlogstatusorder INT AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(50) NOT NULL,
        date DATETIME NOT NULL,
        keterangan TEXT,
        idpurchaseorder VARCHAR(30),
        CONSTRAINT fk_lso_po FOREIGN KEY (idpurchaseorder) REFERENCES purchaseorder(idpurchaseorder)
    )");
    echo "Table 'logstatusorder' created.\n";
    
    // Create logstatusbarang table
    $pdo->exec("DROP TABLE IF EXISTS logstatusbarang");
    $pdo->exec("CREATE TABLE logstatusbarang (
        idlogstatusbarang INT AUTO_INCREMENT PRIMARY KEY,
        iddetailrequest INT,
        status VARCHAR(50),
        date DATETIME,
        keterangan TEXT,
        CONSTRAINT fk_lsb_detailrequest FOREIGN KEY (iddetailrequest) REFERENCES detailrequest(iddetailrequest)
    )");
    echo "Table 'logstatusbarang' created.\n";
    
    // Initialize sequences
    $pdo->exec("INSERT INTO sequences (name, last_no) VALUES
        ('m_barang', 0),
        ('purchaserequest', 0),
        ('purchaseorder', 0),
        ('barangmasuk', 0),
        ('barangkeluar', 0),
        ('inventory', 0)
    ");
    echo "Sequences initialized.\n";
    
    echo "\nDatabase initialization completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
<?php
require_once 'config/database.php';

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, create a purchase request
    $insertPR = $pdo->prepare("INSERT INTO purchaserequest (idrequest, iduserrequest, tgl_req, namarequestor, keterangan, tgl_butuh, idsupervisor) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $insertPR->execute([
        'PR20230001',    // idrequest
        'USR-001',       // iduserrequest
        date('Y-m-d H:i:s'), // tgl_req
        'Test User',     // namarequestor
        'Test purchase request', // keterangan
        date('Y-m-d'),   // tgl_butuh
        'USR-002'        // idsupervisor
    ]);
    
    if (!$result) {
        throw new Exception("Failed to create purchase request");
    }
    
    // Then, insert into detailrequest table
    $insertDetail = $pdo->prepare("INSERT INTO detailrequest (idbarang, idrequest, linkpembelian, namaitem, deskripsi, harga, qty, total, kodeproject, kodebarang, satuan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $result = $insertDetail->execute([
        'BR20250001',      // idbarang
        'PR20230001',    // idrequest
        'https://example.com', // linkpembelian
        'Laptop Lenovo ThinkPad',     // namaitem
        'Laptop untuk kebutuhan kerja', // deskripsi
        12500000.00,          // harga
        2,               // qty
        25000000.00,          // total
        'PRJ-001',      // kodeproject
        'BR-001',        // kodebarang
        'Unit',           // satuan
        1                // status
    ]);
    
    if ($result) {
        echo "Insert successful!";
        
        // Commit transaction
        $pdo->commit();
        
        // Delete the test records
        $deleteStmt = $pdo->prepare("DELETE FROM detailrequest WHERE idrequest = ?");
        $deleteStmt->execute(['PR20230001']);
        
        $deleteStmt2 = $pdo->prepare("DELETE FROM purchaserequest WHERE idrequest = ?");
        $deleteStmt2->execute(['PR20230001']);
        
        echo "\nTest records deleted.";
    } else {
        throw new Exception("Insert failed!");
    }
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollback();
    echo "Error: " . $e->getMessage();
}
?>
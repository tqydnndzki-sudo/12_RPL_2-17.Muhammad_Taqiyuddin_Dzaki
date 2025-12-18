<?php
// Read the original file
$lines = file('c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\pages\procurement.php');

// Fix the INSERT query on line 86 (0-indexed as line 85)
$lines[85] = '            $insertPR = $pdo->prepare("INSERT INTO purchaserequest (idrequest, iduserrequest, tgl_req, namarequestor, keterangan, tgl_butuh, idsupervisor) VALUES (?, ?, ?, ?, ?, ?, ?)");' . "\n";

// Fix the userid issue on line 103 (0-indexed as line 102)
$lines[102] = '                $result = $logStatus->execute([$idrequest, $initialStatus, $iduserrequest]);' . "\n";

// Write the fixed content to a new file
file_put_contents('c:\Users\mhmmd\OneDrive\Dokumen\Desktop\Simba\pages\procurement_fixed.php', implode('', $lines));

echo "File fixed successfully!\n";
?>
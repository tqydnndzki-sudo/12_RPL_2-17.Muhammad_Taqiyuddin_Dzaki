<?php
/**
 * Function to generate PR ID
 * @param PDO $pdo Database connection
 * @return string Generated PR ID in format PRYYYYNNNN
 */
function generatePRId(PDO $pdo) {
    $year = date('Y');

    $stmt = $pdo->prepare("
        SELECT idrequest 
        FROM purchaserequest 
        WHERE idrequest LIKE :prefix 
        ORDER BY idrequest DESC 
        LIMIT 1
    ");
    $stmt->execute([':prefix' => "PR{$year}%"]);
    $lastId = $stmt->fetchColumn();

    if ($lastId) {
        $lastNumber = (int) substr($lastId, -4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    return 'PR' . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}
?>
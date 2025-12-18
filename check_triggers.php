<?php
require_once 'config/database.php';

try {
    // Check for triggers on detailrequest table
    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'detailrequest'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($triggers)) {
        echo "No triggers found on detailrequest table.\n";
    } else {
        echo "Triggers found on detailrequest table:\n";
        foreach ($triggers as $trigger) {
            echo "- Name: " . $trigger['Trigger'] . "\n";
            echo "  Event: " . $trigger['Event'] . "\n";
            echo "  Timing: " . $trigger['Timing'] . "\n";
            echo "  Statement: " . $trigger['Statement'] . "\n\n";
        }
    }
    
    // Also check for triggers on purchaserequest table
    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'purchaserequest'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($triggers)) {
        echo "No triggers found on purchaserequest table.\n";
    } else {
        echo "Triggers found on purchaserequest table:\n";
        foreach ($triggers as $trigger) {
            echo "- Name: " . $trigger['Trigger'] . "\n";
            echo "  Event: " . $trigger['Event'] . "\n";
            echo "  Timing: " . $trigger['Timing'] . "\n";
            echo "  Statement: " . $trigger['Statement'] . "\n\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
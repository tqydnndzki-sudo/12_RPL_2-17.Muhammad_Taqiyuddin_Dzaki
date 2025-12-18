# PR ID Generation Implementation Guide

## Overview

This guide explains how to implement the `generatePRId` function in the procurement system to generate properly formatted purchase request IDs.

## Changes Required

### 1. Add the generatePRId Function

Add this function to the top of `procurement.php`, right after the session start block:

```php
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
```

### 2. Modify the ID Generation in Add Purchase Request Handler

In the add purchase request section (around line 71), replace this block:

```php
// Generate ID for purchaserequest using sequences
$stmt = $pdo->prepare("SELECT last_no FROM sequences WHERE name = 'purchaserequest'");
$stmt->execute();
$sequence = $stmt->fetch(PDO::FETCH_ASSOC);
$lastNo = $sequence ? $sequence['last_no'] + 1 : 1;
$idrequest = 'PR-' . str_pad($lastNo, 6, '0', STR_PAD_LEFT);

// Update the sequence
$stmt = $pdo->prepare("INSERT INTO sequences (name, last_no) VALUES ('purchaserequest', ?) ON DUPLICATE KEY UPDATE last_no = ?");
$stmt->execute([$lastNo, $lastNo]);
```

With this single line:

```php
// Generate ID for purchaserequest using the generatePRId function
$idrequest = generatePRId($pdo);
```

## Benefits of This Implementation

1. **Proper Formatting**: Generates IDs in the format `PRYYYYNNNN` (e.g., PR20230001)
2. **Sequential Numbering**: Ensures sequential numbering within each year
3. **Year-Based Reset**: Numbering resets to 0001 each year
4. **Database Efficient**: Eliminates the need for sequence tables
5. **Easy to Understand**: Clear and maintainable code

## Testing

After implementing these changes:

1. Create a new purchase request
2. Verify the ID is generated in the correct format
3. Create multiple requests to ensure sequential numbering works
4. Test in a new year to verify the reset functionality

## Example IDs Generated

- First request of 2023: PR20230001
- Second request of 2023: PR20230002
- First request of 2024: PR20240001

# PR ID Generation Implementation Summary

## Objective

Implement a proper PR ID generation function in the procurement system that creates IDs in the format `PRYYYYNNNN` (e.g., PR20230001) with sequential numbering that resets each year.

## Current Issues

The current system uses a sequence table approach that generates IDs in the format `PR-NNNNNN` which doesn't follow the desired format and doesn't reset yearly.

## Solution Overview

1. Add a `generatePRId()` function to create properly formatted IDs
2. Replace the existing sequence-based ID generation with the new function
3. Maintain all existing functionality while improving ID generation

## Detailed Implementation Steps

### Step 1: Add the generatePRId Function

Add this function at the top of `procurement.php`, right after the session start block:

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

### Step 2: Replace ID Generation Code

In the add purchase request handler section (around lines 71-80), replace this block:

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

## Example IDs Generated

- First request of 2023: PR20230001
- Second request of 2023: PR20230002
- First request of 2024: PR20240001

## Files Created for Reference

1. `pr_id_implementation_guide.md` - Detailed implementation guide
2. `pr_id_patch.diff` - Patch file showing exact changes
3. `includes/pr_functions.php` - Separate file containing the function (alternative approach)

## Testing Instructions

After implementing these changes:

1. Create a new purchase request
2. Verify the ID is generated in the correct format
3. Create multiple requests to ensure sequential numbering works
4. Test in a new year to verify the reset functionality

## Backup Recommendation

A backup of the original `procurement.php` file has been created as `procurement_original.php`.

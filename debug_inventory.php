<?php
// Debug file to understand filter behavior
echo "Debug Info:<br>";
echo "GET params: " . print_r($_GET, true) . "<br>";

$selectedYear = (isset($_GET['year']) && $_GET['year'] !== '' && $_GET['year'] !== '0') ? (int)$_GET['year'] : 0;
$selectedMonth = (isset($_GET['month']) && $_GET['month'] !== '' && $_GET['month'] !== '0') ? (int)$_GET['month'] : 0;

echo "Selected Year: " . $selectedYear . "<br>";
echo "Selected Month: " . $selectedMonth . "<br>";

// Test condition
echo "Year condition: " . ($selectedYear > 0 ? "TRUE" : "FALSE") . "<br>";
echo "Month condition: " . ($selectedMonth > 0 ? "TRUE" : "FALSE") . "<br>";

// Test query construction
$yearCondition = "";
if ($selectedYear > 0) {
    $yearCondition = "AND (YEAR(bm.tgl_masuk) = :chart_year OR YEAR(bk.tgl_keluar) = :chart_year)";
}

echo "Year condition string: " . $yearCondition . "<br>";
?>
<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = 'beler';
$DB_NAME = 'simba';

// MySQLi connection
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("Koneksi database gagal: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// PDO connection for Auth class
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

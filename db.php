<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = 'beler';
$DB_NAME = 'simba';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("Koneksi database gagal: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

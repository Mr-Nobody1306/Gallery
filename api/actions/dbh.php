<?php
// Update these values for your PostgreSQL setup
$servername = 'localhost';
$port = 5432;
$username = 'postgres';
$password = '';
$dbname = 'gallery';

try {
    // Use the pgsql PDO driver
    $dsn = "pgsql:host={$servername};port={$port};dbname={$dbname}";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Connection failed: ' . $e->getMessage());
}
<?php
// Update these values for your PostgreSQL setup
$servername = 'db.vvlknevbekpxwxkjujlf.supabase.co';
$port = 5432;
$username = 'postgres';
$password = 'frimpong@76';
$dbname = 'gallery';

try {
    // Use the pgsql PDO driver
    $dsn = "pgsql:host={$servername};port={$port};dbname={$dbname}";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Connection failed: ' . $e->getMessage());
}

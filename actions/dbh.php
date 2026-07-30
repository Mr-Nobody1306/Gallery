<?php
$servername = 'localhost';
$username = 'root';
$password = 'frimpong@76';
$dbname = 'gallery';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname",$username,$password);
    $conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Connection failed:" . $e->getMessage());
}
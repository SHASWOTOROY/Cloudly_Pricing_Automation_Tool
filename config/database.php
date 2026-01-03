<?php
// Initialize application directories
require_once __DIR__ . '/init.php';

$host = 'localhost';
$db = 'aws_calc';
$user = 'root';
$pass = '';
$port = '3307';

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

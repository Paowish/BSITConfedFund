<?php
// Start session globally across the entire localhost
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day bago mag-expire
        'path' => '/',       // Available sa buong domain (hindi lang sa isang folder)
        'domain' => '',      // Default domain
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Set timezone para tama ang oras
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$dbname = 'bsit_fund_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
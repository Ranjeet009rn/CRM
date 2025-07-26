<?php
// Detect environment based on server name or IP
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
    // ✅ Localhost Configuration
    $host = "localhost";
    $username = "root";
    $password = ""; // Set your local password if required
    $database = "crm";
} else {
    // ✅ Hostinger Production Configuration
    $host = "193.203.184.228"; // or use "localhost" if Hostinger says so
    $username = "u876295706_support1";
    $password = "Ranjeet@1810";
    $database = "u876295706_crm_loan";
}

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Optional: Set UTF-8 charset
$conn->set_charset("utf8mb4");
?>

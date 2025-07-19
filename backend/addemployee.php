<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Input from frontend (form data)
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$mobile   = $_POST['mobile'] ?? '';
$email    = $_POST['email'] ?? '';
$role     = $_POST['role'] ?? ''; // Default role is "Employee" if not passed

// Basic validation
if (!$username || !$password || !$mobile || !$email || !$role) {
    echo json_encode(["success" => false, "error" => "All fields are required"]);
    exit();
}

// Check if username or email already exists
$checkStmt = $conn->prepare("SELECT id FROM employee WHERE username = ? OR email = ?");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(["success" => false, "error" => "Username or email already exists"]);
    $checkStmt->close();
    $conn->close();
    exit();
}
$checkStmt->close();

// Store data with role
$stmt = $conn->prepare("INSERT INTO employee (username, password, mobile, email, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $username, $password, $mobile, $email, $role);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Insert failed"]);
}

$stmt->close();
$conn->close();

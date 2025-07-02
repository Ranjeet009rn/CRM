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

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(["success" => false, "error" => "Missing fields"]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO employee (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $password);  // storing plain text password

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Insert failed"]);
}

$stmt->close();
$conn->close();
?>

<?php
// Enable error logging, disable output of errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Proper CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}



// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['id']) || !isset($data['status'])) {
    echo json_encode(["success" => false, "message" => "Invalid input"]);
    exit();
}

$taskId = $data['id'];
$status = $data['status'];

// Connect to DB via central config
require_once __DIR__ . '/db.php';
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); }
    exit();
}

// Update status
$stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $taskId);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed"]);
}

if (isset($stmt) && $stmt instanceof mysqli_stmt) { $stmt->close(); }
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database
require_once 'db.php';

// Validate user_id
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';

if ($user_type === 'admin') {
    $sql = "SELECT * FROM meetings WHERE user_type = 'admin' ORDER BY date DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT * FROM meetings WHERE user_id = ? AND user_type = 'employee' ORDER BY date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    exit();
}

$stmt->execute();
$result = $stmt->get_result();

$meetings = [];
while ($row = $result->fetch_assoc()) {
    $meetings[] = $row;
}

echo json_encode(["success" => true, "meetings" => $meetings]);

$stmt->close();
$conn->close();

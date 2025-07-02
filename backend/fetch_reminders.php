<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$user_type = isset($_GET['user_type']) ? strtolower(trim($_GET['user_type'])) : '';
$user_id   = isset($_GET['user_id']) ? intval($_GET['user_id']) : -1;

if (!$user_type || !in_array($user_type, ['admin', 'employee'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid or missing user type."]);
    exit();
}

// Base SQL
$sql = "SELECT id, repeat_type, datetime, message, pinned, user_id, user_type, created_at 
        FROM reminders";

// Filter for employee
if ($user_type === 'employee') {
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing or invalid user ID."]);
        exit();
    }

    $sql .= " WHERE user_type = 'employee' AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    // Admin sees all
    $sql .= " ORDER BY datetime DESC";
    $stmt = $conn->prepare($sql);
}

// Execute
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Prepare failed: " . $conn->error]);
    exit();
}

$stmt->execute();
$result = $stmt->get_result();

$reminders = [];
while ($row = $result->fetch_assoc()) {
    $reminders[] = $row;
}

echo json_encode([
    "success" => true,
    "reminders" => $reminders
]);

$stmt->close();
$conn->close();

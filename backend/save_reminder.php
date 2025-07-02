<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !is_array($input)) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit();
}

$repeatType = trim($input['repeatType'] ?? '');
$datetime = trim($input['datetime'] ?? '');
$message = trim($input['message'] ?? '');
$user_type = strtolower(trim($input['user_type'] ?? ''));
$user_id = $user_type === 'admin' ? 0 : intval($input['user_id'] ?? 0);

if (!$repeatType || !$datetime || !$message || !$user_type) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit();
}

if (!in_array($user_type, ['admin', 'employee'])) {
    echo json_encode(["success" => false, "error" => "Invalid user type"]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO reminders (repeat_type, datetime, message, created_at, pinned, user_id, user_type) VALUES (?, ?, ?, NOW(), 0, ?, ?)");

if (!$stmt) {
    echo json_encode(["success" => false, "error" => $conn->error]);
    exit();
}

$stmt->bind_param("sssis", $repeatType, $datetime, $message, $user_id, $user_type);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $stmt->insert_id]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();

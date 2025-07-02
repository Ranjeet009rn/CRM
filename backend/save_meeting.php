<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include DB
require_once 'db.php';

// Read JSON input
$raw = file_get_contents("php://input");
$data = json_decode($raw);

// Validate input
if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid JSON input"]);
    exit();
}

// Extract and sanitize input
$user_id   = isset($data->user_id) ? intval($data->user_id) : null;
$user_type = isset($data->user_type) ? strtolower(trim($data->user_type)) : null;
$recurrence = isset($data->recurrence) ? trim($data->recurrence) : '';
$date = isset($data->date) ? trim($data->date) : '';
$team = isset($data->team) ? trim($data->team) : '';
$message = isset($data->message) ? trim($data->message) : '';
$email_automation = !empty($data->email_automation) ? 1 : 0;
$whatsapp_automation = !empty($data->whatsapp_automation) ? 1 : 0;

// Validate required fields
if (!$user_id || !$user_type || !$recurrence || !$date || !$team || !$message) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit();
}

$allowedTypes = ['admin', 'employee'];
if (!in_array($user_type, $allowedTypes)) {
    echo json_encode(["success" => false, "error" => "Invalid user type"]);
    exit();
}

// Insert into database
$sql = "INSERT INTO meetings (recurrence, date, team, message, email_automation, whatsapp_automation, created_at, pinned, user_id, user_type) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 0, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    exit();
}

$stmt->bind_param("ssssiiis", $recurrence, $date, $team, $message, $email_automation, $whatsapp_automation, $user_id, $user_type);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "meeting_id" => $conn->insert_id]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>

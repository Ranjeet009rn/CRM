<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid JSON input"]);
    exit();
}

$user_id = isset($data->user_id) ? intval($data->user_id) : null;
$user_type = isset($data->user_type) ? strtolower(trim($data->user_type)) : null;
$title = isset($data->title) ? trim($data->title) : '';
$content = isset($data->content) ? trim($data->content) : '';

if (!$user_id || !$user_type || !$title || !$content) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit();
}

if (strlen($title) < 1 || strlen($content) < 1) {
    echo json_encode(["success" => false, "error" => "Title and content cannot be empty"]);
    exit();
}

// Allow admin, employee, and all sales/agent roles
$allowedTypes = ['admin', 'employee', 'sales officer', 'sales agent', 'agent', 'staff', 'manager', 'supervisor'];
if (!in_array($user_type, $allowedTypes)) {
    // Also allow any type that contains 'employee', 'sales', or 'agent' as substring
    $isAllowed = false;
    foreach (['employee', 'sales', 'agent', 'staff', 'manager'] as $keyword) {
        if (strpos($user_type, $keyword) !== false) {
            $isAllowed = true;
            break;
        }
    }
    if (!$isAllowed) {
        echo json_encode(["success" => false, "error" => "Invalid user type"]);
        exit();
    }
}

$sql = "INSERT INTO notes (user_id, user_type, title, content, created_at) VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    exit();
}

$stmt->bind_param("isss", $user_id, $user_type, $title, $content);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "note_id" => $conn->insert_id]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
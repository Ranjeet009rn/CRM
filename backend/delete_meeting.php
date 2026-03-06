<?php
// ===== CORS & Content-Type Headers =====
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// ===== Handle Preflight =====
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== Read Input Data =====
$id = null;
$userId = null;

if (strpos($_SERVER["CONTENT_TYPE"] ?? "", "application/json") !== false) {
    $input = json_decode(file_get_contents("php://input"), true);
    $id = $input["id"] ?? null;
    $userId = $input["user_id"] ?? null; // optional
} else {
    $id = $_POST["id"] ?? null;
    $userId = $_POST["user_id"] ?? null; // optional
}

if (!isset($id) || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid meeting id"]);
    exit;
}

// If user_id not provided, treat as admin (0) so dashboard can remove pinned meetings
if (!isset($userId) || !is_numeric($userId)) {
    $userId = 0;
}

$meetingId = (int)$id;
$userId = (int)$userId;

// ===== Connect to DB via central config =====
require_once __DIR__ . '/db.php';

// ===== Delete if user matches OR allow admin (user_id = 0 means admin) =====
$stmt = $conn->prepare("DELETE FROM meetings WHERE id = ? AND (user_id = ? OR ? = 0)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("iii", $meetingId, $userId, $userId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Unauthorized or meeting not found"]);
    }
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Deletion failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

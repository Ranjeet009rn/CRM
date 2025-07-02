<?php
// ===== CORS HEADERS =====
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
header("Access-Control-Max-Age: 86400");

// ===== Preflight request for CORS =====
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== Read JSON input =====
$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? intval($data['id']) : 0;
$userType = isset($data['user_type']) ? $data['user_type'] : '';

if ($id <= 0 || $userType !== 'admin') {
    echo json_encode([
        "success" => false,
        "message" => "⛔ Unauthorized or invalid input"
    ]);
    exit();
}

// ===== DB Connection =====
require_once 'db.php';

// ===== Update pinned status =====
$stmt = $conn->prepare("UPDATE notes SET pinned = 1 WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "📌 Note pinned successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "❌ Failed to pin note"
    ]);
}

// ===== Cleanup =====
$stmt->close();
$conn->close();
?>

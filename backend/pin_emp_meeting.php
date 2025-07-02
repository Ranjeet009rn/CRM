<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB connection
require_once 'db.php';

// Read POST body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "Meeting ID is required."]);
    exit();
}

$meetingId = intval($data['id']);

// Optional: Reset all meetings for this user if needed (unpin others)
// Example: Uncomment this block if you want only one pinned meeting at a time
/*
session_start();
$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    $stmt = $conn->prepare("UPDATE meetings SET pinned = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}
*/

// Now pin the selected meeting
$stmt = $conn->prepare("UPDATE meetings SET pinned = 1 WHERE id = ?");
$stmt->bind_param("i", $meetingId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Meeting pinned successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to pin meeting."]);
}

$stmt->close();
$conn->close();

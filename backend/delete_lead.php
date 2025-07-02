<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($data["id"])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Lead ID not provided"]);
    exit;
}

$leadId = intval($data["id"]);

// DB connection
require_once 'db.php';  // Use your central db.php file

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Prepare and execute delete query
$stmt = $conn->prepare("DELETE FROM leads WHERE id = ?");
$stmt->bind_param("i", $leadId);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to delete lead"]);
}

$stmt->close();
$conn->close();
?>

<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['enquiry_id'])) {
    echo json_encode(["success" => false, "message" => "Missing enquiry_id"]);
    exit();
}

$enquiry_id = intval($data['enquiry_id']);

$stmt = $conn->prepare("UPDATE enquiry SET status = 'completed' WHERE id = ?");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit();
}
$stmt->bind_param("i", $enquiry_id);
if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}
$stmt->close();
$conn->close(); 
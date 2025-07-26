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

if (!isset($data['enquiry_id']) || !isset($data['assigned_to'])) {
    echo json_encode(["success" => false, "message" => "Missing enquiry_id or assigned_to"]);
    exit();
}

$enquiry_id = intval($data['enquiry_id']);
$assigned_to = trim($data['assigned_to']);

$stmt = $conn->prepare("UPDATE enquiry SET assigned_to = ?, status = 'assigned' WHERE id = ?");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit();
}
$stmt->bind_param("si", $assigned_to, $enquiry_id);
if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}
$stmt->close();
$conn->close(); 
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing ID or status"]);
    exit();
}

$id = intval($input['id']);
$status = $conn->real_escape_string($input['status']);

$sql = "UPDATE leaves SET status = '$status' WHERE id = $id";

if ($conn->query($sql)) {
    echo json_encode(["success" => true]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$conn->close();
?>

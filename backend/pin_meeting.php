<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php'; // or your connection file

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'];

if (!$id) {
    echo json_encode(["success" => false, "message" => "Meeting ID missing"]);
    exit;
}

$query = "UPDATE meetings SET pinned = 1 WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Meeting pinned"]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to pin meeting"]);
}
?>

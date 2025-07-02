<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(["success" => false, "error" => "Missing ID"]);
    exit();
}

$stmt = $conn->prepare("DELETE FROM employee WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Delete failed"]);
}

$stmt->close();
$conn->close();
?>

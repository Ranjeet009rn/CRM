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

$id = $_POST['id'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (!$id || !$username || !$password) {
    echo json_encode(["success" => false, "error" => "Missing fields"]);
    exit();
}

$stmt = $conn->prepare("UPDATE employee SET username = ?, password = ? WHERE id = ?");
$stmt->bind_param("ssi", $username, $password, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Update failed"]);
}

$stmt->close();
$conn->close();
?>

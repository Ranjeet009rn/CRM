<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

require_once 'db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$user_type = $_GET['user_type'] ?? null;

if (!$user_id || !$user_type) {
  echo json_encode(["success" => false, "error" => "Missing user_id or user_type"]);
  exit();
}

if ($user_type === 'admin') {
  $sql = "SELECT * FROM notes WHERE user_type = 'admin' ORDER BY created_at DESC";
  $stmt = $conn->prepare($sql);
} else {
  $sql = "SELECT * FROM notes WHERE user_id = ? AND user_type = 'employee' ORDER BY created_at DESC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

$notes = [];
while ($row = $result->fetch_assoc()) {
  $notes[] = $row;
}

echo json_encode(["success" => true, "notes" => $notes]);

$stmt->close();
$conn->close();
?>

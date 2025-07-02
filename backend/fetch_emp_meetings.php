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

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    echo json_encode(["success" => false, "error" => "Missing user_id"]);
    exit();
}

// ✅ Only fetch meetings where user_type is 'employee'
$sql = "SELECT * FROM meetings WHERE user_id = ? AND user_type = 'employee' ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$meetings = [];
while ($row = $result->fetch_assoc()) {
    $meetings[] = $row;
}

echo json_encode(["success" => true, "meetings" => $meetings]);

$stmt->close();
$conn->close();

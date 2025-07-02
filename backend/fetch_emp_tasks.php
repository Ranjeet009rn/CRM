<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$username = isset($_GET['username']) ? $_GET['username'] : '';
if (!$username) {
    echo json_encode(['success' => false, 'message' => 'Username missing']);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM tasks WHERE assigned_to = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

echo json_encode(['success' => true, 'tasks' => $tasks]);
?>

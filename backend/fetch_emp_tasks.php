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
require_once 'permissions.php';

$userInfo = getUserFromRequest();
$username = $userInfo['username'] ?: (isset($_GET['username']) ? $_GET['username'] : '');

if (!$username) {
    echo json_encode(['success' => false, 'message' => 'Username missing']);
    exit();
}

// Exclude expired tasks: only show tasks where due_date is today or future, OR status is 'Completed'
// AND (created_by = username OR assigned_to = username)
$stmt = $conn->prepare("SELECT * FROM tasks WHERE (created_by = ? OR assigned_to = ?) AND (due_date >= CURDATE() OR status = 'Completed')");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

echo json_encode(['success' => true, 'tasks' => $tasks]);
?>
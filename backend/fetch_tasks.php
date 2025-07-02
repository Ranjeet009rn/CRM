<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method Not Allowed"]);
    exit();
}

require_once 'db.php';

$sql = "SELECT id, TRIM(assigned_to) AS assigned_to, TRIM(subject) AS subject, priority, recurrence, status, start_date, end_date, description, attachment FROM tasks ORDER BY id DESC";
$result = $conn->query($sql);

$tasks = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row = array_map(function ($val) {
            return is_string($val) ? trim($val) : $val;
        }, $row);
        $tasks[] = $row;
    }
}
echo json_encode(["success" => true, "tasks" => $tasks]);
$conn->close();
?>

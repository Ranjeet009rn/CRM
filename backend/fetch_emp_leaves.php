<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include DB connection
require_once 'db.php';

// Get the username
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (empty($username)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Missing or empty 'username' parameter"
    ]);
    exit();
}

// Fetch only that employee's leaves
$sql = "SELECT id, name, reason, startDate, endDate, appliedDate, status FROM leaves WHERE name = ? ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$leaves = [];

while ($row = $result->fetch_assoc()) {
    $leaves[] = $row;
}

echo json_encode([
    "success" => true,
    "leaves" => $leaves
]);

$stmt->close();
$conn->close();
?>

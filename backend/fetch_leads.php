<?php
// === CORS + JSON headers (required at top)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

// === DB connection
require_once 'db.php';

$sql = "SELECT * FROM leads ORDER BY created_at DESC";
$result = $conn->query($sql);
$leads = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
}

echo json_encode(["success" => true, "leads" => $leads]);
$conn->close();

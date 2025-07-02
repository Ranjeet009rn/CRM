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

// Include database connection
require_once 'db.php';

// Query to fetch all leave records
$sql = "
    SELECT 
        id, 
        name, 
        reason, 
        startDate, 
        endDate, 
        appliedDate, 
        status
    FROM leaves 
    ORDER BY id DESC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database query failed: " . $conn->error
    ]);
    exit();
}

$leaves = [];

while ($row = $result->fetch_assoc()) {
    $leaves[] = $row;
}

echo json_encode([
    "success" => true,
    "leaves" => $leaves
]);

$conn->close();
?>

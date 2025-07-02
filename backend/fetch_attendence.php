<?php
// Disable direct error output and log instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', _DIR_ . '/php-error.log');
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}


// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Only GET allowed"]);
    exit();
}

// Database connection
require_once 'db.php';

// Validate input
$empId = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
if ($empId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing or invalid employee_id"]);
    exit();
}

// Fetch records
$stmt = $conn->prepare("
    SELECT date, status
    FROM attendance
    WHERE employee_id = ?
    ORDER BY date DESC
");
$stmt->bind_param("i", $empId);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// Return JSON
echo json_encode(["success" => true, "records" => $records]);

$stmt->close();
$conn->close();
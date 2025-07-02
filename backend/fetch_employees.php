<?php
// ===== CORS HEADERS =====
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

// ===== Handle preflight request =====
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== DB Connection =====
require_once 'db.php';

$sql = "SELECT id, username AS name FROM employee ORDER BY username ASC";

$result = $conn->query($sql);
$employees = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

echo json_encode([
    "success" => true,
    "employees" => $employees
]);

$conn->close();
?>

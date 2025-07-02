<?php
// ✅ CORS HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");

// ✅ Preflight check
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ✅ DB connection
require_once 'db.php';

// ✅ Get incoming JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input"]);
    exit();
}

// ✅ Extract and validate fields
$name = $input['name'] ?? null;
$reason = $input['reason'] ?? null;
$startDate = $input['startDate'] ?? null;
$endDate = $input['endDate'] ?? null;
$appliedDate = $input['appliedDate'] ?? date('Y-m-d');
$status = 'pending';

if (!$name || !$reason || !$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

// ✅ Prepare SQL
$stmt = $conn->prepare("
    INSERT INTO leaves (name, reason, startDate, endDate, appliedDate, status)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssss", $name, $reason, $startDate, $endDate, $appliedDate, $status);

// ✅ Execute and respond
if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>

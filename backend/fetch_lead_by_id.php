<?php
// CORS HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// DB connection
require_once 'db.php';

// Validate input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["success" => false, "message" => "Valid ID required"]);
    exit;
}

$id = intval($_GET['id']);

// Prepare and execute query
$stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($lead = $result->fetch_assoc()) {
    echo json_encode(["success" => true, "lead" => $lead]);
} else {
    echo json_encode(["success" => false, "message" => "Lead not found"]);
}

$stmt->close();
$conn->close();
?>

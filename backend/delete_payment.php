<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    if (isset($conn)) @$conn->close();
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// Debug logging
error_log("Delete payment request data: " . print_r($data, true));

if (!$data || !isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "Payment ID is required"]);
    $conn->close();
    exit();
}

$id = intval($data['id']);
error_log("Attempting to delete payment with ID: " . $id);

// First check if the payment exists
$checkStmt = $conn->prepare("SELECT id FROM payments WHERE id = ?");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
error_log("Payment exists check - rows found: " . $checkResult->num_rows);

if ($checkResult->num_rows === 0) {
    // Let's also check what payments exist
    $allStmt = $conn->query("SELECT id FROM payments LIMIT 10");
    $existingIds = [];
    if ($allStmt) {
        while ($row = $allStmt->fetch_assoc()) {
            $existingIds[] = $row['id'];
        }
    }
    error_log("Existing payment IDs: " . implode(', ', $existingIds));
}
$checkStmt->close();

$stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Payment deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Payment not found"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Failed to delete payment: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

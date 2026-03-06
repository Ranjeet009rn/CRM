<?php
// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

$taskId      = $data['id'] ?? null;
$assignedTo  = $data['assigned_to'] ?? null;
$updatedBy   = $data['updated_by'] ?? 'admin';

if (!$taskId || !$assignedTo) {
    echo json_encode([
        'success' => false,
        'message' => 'Task ID and assigned_to are required.',
    ]);
    exit();
}

$stmt = $conn->prepare("UPDATE tasks SET assigned_to = ?, updated_at = NOW(), created_by = created_by WHERE id = ?");
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Prepare failed: ' . $conn->error,
    ]);
    exit();
}

$stmt->bind_param('si', $assignedTo, $taskId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Update failed: ' . $stmt->error,
    ]);
}

$stmt->close();
$conn->close();

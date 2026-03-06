<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    require_once 'db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['sanctioned_id']) || empty($input['sanctioned_id'])) {
        throw new Exception('Sanctioned ID is required', 400);
    }

    $sanctioned_id = (int)$input['sanctioned_id'];

    // Check if sanctioned loan exists
    $checkStmt = $conn->prepare("SELECT sanctioned_id FROM sanctioned_loan WHERE sanctioned_id = ?");
    $checkStmt->bind_param('i', $sanctioned_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Sanctioned loan not found', 404);
    }
    $checkStmt->close();

    // Delete sanctioned loan
    $stmt = $conn->prepare("DELETE FROM sanctioned_loan WHERE sanctioned_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $sanctioned_id);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to delete sanctioned loan', 500);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Sanctioned loan deleted successfully'
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

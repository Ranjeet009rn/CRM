<?php
// Fetch claim history for a specific sanctioned loan

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

require_once 'db.php';

try {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?: [];

    $sanctionedId = isset($input['sanctioned_id']) ? intval($input['sanctioned_id']) : 0;
    if ($sanctionedId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid sanctioned_id'
        ]);
        exit;
    }

    $sql = "SELECT id, sanctioned_id, lead_id, event_date, emi_type, notes, created_at
            FROM claim_history
            WHERE sanctioned_id = ?
            ORDER BY event_date ASC, id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $sanctionedId);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    echo json_encode([
        'success' => true,
        'history' => $history,
        'count' => count($history)
    ]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

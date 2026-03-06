<?php
// Fetch payment reminder history, optionally filtered by payment_id

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

    $paymentId = isset($input['payment_id']) ? intval($input['payment_id']) : 0;

    $sql = "SELECT id, payment_id, method, message, status, created_at
            FROM payment_reminders";

    $types = '';
    $params = [];

    if ($paymentId > 0) {
        $sql .= " WHERE payment_id = ?";
        $types .= 'i';
        $params[] = $paymentId;
    }

    $sql .= " ORDER BY created_at DESC, id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $reminders = [];
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }

    echo json_encode([
        'success' => true,
        'reminders' => $reminders,
        'count' => count($reminders)
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

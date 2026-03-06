<?php
// Add a payment reminder entry for a specific payment

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
    $method = isset($input['method']) ? trim($input['method']) : '';
    $message = isset($input['message']) ? trim($input['message']) : '';
    $status = isset($input['status']) ? trim($input['status']) : 'Sent';

    if ($paymentId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid payment_id'
        ]);
        exit;
    }

    if ($method === '') {
        echo json_encode([
            'success' => false,
            'error' => 'Method is required'
        ]);
        exit;
    }

    if ($message === '') {
        echo json_encode([
            'success' => false,
            'error' => 'Message is required'
        ]);
        exit;
    }

    $sql = "INSERT INTO payment_reminders (payment_id, method, message, status, created_at)
            VALUES (?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('isss', $paymentId, $method, $message, $status);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $insertId = $stmt->insert_id;

    echo json_encode([
        'success' => true,
        'id' => $insertId,
        'created_at' => date('Y-m-d H:i:s')
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

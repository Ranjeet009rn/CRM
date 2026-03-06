<?php
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
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new Exception('Invalid JSON payload', 400);
    }

    $leadId        = isset($data['lead_id']) ? (int)$data['lead_id'] : 0;
    $movementDate  = $data['date'] ?? null; // YYYY-MM-DD
    $sentTo        = trim($data['sent_to'] ?? '');
    $status        = trim($data['status'] ?? '');
    $remark        = trim($data['remark'] ?? '');
    $sanctionDate  = $data['sanction_date'] ?? null; // nullable
    $createdBy     = $data['created_by'] ?? ($_GET['user'] ?? 'system');

    if ($leadId <= 0 || !$movementDate || !$sentTo || !$status) {
        throw new Exception('lead_id, date, sent_to and status are required', 400);
    }

    // If status is Sanctioned and sanction date is not provided, default it to movement date
    if ($status === 'Sanctioned' && empty($sanctionDate)) {
        $sanctionDate = $movementDate;
    }

    // Look up lead name so we can store a snapshot in file_movements
    $leadName = null;
    $leadSql = "SELECT name FROM leads WHERE lead_id = ? LIMIT 1";
    if ($leadStmt = $conn->prepare($leadSql)) {
        $leadStmt->bind_param('i', $leadId);
        $leadStmt->execute();
        $leadRes = $leadStmt->get_result();
        if ($leadRow = $leadRes->fetch_assoc()) {
            $leadName = $leadRow['name'] ?? null;
        }
        $leadStmt->close();
    }

    // Always INSERT a new movement row so full history is preserved
    $sql = "INSERT INTO file_movements (lead_id, movement_date, sent_to, status, remark, sanction_date, created_by, lead_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param(
        'isssssss',
        $leadId,
        $movementDate,
        $sentTo,
        $status,
        $remark,
        $sanctionDate,
        $createdBy,
        $leadName
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
    }

    $responseId = $stmt->insert_id;
    $message = 'File movement saved successfully';

    echo json_encode([
        'success' => true,
        'message' => $message,
        'id'      => $responseId,
    ]);

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

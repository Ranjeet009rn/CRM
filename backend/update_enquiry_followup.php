<?php
// Update enquiry follow-up status and reminder info

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
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        throw new Exception('Invalid JSON payload');
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $followupStatus = isset($input['followup_status']) ? trim($input['followup_status']) : '';
    $reminderType = isset($input['reminder_type']) ? trim($input['reminder_type']) : '';

    if ($id <= 0) {
        throw new Exception('Invalid enquiry id');
    }

    // Compute next_followup_date based on reminder_type
    $nextFollowupDate = null;
    if ($reminderType === '2_days') {
        $nextFollowupDate = date('Y-m-d', strtotime('+2 days'));
    } elseif ($reminderType === '4_days') {
        $nextFollowupDate = date('Y-m-d', strtotime('+4 days'));
    }

    // NOTE: This script expects that the `enquiry` table has columns:
    //   followup_status VARCHAR(50) NULL
    //   reminder_type   VARCHAR(50) NULL
    //   next_followup_date DATE NULL
    // If they do not exist, please add them in phpMyAdmin before using this API.

    $sql = "UPDATE enquiry
            SET followup_status = ?,
                reminder_type = ?,
                next_followup_date = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('sssi', $followupStatus, $reminderType, $nextFollowupDate, $id);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Follow-up updated successfully',
    ]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}

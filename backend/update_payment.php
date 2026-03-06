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

if (!$data || !isset($data['id']) || !isset($data['type']) || !isset($data['amount']) || !isset($data['reason'])) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    $conn->close();
    exit();
}

$id = intval($data['id']);
$type = $data['type'];
$date = $data['date'] ?? date('Y-m-d');
$expected_date = $data['expected_date'] ?? null;
$amount = floatval($data['amount']);
$reason = $data['reason'];
$emp_id = !empty($data['emp_id']) ? intval($data['emp_id']) : null;
$customer_id = !empty($data['customer_id']) ? intval($data['customer_id']) : null;
$lead_id = !empty($data['lead_id']) ? intval($data['lead_id']) : null;
$mode = $data['mode'] ?? null;
$online_mode = $data['online_mode'] ?? null;
// Optional: amount paid now when reducing a Pending payment
$paid_now = isset($data['paid_now']) ? floatval($data['paid_now']) : 0.0;

// Detect optional expected_date column
$hasExpectedDate = false;
$colCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'expected_date'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasExpectedDate = true;
}
if ($colCheck) { $colCheck->free(); }

if ($hasExpectedDate) {
    $sql = "UPDATE payments 
            SET type = ?, date = ?, expected_date = ?, amount = ?, reason = ?, emp_id = ?, customer_id = ?, lead_id = ?, mode = ?, online_mode = ?, updated_at = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("sssdsiiissi", $type, $date, $expected_date, $amount, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode, $id);
} else {
    // Fallback without expected_date
    $sql = "UPDATE payments 
            SET type = ?, date = ?, amount = ?, reason = ?, emp_id = ?, customer_id = ?, lead_id = ?, mode = ?, online_mode = ?, updated_at = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit();
    }
    $stmt->bind_param("ssdsiisssi", $type, $date, $amount, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode, $id);
}

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // If this is a Pending payment and some amount is marked as paid_now,
        // record that as a separate Inward payment entry for tracking collections.
        if ($type === 'Pending' && $paid_now > 0 && $lead_id) {
            // Use today's date for the actual payment record so history reflects real payment date
            $today = date('Y-m-d');
            if ($hasExpectedDate) {
                $insertSql = "INSERT INTO payments (type, date, expected_date, amount, reason, emp_id, customer_id, lead_id, mode, online_mode, created_at)
                               VALUES ('Inward', ?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW())";
                if ($insStmt = $conn->prepare($insertSql)) {
                    $insStmt->bind_param('sdsiiiss', $today, $paid_now, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode);
                    if (!$insStmt->execute()) {
                        error_log('[update_payment] Failed to insert Inward row for paid_now: ' . $insStmt->error);
                    }
                    $insStmt->close();
                } else {
                    error_log('[update_payment] Failed to prepare Inward insert statement: ' . $conn->error);
                }
            } else {
                $insertSql = "INSERT INTO payments (type, date, amount, reason, emp_id, customer_id, lead_id, mode, online_mode, created_at)
                               VALUES ('Inward', ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                if ($insStmt = $conn->prepare($insertSql)) {
                    $insStmt->bind_param('sdsiiiss', $today, $paid_now, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode);
                    if (!$insStmt->execute()) {
                        error_log('[update_payment] Failed to insert Inward row for paid_now (no expected_date): ' . $insStmt->error);
                    }
                    $insStmt->close();
                } else {
                    error_log('[update_payment] Failed to prepare Inward insert statement (no expected_date): ' . $conn->error);
                }
            }
        }

        // If this payment is linked to a lead, recompute that lead's payment progress.
        // We treat:
        //  - 'Pending' payments as current outstanding amount
        //  - 'Inward' payments as actual received amount
        // Lead status:
        //  - If total pending_amount > 0  => keep as due (no automatic change here)
        //  - If total pending_amount <= 0 AND there is at least some payment row => Payment Approved
        if ($lead_id) {
            $sumSql = "SELECT 
                            SUM(CASE WHEN type = 'Pending' THEN amount ELSE 0 END) AS pending_amount,
                            SUM(CASE WHEN type = 'Inward' THEN amount ELSE 0 END) AS inward_amount,
                            COUNT(*) AS payment_count
                        FROM payments
                        WHERE lead_id = ?";

            if ($sumStmt = $conn->prepare($sumSql)) {
                $sumStmt->bind_param('i', $lead_id);
                if ($sumStmt->execute()) {
                    $sumResult = $sumStmt->get_result();
                    if ($sumRow = $sumResult->fetch_assoc()) {
                        $pendingAmount = isset($sumRow['pending_amount']) ? (float)$sumRow['pending_amount'] : 0.0;
                        $inwardAmount  = isset($sumRow['inward_amount']) ? (float)$sumRow['inward_amount'] : 0.0;
                        $paymentCount  = isset($sumRow['payment_count']) ? (int)$sumRow['payment_count'] : 0;

                        error_log("[update_payment] Lead $lead_id payment summary => pending: $pendingAmount, inward: $inwardAmount, count: $paymentCount");

                        if ($paymentCount > 0 && $pendingAmount <= 0) {
                            $updateSql = "UPDATE leads SET status = 'Payment Approved' WHERE lead_id = ?";
                            if ($updateStmt = $conn->prepare($updateSql)) {
                                $updateStmt->bind_param('i', $lead_id);
                                if (!$updateStmt->execute()) {
                                    error_log('[update_payment] Failed to update lead status to Payment Approved: ' . $updateStmt->error);
                                }
                                $updateStmt->close();
                            } else {
                                error_log('[update_payment] Failed to prepare lead status update statement: ' . $conn->error);
                            }
                        }
                    }
                    $sumResult->free();
                } else {
                    error_log('[update_payment] Failed to compute payment summary for lead ' . $lead_id . ': ' . $sumStmt->error);
                }
                $sumStmt->close();
            } else {
                error_log('[update_payment] Failed to prepare payment summary statement: ' . $conn->error);
            }
        }

        echo json_encode(["success" => true, "message" => "Payment updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "No changes made or payment not found"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Failed to update payment: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

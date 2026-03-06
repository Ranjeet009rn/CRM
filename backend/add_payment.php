<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit();
}

require_once __DIR__ . '/db.php';

try {
    if (!isset($conn) || $conn->connect_error) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "DB connection failed"]);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // Debug logging
    error_log("Add payment request data: " . print_r($data, true));

    if (!$data || !isset($data['type']) || !isset($data['amount']) || !isset($data['reason'])) {
        error_log("Missing required fields in payment data");
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
        return;
    }

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
    $other_customer_name = $data['other_customer_name'] ?? null;

    // Detect whether payments table has optional columns
    $hasLeadId = false;
    $hasExpectedDate = false;
    $hasOtherCustomerName = false;

    $colCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'lead_id'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasLeadId = true;
    }
    if ($colCheck) { $colCheck->free(); }

    $colCheck2 = $conn->query("SHOW COLUMNS FROM payments LIKE 'expected_date'");
    if ($colCheck2 && $colCheck2->num_rows > 0) {
        $hasExpectedDate = true;
    }
    if ($colCheck2) { $colCheck2->free(); }

    $colCheck3 = $conn->query("SHOW COLUMNS FROM payments LIKE 'other_customer_name'");
    if ($colCheck3 && $colCheck3->num_rows > 0) {
        $hasOtherCustomerName = true;
    }
    if ($colCheck3) { $colCheck3->free(); }

    if ($hasLeadId && $hasExpectedDate) {
        if ($hasOtherCustomerName) {
            $sql = "INSERT INTO payments (type, date, expected_date, amount, reason, emp_id, customer_id, lead_id, mode, online_mode, other_customer_name, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssdsiiisss", $type, $date, $expected_date, $amount, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode, $other_customer_name);
            error_log("Executing payment insert with values (with lead_id, expected_date, other_customer_name): type=$type, date=$date, expected_date=$expected_date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, lead_id=$lead_id, mode=$mode, online_mode=$online_mode, other_customer_name=$other_customer_name");
        } else {
            $sql = "INSERT INTO payments (type, date, expected_date, amount, reason, emp_id, customer_id, lead_id, mode, online_mode, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssdsiiiss", $type, $date, $expected_date, $amount, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode);
            error_log("Executing payment insert with values (with lead_id, expected_date): type=$type, date=$date, expected_date=$expected_date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, lead_id=$lead_id, mode=$mode, online_mode=$online_mode");
        }
    } elseif ($hasLeadId) {
        if ($hasOtherCustomerName) {
            $sql = "INSERT INTO payments (type, date, amount, reason, emp_id, customer_id, lead_id, mode, online_mode, other_customer_name, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssdsiissss", $type, $date, $amount, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode, $other_customer_name);
            error_log("Executing payment insert with values (with lead_id, other_customer_name): type=$type, date=$date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, lead_id=$lead_id, mode=$mode, online_mode=$online_mode, other_customer_name=$other_customer_name");
        } else {
            $sql = "INSERT INTO payments (type, date, amount, reason, emp_id, customer_id, lead_id, mode, online_mode, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssdsiisss", $type, $date, $amount, $reason, $emp_id, $customer_id, $lead_id, $mode, $online_mode);
            error_log("Executing payment insert with values (with lead_id): type=$type, date=$date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, lead_id=$lead_id, mode=$mode, online_mode=$online_mode");
        }
    } elseif ($hasExpectedDate) {
        if ($hasOtherCustomerName) {
            $sql = "INSERT INTO payments (type, date, expected_date, amount, reason, emp_id, customer_id, mode, online_mode, other_customer_name, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssdsissss", $type, $date, $expected_date, $amount, $reason, $emp_id, $customer_id, $mode, $online_mode, $other_customer_name);
            error_log("Executing payment insert with values (with expected_date, other_customer_name): type=$type, date=$date, expected_date=$expected_date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, mode=$mode, online_mode=$online_mode, other_customer_name=$other_customer_name");
        } else {
            $sql = "INSERT INTO payments (type, date, expected_date, amount, reason, emp_id, customer_id, mode, online_mode, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssdsisss", $type, $date, $expected_date, $amount, $reason, $emp_id, $customer_id, $mode, $online_mode);
            error_log("Executing payment insert with values (with expected_date): type=$type, date=$date, expected_date=$expected_date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, mode=$mode, online_mode=$online_mode");
        }
    } else {
        // Fallback: insert without lead_id/expected_date columns
        if ($hasOtherCustomerName) {
            $sql = "INSERT INTO payments (type, date, amount, reason, emp_id, customer_id, mode, online_mode, other_customer_name, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssdsissss", $type, $date, $amount, $reason, $emp_id, $customer_id, $mode, $online_mode, $other_customer_name);
            error_log("Executing payment insert with values (basic, other_customer_name): type=$type, date=$date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, mode=$mode, online_mode=$online_mode, other_customer_name=$other_customer_name");
        } else {
            $sql = "INSERT INTO payments (type, date, amount, reason, emp_id, customer_id, mode, online_mode, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssdsisss", $type, $date, $amount, $reason, $emp_id, $customer_id, $mode, $online_mode);
            error_log("Executing payment insert with values (basic): type=$type, date=$date, amount=$amount, reason=$reason, emp_id=$emp_id, customer_id=$customer_id, mode=$mode, online_mode=$online_mode");
        }
    }

    if ($stmt->execute()) {
        $insertId = $stmt->insert_id;
        error_log("Payment added successfully with ID: " . $insertId);

        // If this payment is linked to a lead, recompute that lead's payment progress.
        if ($hasLeadId && $lead_id) {
            $sumSql = "SELECT 
                            SUM(CASE WHEN type = 'Pending' THEN amount ELSE 0 END) AS pending_amount,
                            SUM(CASE WHEN type = 'Inward' THEN amount ELSE 0 END) AS inward_amount
                        FROM payments
                        WHERE lead_id = ?";

            if ($sumStmt = $conn->prepare($sumSql)) {
                $sumStmt->bind_param('i', $lead_id);
                if ($sumStmt->execute()) {
                    $sumResult = $sumStmt->get_result();
                    if ($sumRow = $sumResult->fetch_assoc()) {
                        $pendingAmount = isset($sumRow['pending_amount']) ? (float)$sumRow['pending_amount'] : 0.0;
                        $inwardAmount  = isset($sumRow['inward_amount']) ? (float)$sumRow['inward_amount'] : 0.0;

                        error_log("Lead $lead_id payment summary => pending: $pendingAmount, inward: $inwardAmount");

                        if (($pendingAmount > 0 && $inwardAmount >= $pendingAmount) || $pendingAmount <= 0) {
                            $updateSql = "UPDATE leads SET status = 'Payment Approved' WHERE lead_id = ?";
                            if ($updateStmt = $conn->prepare($updateSql)) {
                                $updateStmt->bind_param('i', $lead_id);
                                if (!$updateStmt->execute()) {
                                    error_log('Failed to update lead status to Payment Approved: ' . $updateStmt->error);
                                }
                                $updateStmt->close();
                            } else {
                                error_log('Failed to prepare lead status update statement: ' . $conn->error);
                            }
                        }
                    }
                    if (isset($sumResult)) { $sumResult->free(); }
                } else {
                    error_log('Failed to compute payment summary for lead ' . $lead_id . ': ' . $sumStmt->error);
                }
                $sumStmt->close();
            } else {
                error_log('Failed to prepare payment summary statement: ' . $conn->error);
            }
        }

        echo json_encode(["success" => true, "message" => "Payment added successfully", "id" => $insertId]);
    } else {
        error_log("Failed to add payment: " . $stmt->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to add payment: " . $stmt->error]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} finally {
    if (isset($sumResult) && $sumResult instanceof mysqli_result) { @$sumResult->free(); }
    if (isset($sumStmt) && $sumStmt instanceof mysqli_stmt) { @$sumStmt->close(); }
    if (isset($updateStmt) && $updateStmt instanceof mysqli_stmt) { @$updateStmt->close(); }
    if (isset($stmt) && $stmt instanceof mysqli_stmt) { @$stmt->close(); }
    if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); }
}

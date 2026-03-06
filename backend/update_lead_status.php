<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("=== UPDATE STATUS REQUEST START ===");

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    require_once 'db.php';

    // Get raw POST data
    $json = file_get_contents('php://input');
    error_log("Raw JSON: " . $json);
    
    $data = json_decode($json, true);
    error_log("Decoded data: " . print_r($data, true));

    // Validate input - check for both 'id' and 'lead_id'
    $leadId = $data['lead_id'] ?? $data['id'] ?? null;
    $status = $data['status'] ?? null;
    $remark = $data['remark'] ?? null; // Optional scrutiny remark

    error_log("Lead ID: " . $leadId);
    error_log("Status: " . $status);

    if (!$leadId || !$status) {
        throw new Exception('Lead ID and status are required', 400);
    }

    // Validate status
    $validStatuses = ['New', 'In Progress', 'On Hold', 'Scrutinized', 'Sanctioned', 'Payment Approved', 'Claimed', 'Rejected'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status value: ' . $status, 400);
    }

    // Check if lead exists first
    $checkStmt = $conn->prepare("SELECT lead_id, status FROM leads WHERE lead_id = ?");
    $checkStmt->bind_param("i", $leadId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Lead not found with ID: ' . $leadId, 404);
    }
    
    $currentLead = $result->fetch_assoc();
    error_log("Current lead status: " . $currentLead['status']);
    $checkStmt->close();

    // Update lead status using lead_id.
    // Always touch updated_at; if status is 'Scrutinized', also remember who scrutinized it and store remark.
    if ($status === 'Scrutinized') {
        $stmt = $conn->prepare("UPDATE leads SET status = ?, scrutinized_by = assigned_to, scrutiny_remark = ?, updated_at = NOW() WHERE lead_id = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
        $stmt->bind_param("ssi", $status, $remark, $leadId);
    } else {
        $stmt = $conn->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE lead_id = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
        $stmt->bind_param("si", $status, $leadId);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
    }

    $affectedRows = $stmt->affected_rows;
    error_log("Affected rows: " . $affectedRows);

    if ($affectedRows === 0 && $currentLead['status'] === $status) {
        // Status is already the same, return success anyway
        error_log("Status unchanged (already " . $status . ")");
    } else if ($affectedRows === 0) {
        throw new Exception('Failed to update status', 500);
    }

    error_log("Status updated successfully to: " . $status);

    // Auto-create sanctioned loan entry if status is changed to "Sanctioned"
    if ($status === 'Sanctioned') {
        error_log("Creating sanctioned loan entry for lead ID: " . $leadId);
        
        // Get lead details for sanctioned loan
        $leadStmt = $conn->prepare("SELECT loan_amount, emi_frequency, interest_rate, loan_tenure, emi FROM leads WHERE lead_id = ?");
        $leadStmt->bind_param('i', $leadId);
        $leadStmt->execute();
        $leadResult = $leadStmt->get_result();
        $leadData = $leadResult->fetch_assoc();
        $leadStmt->close();
        
        if ($leadData && $leadData['loan_amount']) {
            $loanAmount = $leadData['loan_amount'];
            $emiType = $leadData['emi_frequency'] ?? 'Monthly';
            $interestRate = $leadData['interest_rate'] ?? 10;
            $loanTenure = $leadData['loan_tenure'] ?? 12;
            $emi = $leadData['emi'] ?? 0;
            
            // Insert into sanctioned_loan table
            $sanctionSql = "INSERT INTO sanctioned_loan 
                           (lead_id, sanctioned_date, loan_amount, emi_type, interest_rate, loan_tenure, emi, claim_status, disbursed) 
                           VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'not started', 'no')
                           ON DUPLICATE KEY UPDATE 
                           loan_amount = VALUES(loan_amount),
                           emi_type = VALUES(emi_type),
                           interest_rate = VALUES(interest_rate),
                           loan_tenure = VALUES(loan_tenure),
                           emi = VALUES(emi),
                           sanctioned_date = CURDATE(),
                           updated_at = CURRENT_TIMESTAMP";
            
            $sanctionStmt = $conn->prepare($sanctionSql);
            if ($sanctionStmt) {
                $sanctionStmt->bind_param("idssis", $leadId, $loanAmount, $emiType, $interestRate, $loanTenure, $emi);
                
                if ($sanctionStmt->execute()) {
                    error_log("Successfully created sanctioned loan entry");
                } else {
                    error_log("Failed to create sanctioned loan: " . $sanctionStmt->error);
                }
                $sanctionStmt->close();
            } else {
                error_log("Failed to prepare sanctioned loan statement: " . $conn->error);
            }
        } else {
            error_log("No loan amount found for lead ID: " . $leadId);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'status' => $status,
        'lead_id' => $leadId,
        'affected_rows' => $affectedRows
    ]);

    $stmt->close();
    $conn->close();
    error_log("=== UPDATE STATUS REQUEST END ===");

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$lead_id = isset($input['lead_id']) ? intval($input['lead_id']) : 0;
$loan_amount = isset($input['loan_amount']) ? floatval($input['loan_amount']) : 0;
$emi_type = isset($input['emi_type']) ? $input['emi_type'] : 'Monthly';
$interest_rate = isset($input['interest_rate']) ? $input['interest_rate'] : '';
$loan_tenure = isset($input['loan_tenure']) ? intval($input['loan_tenure']) : 0;
$emi = isset($input['emi']) ? floatval($input['emi']) : 0;

if ($lead_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid lead ID']);
    exit;
}

// Check if sanctioned loan already exists for this lead
$check_sql = "SELECT sanctioned_id FROM sanctioned_loan WHERE lead_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $lead_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update existing record
    $sql = "UPDATE sanctioned_loan SET 
            loan_amount = ?, 
            emi_type = ?, 
            interest_rate = ?, 
            loan_tenure = ?, 
            emi = ?,
            sanctioned_date = CURDATE(),
            updated_at = CURRENT_TIMESTAMP
            WHERE lead_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dssidi", $loan_amount, $emi_type, $interest_rate, $loan_tenure, $emi, $lead_id);
} else {
    // Insert new record
    $sql = "INSERT INTO sanctioned_loan 
            (lead_id, sanctioned_date, loan_amount, emi_type, interest_rate, loan_tenure, emi, claim_status, disbursed) 
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'not started', 'no')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idssis", $lead_id, $loan_amount, $emi_type, $interest_rate, $loan_tenure, $emi);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Sanctioned loan saved successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save sanctioned loan: ' . $stmt->error]);
}

$stmt->close();
$check_stmt->close();
$conn->close();
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to catch any errors
ob_start();

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
    
    // Check if database connection exists
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed', 500);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed', 405);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Log input for debugging
    error_log("Create Sanctioned Loan - Raw Input: " . $rawInput);
    error_log("Create Sanctioned Loan - Parsed Input: " . print_r($input, true));
    
    // Validate required fields
    if (!isset($input['lead_id']) || empty($input['lead_id'])) {
        throw new Exception('Lead ID is required', 400);
    }
    if (!isset($input['loan_amount']) || empty($input['loan_amount'])) {
        throw new Exception('Loan amount is required', 400);
    }
    if (!isset($input['interest_rate']) || empty($input['interest_rate'])) {
        throw new Exception('Interest rate is required', 400);
    }
    if (!isset($input['loan_tenure']) || empty($input['loan_tenure'])) {
        throw new Exception('Loan tenure is required', 400);
    }

    $lead_id = (int)$input['lead_id'];
    $sanctioned_date = $input['sanctioned_date'] ?? date('Y-m-d');
    $loan_amount = (float)$input['loan_amount'];
    $emi_type = $input['emi_type'] ?? 'Monthly';
    $interest_rate = (float)$input['interest_rate'];
    $loan_tenure = (int)$input['loan_tenure'];
    $emi = isset($input['emi']) ? (float)$input['emi'] : 0;
    $claim_status = $input['claim_status'] ?? 'not started';
    $disbursed = $input['disbursed'] ?? 'no';
    $sanction_letter = $input['sanction_letter'] ?? '';

    // Check if lead exists
    $checkStmt = $conn->prepare("SELECT lead_id FROM leads WHERE lead_id = ?");
    $checkStmt->bind_param('i', $lead_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        throw new Exception('Lead ID not found', 404);
    }
    $checkStmt->close();

    // Prevent duplicate sanctioned loan for same lead
    $dupStmt = $conn->prepare("SELECT sanctioned_id FROM sanctioned_loan WHERE lead_id = ? LIMIT 1");
    if (!$dupStmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
    }
    $dupStmt->bind_param('i', $lead_id);
    $dupStmt->execute();
    $dupResult = $dupStmt->get_result();
    if ($dupResult->num_rows > 0) {
        $dupStmt->close();
        throw new Exception('Sanctioned loan already exists for this Lead ID', 400);
    }
    $dupStmt->close();

    // Insert sanctioned loan
    $sql = "INSERT INTO sanctioned_loan 
            (lead_id, sanctioned_date, loan_amount, emi_type, interest_rate, loan_tenure, emi, claim_status, disbursed, sanction_letter) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('isdsdiisss', 
        $lead_id,           // i - integer
        $sanctioned_date,   // s - string (date)
        $loan_amount,       // d - double
        $emi_type,          // s - string
        $interest_rate,     // d - double
        $loan_tenure,       // i - integer
        $emi,               // i - integer (or d for double)
        $claim_status,      // s - string
        $disbursed,         // s - string
        $sanction_letter    // s - string
    );

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
    }

    $sanctioned_id = $conn->insert_id;

    $stmt->close();
    $conn->close();
    
    // Clear any buffered output and send JSON
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sanctioned loan created successfully',
        'sanctioned_id' => $sanctioned_id
    ]);

} catch (Exception $e) {
    // Clear any buffered output
    ob_end_clean();
    
    // Log the error
    error_log("Create Sanctioned Loan Exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    // Catch fatal errors
    ob_end_clean();
    
    // Log the error
    error_log("Create Sanctioned Loan Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>

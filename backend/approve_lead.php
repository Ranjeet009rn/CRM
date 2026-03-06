<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS Configuration
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'https://ingavalebusinesssolution.in',
    'http://ingavalebusinesssolution.in'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Allow all origins for now (you can restrict this later)
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';

$response = ['success' => false, 'message' => ''];

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed', 405);
    }

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || empty($input['id'])) {
        throw new Exception('Lead ID is required', 400);
    }

    $leadId = (int)$input['id'];
    
    // Debug: Log the lead ID being processed
    error_log("Approving lead ID: " . $leadId);
    
    // First check if lead exists at all
    $existsStmt = $conn->prepare("SELECT lead_id, is_approved FROM leads WHERE lead_id = ?");
    $existsStmt->bind_param('i', $leadId);
    $existsStmt->execute();
    $existsResult = $existsStmt->get_result();
    $leadInfo = $existsResult->fetch_assoc();
    
    if (!$leadInfo) {
        throw new Exception("Lead ID $leadId does not exist", 404);
    }
    
    error_log("Lead $leadId exists with is_approved = " . ($leadInfo['is_approved'] ?? 'NULL'));
    
    // Check if lead is already approved
    if ($leadInfo['is_approved'] == 1) {
        throw new Exception("Lead ID $leadId is already approved", 400);
    }

    // Approve the lead
    $approveStmt = $conn->prepare("UPDATE leads SET is_approved = 1 WHERE lead_id = ?");
    $approveStmt->bind_param('i', $leadId);
    $approveStmt->execute();

    if ($approveStmt->affected_rows === 0) {
        throw new Exception('Failed to approve lead', 500);
    }
    
    // Auto-add to sanctioned_loan table when approved
    // Get lead details first
    error_log("Fetching lead data for sanctioned_loan insertion");
    $leadStmt = $conn->prepare("SELECT name, phone, loan_amount, emi_frequency, interest_rate, loan_tenure, emi FROM leads WHERE lead_id = ?");
    $leadStmt->bind_param('i', $leadId);
    $leadStmt->execute();
    $leadResult = $leadStmt->get_result();
    $leadData = $leadResult->fetch_assoc();
    
    error_log("Lead data: " . json_encode($leadData));
    
    if ($leadData && $leadData['loan_amount']) {
        $customerName = $leadData['name'] ?? 'Unknown';
        $phone = $leadData['phone'] ?? '';
        $loanAmount = $leadData['loan_amount'];
        $emiType = $leadData['emi_frequency'] ?? 'Monthly';
        $interestRate = $leadData['interest_rate'] ?? 10;
        $loanTenure = $leadData['loan_tenure'] ?? 12;
        $emi = $leadData['emi'] ?? 0;
        
        // Insert into sanctioned_loan table with minimal required fields
        $sanctionSql = "INSERT INTO sanctioned_loan 
                       (lead_id, sanctioned_date, loan_amount, emi_type, interest_rate, loan_tenure, emi, claim_status, disbursed) 
                       VALUES (?, CURDATE(), ?, 'Monthly', 10, 12, 0, 'not started', 'No')
                       ON DUPLICATE KEY UPDATE 
                       loan_amount = VALUES(loan_amount),
                       sanctioned_date = CURDATE()";
        
        $sanctionStmt = $conn->prepare($sanctionSql);
        if ($sanctionStmt) {
            error_log("Inserting into sanctioned_loan table");
            $sanctionStmt->bind_param("id", 
                $leadId, 
                $loanAmount
            );
            
            if ($sanctionStmt->execute()) {
                error_log("Successfully inserted into sanctioned_loan table");
            } else {
                error_log("Failed to insert into sanctioned_loan: " . $sanctionStmt->error);
            }
            $sanctionStmt->close();
        } else {
            error_log("Failed to prepare sanctioned_loan statement: " . $conn->error);
        }
    }
    
    if (isset($leadStmt)) $leadStmt->close();

    $response = [
        'success' => true,
        'message' => 'Lead approved successfully'
    ];
    
    http_response_code(200);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    header('Content-Type: application/json');
    echo json_encode($response);
    
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($approveStmt)) $approveStmt->close();
    $conn->close();
}
?>
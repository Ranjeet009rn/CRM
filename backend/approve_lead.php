<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS Configuration
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
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
    
    // Check if lead exists and is unapproved
    $checkStmt = $conn->prepare("SELECT id FROM leads WHERE id = ? AND is_approved = 0");
    $checkStmt->bind_param('i', $leadId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Lead not found or already approved', 404);
    }

    // Approve the lead
    $approveStmt = $conn->prepare("UPDATE leads SET is_approved = 1, approved_at = NOW() WHERE id = ?");
    $approveStmt->bind_param('i', $leadId);
    $approveStmt->execute();

    if ($approveStmt->affected_rows === 0) {
        throw new Exception('Failed to approve lead', 500);
    }

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
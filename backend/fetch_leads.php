<?php
// Enable error reporting (remove in production)
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
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Start output buffering to catch any unexpected output
ob_start();

require_once 'db.php';

$response = ['success' => false, 'message' => ''];

try {
    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    // Prepare and execute query
    $sql = "SELECT * FROM leads ORDER BY created_at DESC";
    
    // Using prepared statement even for SELECT as best practice
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query preparation failed', 500);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leads = [];
    while ($row = $result->fetch_assoc()) {
        // Sanitize output if needed
        $leads[] = $row;
    }
    
    $response = [
        'success' => true,
        'leads' => $leads
    ];
    
    http_response_code(200);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
} finally {
    // Clean buffers and send response
    ob_end_clean();
    echo json_encode($response);
    
    // Close connections
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
?>
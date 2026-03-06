<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'db.php';
    
    // Get all lead IDs and basic info
    $result = $conn->query("SELECT lead_id, name, email, phone, father_name, dob, address FROM leads ORDER BY lead_id LIMIT 5");
    $leads = [];
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "leads" => $leads,
        "count" => count($leads)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>

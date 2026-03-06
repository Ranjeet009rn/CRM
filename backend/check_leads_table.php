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
    
    // Get table structure
    $result = $conn->query("DESCRIBE leads");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    
    // Get sample data from first lead
    $sampleResult = $conn->query("SELECT * FROM leads LIMIT 1");
    $sampleData = $sampleResult->fetch_assoc();
    
    // Count total leads
    $countResult = $conn->query("SELECT COUNT(*) as total FROM leads");
    $totalLeads = $countResult->fetch_assoc()['total'];
    
    echo json_encode([
        "success" => true,
        "table_structure" => $columns,
        "sample_data" => $sampleData,
        "total_leads" => $totalLeads,
        "sample_data_keys" => $sampleData ? array_keys($sampleData) : [],
        "column_count" => count($columns)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>

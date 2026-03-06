<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'db.php';
    
    $results = [];
    
    // 1. Check if created_by column exists
    $result = $conn->query("SHOW COLUMNS FROM leads LIKE 'created_by'");
    $createdByExists = $result->num_rows > 0;
    $results['created_by_exists'] = $createdByExists;
    
    // 2. If created_by doesn't exist, add it
    if (!$createdByExists) {
        $conn->query("ALTER TABLE leads ADD COLUMN created_by VARCHAR(255) DEFAULT NULL AFTER assigned_to");
        $results['created_by_added'] = true;
    }
    
    // 3. Check if username column exists
    $result = $conn->query("SHOW COLUMNS FROM leads LIKE 'username'");
    $usernameExists = $result->num_rows > 0;
    $results['username_exists'] = $usernameExists;
    
    // 4. If username doesn't exist, add it
    if (!$usernameExists) {
        $conn->query("ALTER TABLE leads ADD COLUMN username VARCHAR(255) DEFAULT NULL AFTER created_by");
        $results['username_added'] = true;
    }
    
    // 5. Update existing leads with creator information
    // For leads that have assigned_to but no created_by, we'll set created_by to 'admin'
    $updateQuery = "UPDATE leads SET created_by = 'admin' WHERE created_by IS NULL OR created_by = ''";
    $conn->query($updateQuery);
    $results['updated_existing_leads'] = $conn->affected_rows;
    
    // 6. Get sample of updated data
    $sampleResult = $conn->query("SELECT lead_id, name, assigned_to, created_by, username FROM leads LIMIT 5");
    $sampleData = [];
    while ($row = $sampleResult->fetch_assoc()) {
        $sampleData[] = $row;
    }
    $results['sample_data'] = $sampleData;
    
    // 7. Get column structure
    $columnsResult = $conn->query("SHOW COLUMNS FROM leads");
    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $results['all_columns'] = $columns;
    
    echo json_encode([
        "success" => true,
        "message" => "Database structure updated successfully",
        "results" => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, return JSON instead

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
    // DB connection
    require_once 'db.php';

    // Validate input
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("Valid ID required", 400);
    }

    $id = intval($_GET['id']);

    // Prepare and execute query - use lead_id as primary key
    $stmt = $conn->prepare("SELECT * FROM leads WHERE lead_id = ?");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error, 500);
    }
    
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error, 500);
    }
    
    $result = $stmt->get_result();

    if ($lead = $result->fetch_assoc()) {
        // Debug: Log the fetched lead data
        error_log("=== FETCH LEAD BY ID DEBUG ===");
        error_log("Lead ID requested: " . $id);
        error_log("Lead data fetched: " . json_encode($lead));
        error_log("Number of fields: " . count($lead));
        error_log("Field names: " . implode(", ", array_keys($lead)));
        error_log("===============================");
        
        // Normalize: add 'id' field if not present
        if (!isset($lead['id']) && isset($lead['lead_id'])) {
            $lead['id'] = $lead['lead_id'];
        }
        
        // Clean up any null values and convert to proper format
        foreach ($lead as $key => $value) {
            if ($value === null || $value === '') {
                $lead[$key] = null; // Ensure consistent null representation
            }
        }
        
        echo json_encode(["success" => true, "lead" => $lead]);
    } else {
        error_log("No lead found with ID: " . $id);
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Lead not found"]);
    }

    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        "success" => false, 
        "message" => $e->getMessage()
    ]);
}
?>

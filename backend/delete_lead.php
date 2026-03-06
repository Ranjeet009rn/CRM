<?php
// DEBUG: Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Read input from multiple sources
$leadId = null;

// Try JSON input first
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("delete_lead.php - JSON decode error: " . json_last_error_msg());
}

// Try GET parameter (check both 'id' and 'lead_id')
if (isset($_GET['id'])) {
    $leadId = intval($_GET['id']);
} elseif (isset($_GET['lead_id'])) {
    $leadId = intval($_GET['lead_id']);
}
// Try POST parameter
elseif (isset($_POST['id'])) {
    $leadId = intval($_POST['id']);
} elseif (isset($_POST['lead_id'])) {
    $leadId = intval($_POST['lead_id']);
}
// Try JSON data
elseif ($data && isset($data["id"])) {
    $leadId = intval($data["id"]);
} elseif ($data && isset($data["lead_id"])) {
    $leadId = intval($data["lead_id"]);
}

// DEBUG: Log what we received
error_log("delete_lead.php - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("delete_lead.php - GET: " . print_r($_GET, true));
error_log("delete_lead.php - POST: " . print_r($_POST, true));
error_log("delete_lead.php - Raw input: " . $rawInput);
error_log("delete_lead.php - Final leadId: " . $leadId);

// Validate input
if (!$leadId || $leadId <= 0) {
    http_response_code(400);
    $errorResponse = [
        "success" => false, 
        "message" => "Valid Lead ID not provided. Received ID: " . var_export($leadId, true),
        "debug" => [
            "method" => $_SERVER['REQUEST_METHOD'],
            "get_params" => $_GET,
            "post_params" => $_POST,
            "json_data" => $data,
            "raw_input" => $rawInput,
            "final_id" => $leadId
        ]
    ];
    error_log("delete_lead.php - VALIDATION FAILED: " . json_encode($errorResponse));
    echo json_encode($errorResponse);
    exit;
}

// DB connection
require_once 'db.php';  // Use your central db.php file

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Prepare and execute delete query - use lead_id column
$stmt = $conn->prepare("DELETE FROM leads WHERE lead_id = ?");
$stmt->bind_param("i", $leadId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Lead deleted successfully"]);
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Lead not found with ID: " . $leadId]);
    }
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to delete lead: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

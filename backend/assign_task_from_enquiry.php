<?php
// Suppress warnings and notices that might interfere with JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Check database connection
if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$enquiryId = $input['enquiryId'] ?? '';
$assignedTo = $input['assignedTo'] ?? '';
$priority = $input['priority'] ?? 'medium';
$description = $input['description'] ?? '';

if (empty($enquiryId) || empty($assignedTo)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enquiry ID and assigned employee are required"]);
    exit();
}

// Get enquiry details
try {
    $stmt = $conn->prepare("SELECT * FROM enquiry WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare enquiry query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $enquiryId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Enquiry not found"]);
        exit();
    }

    $enquiry = $result->fetch_assoc();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    exit();
}

// Create task subject from enquiry
$subject = "Follow up on enquiry from " . $enquiry['first_name'] . " " . $enquiry['last_name'];

// Create task description from enquiry details
$taskDescription = "Enquiry Details:\n";
$taskDescription .= "Name: " . $enquiry['first_name'] . " " . $enquiry['last_name'] . "\n";
$taskDescription .= "Email: " . $enquiry['email'] . "\n";
$taskDescription .= "Phone: " . ($enquiry['phone'] ?? 'Not provided') . "\n";
$taskDescription .= "Company: " . ($enquiry['company'] ?? 'Not provided') . "\n";
$taskDescription .= "Message: " . $enquiry['message'] . "\n\n";
$taskDescription .= "Additional Notes: " . $description;

// Set default dates
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+7 days')); // Default 7 days from now

// Insert task into database
try {
    $stmt = $conn->prepare("
        INSERT INTO tasks 
        (subject, assigned_to, priority, recurrence, status, start_date, end_date, description, attachment) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare task insert query: " . $conn->error);
    }

    $stmt->bind_param("sssssssss", $subject, $assignedTo, $priority, 'none', 'pending', $startDate, $endDate, $taskDescription, null);

    if ($stmt->execute()) {
        $taskId = $stmt->insert_id;
        
        echo json_encode([
            "success" => true, 
            "message" => "Task assigned successfully",
            "taskId" => $taskId
        ]);
    } else {
        throw new Exception("Failed to execute task insert: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to assign task: " . $e->getMessage()]);
    exit();
}
?> 
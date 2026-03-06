<?php
// Set headers first to ensure proper content type
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') 
{ http_response_code(200);
  exit;
} 

// Use central DB configuration
require_once __DIR__ . '/db.php';

// Initialize response array
$response = [
    "success" => false,
    "message" => ""
];

try {
    // Ensure connection from db.php is valid
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Connection not initialized'));
    }

    // Get the posted data
    $data = json_decode(file_get_contents("php://input"));

    // Validate the input
    if ($data === null || !isset($data->id) || empty($data->id)) {
        throw new Exception("Invalid request data: Enquiry ID is required");
    }

    $id = (int)$data->id;

    // Prepare and execute the delete statement
    $stmt = $conn->prepare("DELETE FROM enquiry WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $executed = $stmt->execute();

    if (!$executed) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        $response["success"] = true;
        $response["message"] = "Enquiry deleted successfully";
    } else {
        $response["message"] = "No enquiry found with that ID";
    }

} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = $e->getMessage();
}

// Ensure resources are closed regardless of outcome
if (isset($stmt) && $stmt instanceof mysqli_stmt) { $stmt->close(); }
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }

// Ensure we only output JSON
echo json_encode($response);
exit();
?>
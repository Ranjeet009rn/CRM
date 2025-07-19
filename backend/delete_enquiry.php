<?php
// Set headers first to ensure proper content type
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "crm";

// Initialize response array
$response = [
    "success" => false,
    "message" => ""
];

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
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

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    $response["message"] = $e->getMessage();
}

// Ensure we only output JSON
echo json_encode($response);
exit();
?>
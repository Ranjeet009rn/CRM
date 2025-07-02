<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "CRM");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Handle request body
$data = json_decode(file_get_contents("php://input"), true);
$username = $data["username"] ?? null;
$isEmployee = $data["isEmployee"] ?? false;

if (!$conn) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

// Fetch customers based on user type
if ($isEmployee && $username) {
    // Only customers where the lead's assigned_to = this username
    $stmt = $conn->prepare("
        SELECT c.* 
        FROM customer c 
        JOIN leads l ON c.lead_id = l.id 
        WHERE l.assigned_to = ?
    ");
    $stmt->bind_param("s", $username);
} else {
    // Admin fetch: get all customers
    $stmt = $conn->prepare("SELECT * FROM customer");
}

$customers = [];
if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    echo json_encode(["success" => true, "customers" => $customers]);
} else {
    echo json_encode(["success" => false, "error" => "Query failed"]);
}

$stmt->close();
$conn->close();
?>

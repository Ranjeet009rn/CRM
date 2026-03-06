<?php
// Minimal working version of save_lead.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
if (!$input || !isset($input['name']) || !isset($input['phone']) || !isset($input['email'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

// Extract basic values
$name = $input['name'];
$email = $input['email'];
$phone = $input['phone'];
$source = $input['source'] ?? '';
$status = $input['status'] ?? 'New';
$assignedTo = $input['assignedTo'] ?? '';
$address = $input['address'] ?? '';
$cd_date = $input['cd_date'] ?? date('Y-m-d');
$loan_reason = $input['loan_reason'] ?? '';
$loan_amount = $input['loan_amount'] ?? '';
$emi = $input['emi'] ?? '';
$emi_frequency = $input['emi_frequency'] ?? 'Monthly';
$interest_rate = $input['interest_rate'] ?? '';
$loan_tenure = $input['loan_tenure'] ?? null;

// Simple INSERT with only essential fields
$sql = "INSERT INTO leads 
        (name, email, phone, source, status, assigned_to, address, cd_date, loan_reason, loan_amount, emi, emi_frequency, interest_rate, loan_tenure) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["error" => "Prepare failed: " . $conn->error]);
    exit();
}

$stmt->bind_param("ssssssssssssss", 
    $name, $email, $phone, $source, $status, $assignedTo, $address, $cd_date, 
    $loan_reason, $loan_amount, $emi, $emi_frequency, $interest_rate, $loan_tenure
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $conn->insert_id]);
} else {
    echo json_encode(["error" => "Execute failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

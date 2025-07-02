<?php
// CORS HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require_once 'db.php';

// Read incoming JSON data
$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
if (
    !$input ||
    !isset($input['name']) ||
    !isset($input['phone']) ||
    !isset($input['email']) ||
    !isset($input['source']) ||
    !isset($input['status']) ||
    !isset($input['assignedTo']) ||
    !isset($input['address']) ||
    !isset($input['cd_date']) ||
    !isset($input['loan_reason']) ||
    !isset($input['loan_amount']) ||
    !isset($input['emi']) ||
    !isset($input['interest_rate'])
) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit();
}

// Extract values
$name           = $input['name'];
$email          = $input['email'];
$phone          = $input['phone'];
$source         = $input['source'];
$status         = $input['status'];
$assignedTo     = $input['assignedTo'];
$notes          = $input['notes'] ?? '';
$label          = $input['label'] ?? '';
$reference      = $input['reference'] ?? '';
$address        = $input['address'];
$cd_date        = $input['cd_date'];
$loan_reason    = $input['loan_reason'];
$loan_amount    = $input['loan_amount'];
$emi            = $input['emi'];
$interest_rate  = $input['interest_rate'];

// Insert query
$stmt = $conn->prepare("
    INSERT INTO leads 
    (name, email, phone, source, status, assigned_to, notes, label, reference, address, cd_date, loan_reason, loan_amount, emi, interest_rate)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssssssssssssss",
    $name,
    $email,
    $phone,
    $source,
    $status,
    $assignedTo,
    $notes,
    $label,
    $reference,
    $address,
    $cd_date,
    $loan_reason,
    $loan_amount,
    $emi,
    $interest_rate
);

// Execute and respond
if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>

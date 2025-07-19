<?php
require 'db.php';

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

// Get username
$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(["success" => false, "message" => "Username is required"]);
    exit;
}

// Prepare SQL query
$query = "SELECT * FROM leads WHERE assigned_to = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "SQL error: " . $conn->error]);
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}

echo json_encode(["success" => true, "leads" => $leads]);
?>

<?php
require 'db.php';

// Set headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

// Get username from query param
$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(["success" => false, "message" => "Username is required"]);
    exit;
}

// Prepare and execute query to fetch only assigned leads
$query = "SELECT * FROM leads WHERE assigned_to = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// Fetch and return results
$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}

echo json_encode(["success" => true, "leads" => $leads]);
?>

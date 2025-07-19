<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/db.php';

$sql = "SELECT id, first_name, last_name, email, phone, message, created_at FROM enquiry ORDER BY created_at DESC";
$result = $conn->query($sql);

$enquiries = [];
while ($row = $result->fetch_assoc()) {
    $enquiries[] = $row;
}

echo json_encode(['success' => true, 'data' => $enquiries]);
?>

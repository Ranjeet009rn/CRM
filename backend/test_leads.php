<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

// Get all leads with their status
$sql = "SELECT lead_id, name, status, assigned_to, created_at FROM leads ORDER BY created_at DESC LIMIT 20";
$result = $conn->query($sql);

$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}

// Count by status
$statusCount = [];
$statusSql = "SELECT status, COUNT(*) as count FROM leads GROUP BY status";
$statusResult = $conn->query($statusSql);
while ($row = $statusResult->fetch_assoc()) {
    $statusCount[$row['status']] = $row['count'];
}

echo json_encode([
    'success' => true,
    'total_leads' => count($leads),
    'leads' => $leads,
    'status_counts' => $statusCount
], JSON_PRETTY_PRINT);

$conn->close();
?>

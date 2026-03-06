<?php
// Check leads table data for debugging
require_once 'db.php';

header('Content-Type: application/json');

try {
    // Check if created_by column exists
    $result = $conn->query("SHOW COLUMNS FROM leads LIKE 'created_by'");
    $hasCreatedBy = $result && $result->num_rows > 0;

    // Fetch all leads with relevant fields
    $sql = "SELECT lead_id, name, phone, created_by, assigned_to, scrutinized_by, status, created_at 
            FROM leads 
            ORDER BY created_at DESC 
            LIMIT 10";
    $result = $conn->query($sql);

    $leads = [];
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }

    echo json_encode([
        'success' => true,
        'has_created_by_column' => $hasCreatedBy,
        'leads' => $leads,
        'message' => 'Debug info for leads table'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
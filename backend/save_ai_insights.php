<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$leadId = isset($input['lead_id']) ? intval($input['lead_id']) : 0;
$insights = isset($input['insights']) ? trim($input['insights']) : '';

if ($leadId <= 0 || $insights === '') {
    echo json_encode([ 'success' => false, 'error' => 'Invalid input' ]);
    exit;
}

// Ensure table exists (idempotent)
$createSql = "CREATE TABLE IF NOT EXISTS lead_ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL UNIQUE,
    insights LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

// Save or update insights (prevent duplicates by lead)
$stmt = $conn->prepare("INSERT INTO lead_ai_insights (lead_id, insights) VALUES (?, ?) ON DUPLICATE KEY UPDATE insights = VALUES(insights)");
if (!$stmt) {
    echo json_encode([ 'success' => false, 'error' => 'Prepare failed: ' . $conn->error ]);
    exit;
}
$stmt->bind_param('is', $leadId, $insights);
if (!$stmt->execute()) {
    echo json_encode([ 'success' => false, 'error' => 'Execute failed: ' . $stmt->error ]);
    exit;
}

echo json_encode([ 'success' => true ]);
?>



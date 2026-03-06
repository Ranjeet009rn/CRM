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
if ($leadId <= 0) {
    echo json_encode([ 'success' => false, 'error' => 'Invalid lead_id' ]);
    exit;
}

$createSql = "CREATE TABLE IF NOT EXISTS lead_ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL UNIQUE,
    insights LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

// Nothing to change for lock: presence of a row means saved/locked in UI
// Return whether exists
$stmt = $conn->prepare("SELECT 1 FROM lead_ai_insights WHERE lead_id = ?");
if (!$stmt) {
    echo json_encode([ 'success' => false, 'error' => 'Prepare failed: ' . $conn->error ]);
    exit;
}
$stmt->bind_param('i', $leadId);
$stmt->execute();
$res = $stmt->get_result();
$exists = $res->fetch_row() !== null;

echo json_encode([ 'success' => true, 'locked' => $exists ]);
?>



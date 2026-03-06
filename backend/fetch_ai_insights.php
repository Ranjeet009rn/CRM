<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$leadId = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
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

$stmt = $conn->prepare("SELECT insights FROM lead_ai_insights WHERE lead_id = ?");
if (!$stmt) {
    echo json_encode([ 'success' => false, 'error' => 'Prepare failed: ' . $conn->error ]);
    exit;
}
$stmt->bind_param('i', $leadId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if ($row) {
    echo json_encode([ 'success' => true, 'insights' => $row['insights'] ]);
} else {
    echo json_encode([ 'success' => true, 'insights' => null ]);
}
?>



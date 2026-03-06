<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    $leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;

    if ($leadId <= 0) {
        throw new Exception('lead_id is required', 400);
    }

    $sql = "SELECT fm.id,
                   fm.lead_id,
                   fm.movement_date,
                   fm.sent_to,
                   fm.status,
                   fm.remark,
                   fm.sanction_date,
                   fm.created_by,
                   l.name AS lead_name
            FROM file_movements fm
            LEFT JOIN leads l ON fm.lead_id = l.lead_id
            WHERE fm.lead_id = ?
            ORDER BY fm.movement_date DESC, fm.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data'    => $rows,
    ]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

<?php
// Fetch all leads that have ever been assigned to a specific employee (history)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    $username = isset($_GET['username']) ? trim($_GET['username']) : '';

    if ($username === '') {
        throw new Exception('Username required', 400);
    }

    // Get all assignments (current + historical) for this employee
    $sql = "SELECT 
                la.id AS assignment_id,
                la.lead_id,
                la.employee_username,
                la.assigned_by,
                la.assigned_at,
                la.unassigned_at,
                la.employee_role,
                la.notes,
                l.*
            FROM lead_assignments la
            INNER JOIN leads l ON la.lead_id = l.lead_id
            WHERE la.employee_username = ?
            ORDER BY la.assigned_at DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query preparation failed', 500);
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    $seenLeads = [];
    while ($row = $result->fetch_assoc()) {
        // Normalize id
        if (isset($row['lead_id']) && !isset($row['id'])) {
            $row['id'] = $row['lead_id'];
        }

        $leadKey = (string)$row['lead_id'];

        // Results are ordered by assigned_at DESC, so first time we see a lead_id
        // it's the most recent assignment for this employee. Skip any duplicates.
        if (isset($seenLeads[$leadKey])) {
            continue;
        }

        $seenLeads[$leadKey] = true;
        $history[] = $row;
    }

    $response['success'] = true;
    $response['history'] = $history;
    http_response_code(200);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response['success'] = false;
    $response['message'] = $e->getMessage();
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
    echo json_encode($response);
}

?>

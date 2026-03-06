<?php
// Lead statistics grouped by Mahamandal / Social Category and Status
// Returns counts like: [{ social_category: 'APVM', status: 'Sanctioned', count: 5 }, ...]

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Basic CORS (same as other endpoints)
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

ob_start();
require_once 'db.php';

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    // Optional filters: category + date range
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $from     = isset($_GET['from']) ? trim($_GET['from']) : '';
    $to       = isset($_GET['to']) ? trim($_GET['to']) : '';

    // Build base query
    $sql = "SELECT 
                COALESCE(NULLIF(social_category, ''), 'Unspecified') AS social_category,
                COALESCE(NULLIF(status, ''), 'Unknown') AS status,
                COUNT(*) AS lead_count
            FROM leads";

    $params = [];
    $types  = '';
    $where  = [];

    if ($category !== '') {
        $where[]  = "social_category = ?";
        $params[] = $category;
        $types   .= 's';
    }

    // Apply date range if both from & to present
    if ($from !== '' && $to !== '') {
        // Filter strictly by created_at date so report period is based on lead creation
        $where[]  = "DATE(created_at) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types   .= 'ss';
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " GROUP BY social_category, status
              ORDER BY social_category, status";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database query preparation failed: ' . $conn->error, 500);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = [
            'social_category' => $row['social_category'],
            'status'          => $row['status'],
            'count'           => (int)$row['lead_count'],
        ];
    }

    $response = [
        'success' => true,
        'stats'   => $stats,
    ];
    http_response_code(200);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
    ];
} finally {
    ob_end_clean();
    echo json_encode($response);
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}

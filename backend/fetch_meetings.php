<?php
// Production-safe JSON-only output
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ob_start();
$response = ['success' => false];

try {
    require_once 'db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    // Validate inputs
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';

    // Optional limit for dashboard / listing (default 100, max 1000)
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    if ($limit <= 0) {
        $limit = 100;
    }
    if ($limit > 1000) {
        $limit = 1000;
    }

    if ($user_type === 'admin') {
        $sql = "SELECT * FROM meetings WHERE user_type = 'admin' ORDER BY date DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
        $stmt->bind_param('i', $limit);
    } else {
        $sql = "SELECT * FROM meetings WHERE user_id = ? AND user_type = 'employee' ORDER BY date DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error, 500);
        }
        $stmt->bind_param("ii", $user_id, $limit);
    }

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
    }
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
    }

    $result = $stmt->get_result();
    $meetings = [];
    while ($row = $result->fetch_assoc()) {
        $meetings[] = $row;
    }

    http_response_code(200);
    $response = ['success' => true, 'meetings' => $meetings];
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response = ['success' => false, 'error' => $e->getMessage()];
} finally {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($response);
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

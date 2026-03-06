<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';

try {
    if (!isset($conn) || $conn->connect_error) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "DB connection failed"]);
        return;
    }

    // Get user info
    $userInfo = getUserFromRequest();
    $role = $userInfo['role'];
    $username = $userInfo['username'];

    // Build query based on role
    if (canViewAllData($role)) {
        // Admin sees all payments
        $sql = "SELECT p.*, 
                e.username AS emp_name, 
                c.name AS customer_name
                FROM payments p
                LEFT JOIN employee e ON p.emp_id = e.id
                LEFT JOIN customer c ON p.customer_id = c.id
                ORDER BY p.id ASC";
        $stmt = $conn->prepare($sql);
    } else if (!empty($username)) {
        // Non-admin users see only payments for customers from their own leads
        $sql = "SELECT p.*, 
                e.username AS emp_name, 
                c.name AS customer_name
                FROM payments p
                LEFT JOIN employee e ON p.emp_id = e.id
                LEFT JOIN customer c ON p.customer_id = c.id
                LEFT JOIN leads l ON c.lead_id = l.lead_id
                WHERE l.created_by = ? OR l.assigned_to = ?
                ORDER BY p.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
    } else {
        throw new Exception("Username required for non-admin users");
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch payments: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }

    echo json_encode(["success" => true, "payments" => $payments]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->close();
    }
}

<?php
// Disable HTML error display and ensure JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method Not Allowed"]);
    exit();
}

try {
    require_once 'db.php';
    require_once 'permissions.php';

    // Get user info
    $userInfo = getUserFromRequest();
    $role = $userInfo['role'] ?: ($_GET['user_type'] ?? '');
    $username = $userInfo['username'];

    // Optional limit for dashboard / listing (default 100, max 1000)
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    if ($limit <= 0) {
        $limit = 100;
    }
    if ($limit > 1000) {
        $limit = 1000;
    }

    // Build query based on user role with RBAC
    if (canViewAllData($role)) {
        // Admin sees all tasks (no auto-expiry by due_date)
        $sql = "SELECT * FROM tasks 
                ORDER BY id DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param('i', $limit);
    } else if (!empty($username)) {
        // Non-admin users see only tasks they created OR tasks assigned to them (no auto-expiry)
        $sql = "SELECT * FROM tasks 
                WHERE (created_by = ? OR assigned_to = ?)
                ORDER BY id DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param('ssi', $username, $username, $limit);
    } else {
        // Fallback: show all tasks if no username provided (backward compatibility, no auto-expiry)
        $sql = "SELECT * FROM tasks 
                ORDER BY id DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param('i', $limit);
    }
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $tasks = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row = array_map(function ($val) {
                return is_string($val) ? trim($val) : $val;
            }, $row);
            $tasks[] = $row;
        }
    }

    echo json_encode(["success" => true, "tasks" => $tasks]);
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("fetch_tasks.php error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        "success" => false,
        "error" => "Server error: " . $e->getMessage(),
        "file" => basename($e->getFile()),
        "line" => $e->getLine()
    ]);
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>
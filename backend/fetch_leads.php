<?php
// Production-safe error settings: never echo HTML errors
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// CORS Configuration
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
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Start output buffering to catch any unexpected output
ob_start();

$response = ['success' => false, 'message' => ''];

try {
    require_once 'db.php';
    require_once 'permissions.php';

    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method Not Allowed', 405);
    }

    // Get user info from query parameters
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
        // Admin sees all leads
        $sql = "SELECT * FROM leads ORDER BY created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database query preparation failed', 500);
        }
        $stmt->bind_param('i', $limit);
    } else if (!empty($username)) {
        // Sales Officers, Bank Agents, Employees - see only their own created leads OR assigned leads
        $sql = "SELECT * FROM leads WHERE created_by = ? OR assigned_to = ? OR scrutinized_by = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database query preparation failed', 500);
        }
        $stmt->bind_param('sssi', $username, $username, $username, $limit);
    } else {
        // No username provided for non-admin
        throw new Exception('Username required', 400);
    }

    if (!$stmt) {
        throw new Exception('Database query preparation failed', 500);
    }

    if (!$stmt->execute()) {
        throw new Exception('Database execute failed: ' . $stmt->error, 500);
    }
    $result = $stmt->get_result();

    $leads = [];
    while ($row = $result->fetch_assoc()) {
        // Normalize: if lead_id exists but id doesn't, copy lead_id to id
        if (isset($row['lead_id']) && !isset($row['id'])) {
            $row['id'] = $row['lead_id'];
        }

        // Add creator info based on available fields and intelligent inference
        $creator = 'Unknown';

        // 1) If DB already has an explicit creator_name, trust it fully
        if (!empty($row['creator_name'])) {
            $creator = $row['creator_name'];

            // 2) Otherwise, if created_by is set (including 'admin'), use it
        } elseif (!empty($row['created_by'])) {
            $creator = $row['created_by'];

            // 3) Fallbacks only when there is no explicit creator in DB
        } elseif (!empty($row['username'])) {
            $creator = $row['username'];
        } elseif (!empty($row['emp_id'])) {
            $creator = $row['emp_id'];
        } elseif (!empty($row['user_id'])) {
            $creator = $row['user_id'];
        } elseif (!empty($row['added_by'])) {
            $creator = $row['added_by'];
        } elseif (!empty($row['creator'])) {
            $creator = $row['creator'];
        }

        // 4) Intelligent inference: use ONLY when we still don't know creator at all
        if ($creator === 'Unknown') {
            $source = $row['source'] ?? '';
            $assigned_to = $row['assigned_to'] ?? '';

            // If source suggests employee creation and assigned to same employee
            if (
                in_array($source, ['LeadForm', 'Website', 'Advertisement']) &&
                !empty($assigned_to) &&
                $assigned_to !== 'admin'
            ) {
                $creator = $assigned_to; // Likely self-created
            } else {
                $creator = 'admin'; // Default to admin for unclear cases
            }
        }

        $row['creator_name'] = $creator;

        // Debug logging removed - creator attribution fixed

        $leads[] = $row;
    }

    $response = [
        'success' => true,
        'leads' => $leads
    ];

    http_response_code(200);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    error_log("fetch_leads.php error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
} finally {
    // Flush any buffered output and send JSON only
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($response);
    // Close resources
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
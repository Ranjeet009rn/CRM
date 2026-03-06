<?php
// Suppress all errors and warnings to prevent breaking JSON output
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Database connection (use centralized credentials)
require_once __DIR__ . '/db.php';
// $conn is provided by db.php; fail fast if missing
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "DB connection not initialized"]);
    exit;
}

// Handle preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Get request data (support both GET and POST)
$username = null;
$isEmployee = false;
$customerId = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() === JSON_ERROR_NONE && $data) {
        $username = $data["username"] ?? null;
        $isEmployee = $data["isEmployee"] ?? false;
        $customerId = isset($data["customer_id"]) ? (int) $data["customer_id"] : null;
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "GET") {
    // For GET requests, just return all customers (for dropdowns, etc.)
    $username = $_GET["username"] ?? null;
    $isEmployee = isset($_GET["isEmployee"]) ? (bool) $_GET["isEmployee"] : false;
    $customerId = isset($_GET["customer_id"]) ? (int) $_GET["customer_id"] : null;
}

// Check database connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

try {
    // If a specific customer is requested (edit mode), return only that record
    if ($customerId) {
        if ($isEmployee && $username) {
            $stmt = $conn->prepare("
                SELECT c.* 
                FROM customer c 
                JOIN leads l ON c.lead_id = l.lead_id 
                WHERE c.id = ? AND l.assigned_to = ? 
                LIMIT 1
            ");
            if (!$stmt)
                throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("is", $customerId, $username);
        } else {
            $stmt = $conn->prepare("SELECT * FROM customer WHERE id = ? LIMIT 1");
            if (!$stmt)
                throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("i", $customerId);
        }

        if (!$stmt->execute())
            throw new Exception("Query execution failed: " . $stmt->error);

        $customer = null;
        if (method_exists($stmt, 'get_result')) {
            $result = $stmt->get_result();
            if ($result) {
                $row = $result->fetch_assoc();
                if ($row) {
                    if (isset($row['created_at'])) {
                        $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
                    }
                    $customer = $row;
                }
            }
        } else {
            // Fallback without mysqlnd
            $meta = $stmt->result_metadata();
            if ($meta) {
                $fields = [];
                $row = [];
                while ($field = $meta->fetch_field()) {
                    $row[$field->name] = null;
                    $fields[] = &$row[$field->name];
                }
                call_user_func_array([$stmt, 'bind_result'], $fields);
                if ($stmt->fetch()) {
                    $assoc = [];
                    foreach ($row as $key => $val) {
                        if ($key === 'created_at' && $val)
                            $assoc[$key] = date('Y-m-d H:i:s', strtotime($val));
                        else
                            $assoc[$key] = $val;
                    }
                    $customer = $assoc;
                }
            }
        }

        echo json_encode(["success" => true, "customer" => $customer]);
        exit;
    }

    // Otherwise: list customers based on LEADS so that every lead appears as a customer row
    require_once 'permissions.php';

    // Get user info
    $userInfo = getUserFromRequest();
    $role = $userInfo['role'] ?: ($isEmployee ? 'employee' : 'admin');
    $actualUsername = $userInfo['username'] ?: $username;

    if (!canViewAllData($role) && $actualUsername) {
        // For non-admin users: only leads they created OR assigned to them, with any matching customer data
        $sql = "
            SELECT 
                COALESCE(c.id, 0)              AS id,
                l.lead_id                       AS lead_id,
                COALESCE(c.name, l.name)       AS name,
                COALESCE(c.email, l.email)     AS email,
                COALESCE(c.phone, l.phone)     AS phone,
                COALESCE(c.address, l.address) AS address,
                COALESCE(c.loan_reason, l.loan_reason)         AS loan_reason,
                COALESCE(c.loan_amount, l.loan_amount)         AS loan_amount,
                COALESCE(c.interest_rate, l.interest_rate)     AS interest_rate,
                COALESCE(c.emi, l.emi)                         AS emi,
                COALESCE(c.created_at, l.created_at)           AS created_at
            FROM leads l
            LEFT JOIN customer c ON c.lead_id = l.lead_id
            WHERE l.created_by = ? OR l.assigned_to = ?
            ORDER BY COALESCE(c.created_at, l.created_at) DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $actualUsername, $actualUsername);
    } else {
        // For admin: fetch ALL rows directly from customer table
        $sql = "
            SELECT 
                id,
                lead_id,
                name,
                email,
                phone,
                address,
                loan_reason,
                loan_amount,
                interest_rate,
                emi,
                created_at
            FROM customer
            ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
    }

    $customers = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Format dates for consistent output
            if (isset($row['created_at'])) {
                $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
            }
            $customers[] = $row;
        }
        echo json_encode([
            "success" => true,
            "customers" => $customers,
            "count" => count($customers)
        ]);
    } else {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} finally {
    // Clean up
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?>
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Database connection
$conn = new mysqli("localhost", "root", "", "CRM");

// Handle preflight request
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Validate request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

// Get and validate request data
$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid JSON data"]);
    exit;
}

$username = $data["username"] ?? null;
$isEmployee = $data["isEmployee"] ?? false;

// Check database connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

try {
    // Fetch customers based on user type
    if ($isEmployee && $username) {
        // For employees: only their assigned customers, newest first
        $stmt = $conn->prepare("
            SELECT c.* 
            FROM customer c 
            JOIN leads l ON c.lead_id = l.id 
            WHERE l.assigned_to = ?
            ORDER BY c.created_at DESC
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $username);
    } else {
        // For admin: all customers, newest first
        $stmt = $conn->prepare("SELECT * FROM customer ORDER BY created_at DESC");
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
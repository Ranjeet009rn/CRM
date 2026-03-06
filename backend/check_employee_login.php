<?php
// ===== CORS Headers =====
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// ===== Handle preflight request =====
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== Include DB connection =====
require_once 'db.php'; // Assumes $conn is your DB connection

// ===== Get and decode request body =====
$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');
$role     = trim($data['role'] ?? ''); // ✅ Role added

if (empty($username) || empty($password) || empty($role)) {
    echo json_encode(["success" => false, "error" => "Missing username, password, or role"]);
    exit();
}

// ===== Validate employee login with case-insensitive role =====
$stmt = $conn->prepare("SELECT id, username, role, sub_role FROM employee WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Check if role matches (case-insensitive)
    if (strtolower($user['role']) === strtolower($role)) {
        echo json_encode([
            "success" => true,
            "user_id" => $user['id'],
            "username" => $user['username'],
            "role" => $user['role'],
            "sub_role" => ($user['sub_role'] ?? '')
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Role mismatch. User role: " . $user['role']]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Invalid username or password"]);
}

$stmt->close();
$conn->close();
?>

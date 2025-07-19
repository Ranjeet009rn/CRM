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

// ===== Validate employee login with role =====
$stmt = $conn->prepare("SELECT id, username FROM employee WHERE username = ? AND password = ? AND role = ?");
$stmt->bind_param("sss", $username, $password, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "user_id" => $user['id'],
        "username" => $user['username']
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Invalid credentials or role"]);
}

$stmt->close();
$conn->close();
?>

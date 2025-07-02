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

if (empty($username) || empty($password)) {
    echo json_encode(["success" => false, "error" => "Missing username or password"]);
    exit();
}

// ===== Validate employee login =====
$stmt = $conn->prepare("SELECT id, username FROM employee WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $username, $password);
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
    echo json_encode(["success" => false, "error" => "Invalid credentials"]);
}

$stmt->close();
$conn->close();
?>

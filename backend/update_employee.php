<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Get input data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['id'])) {
    echo json_encode(["success" => false, "error" => "Employee ID is required"]);
    exit();
}

$id = $data['id'];
$username = $data['username'] ?? null;
$password = $data['password'] ?? null;
$email = $data['email'] ?? null;
$mobile = $data['mobile'] ?? null;

// Validate at least one field is being updated
if ($username === null && $password === null && $email === null && $mobile === null) {
    echo json_encode(["success" => false, "error" => "No fields to update"]);
    exit();
}

// Prepare the update query dynamically based on provided fields
$updates = [];
$params = [];
$types = '';

if ($username !== null) {
    // Validate username (only letters)
    if (!preg_match('/^[A-Za-z]+$/', $username)) {
        echo json_encode(["success" => false, "error" => "Username can only contain letters"]);
        exit();
    }
    $updates[] = "username = ?";
    $params[] = $username;
    $types .= 's';
}

if ($password !== null) {
    // Validate password strength
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        echo json_encode(["success" => false, "error" => "Password must be at least 8 characters with uppercase, lowercase, number and special character"]);
        exit();
    }
    $updates[] = "password = ?";
    $params[] = $password; // Store as plain text (not hashed)
    $types .= 's';
}

if ($email !== null) {
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "error" => "Invalid email format"]);
        exit();
    }
    $updates[] = "email = ?";
    $params[] = $email;
    $types .= 's';
}

if ($mobile !== null) {
    // Validate mobile number (10 digits)
    if (!preg_match('/^\d{10}$/', $mobile)) {
        echo json_encode(["success" => false, "error" => "Mobile must be 10 digits"]);
        exit();
    }
    $updates[] = "mobile = ?";
    $params[] = $mobile;
    $types .= 's';
}

// Add ID to params for WHERE clause
$params[] = $id;
$types .= 'i';

// Build the query
$query = "UPDATE employee SET " . implode(', ', $updates) . " WHERE id = ?";
$stmt = $conn->prepare($query);

// Bind parameters dynamically
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Employee updated successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => "No changes made or employee not found"]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Update failed: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>
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

// Input from frontend (form data)
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$mobile   = $_POST['mobile'] ?? '';
$email    = $_POST['email'] ?? '';
$role     = $_POST['role'] ?? ''; // Default role is "Employee" if not passed

// Handle up to 5 face images (base64)
$face_images = [];
for ($i = 1; $i <= 5; $i++) {
    $key = "face_image_$i";
    if (!empty($_POST[$key])) {
        $img_data = $_POST[$key];
        $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
        $img_data = str_replace(' ', '+', $img_data);
        $img_binary = base64_decode($img_data);
        $safe_email = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $email);
        $file_name = "face_{$safe_email}_{$i}.jpg";
        $file_path = __DIR__ . "/uploads/" . $file_name;
        file_put_contents($file_path, $img_binary);
        $face_images[] = $file_name;
    }
}
$face_images_str = implode(',', $face_images);

// Basic validation
if (!$username || !$password || !$mobile || !$email || !$role) {
    echo json_encode(["success" => false, "error" => "All fields are required"]);
    exit();
}

// Check if username or email already exists
$checkStmt = $conn->prepare("SELECT id FROM employee WHERE username = ? OR email = ?");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(["success" => false, "error" => "Username or email already exists"]);
    $checkStmt->close();
    $conn->close();
    exit();
}
$checkStmt->close();

// Store data with role and face images
$stmt = $conn->prepare("INSERT INTO employee (username, password, mobile, email, role, face_image) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $username, $password, $mobile, $email, $role, $face_images_str);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Insert failed"]);
}

$stmt->close();
$conn->close();

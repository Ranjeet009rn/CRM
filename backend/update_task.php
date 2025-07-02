<?php
// CORS HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once 'db.php';

// Validate ID
$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(["success" => false, "message" => "Task ID is required."]);
    exit;
}

// Fetch form data
$subject     = $_POST['subject'] ?? '';
$status      = $_POST['status'] ?? '';
$assigned_to = $_POST['assigned_to'] ?? '';
$priority    = $_POST['priority'] ?? '';
$recurrence  = $_POST['recurrence'] ?? '';
$start_date  = $_POST['start_date'] ?? '';
$end_date    = $_POST['end_date'] ?? '';
$description = $_POST['description'] ?? '';

// File upload handling
$attachment_name = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_tmp  = $_FILES['attachment']['tmp_name'];
    $file_name = basename($_FILES['attachment']['name']);
    $target_path = $upload_dir . $file_name;

    if (move_uploaded_file($file_tmp, $target_path)) {
        $attachment_name = $file_name;
    } else {
        echo json_encode(["success" => false, "message" => "File upload failed."]);
        exit;
    }
}

// Build SQL dynamically
$sql = "UPDATE tasks SET 
            subject = ?, 
            status = ?, 
            assigned_to = ?, 
            priority = ?, 
            recurrence = ?, 
            start_date = ?, 
            end_date = ?, 
            description = ?";

$params = [
    $subject, $status, $assigned_to, $priority,
    $recurrence, $start_date, $end_date, $description
];

$types = "ssssssss";

if ($attachment_name) {
    $sql .= ", attachment = ?";
    $params[] = $attachment_name;
    $types .= "s";
}

$sql .= " WHERE id = ?";
$params[] = $id;
$types .= "i";

// Prepare and bind
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "SQL prepare failed."]);
    exit;
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Task updated successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Task update failed."]);
}

$stmt->close();
$conn->close();
?>

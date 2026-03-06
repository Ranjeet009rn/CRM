<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Sanitize Inputs
$subject     = $_POST['subject'] ?? '';
$assignedTo  = $_POST['assignedTo'] ?? '';
$priority    = $_POST['priority'] ?? '';
$recurrence  = $_POST['recurrence'] ?? '';
$status      = $_POST['status'] ?? '';
$startDate   = $_POST['startDate'] ?? null; // currently unused in DB schema
$endDate     = $_POST['endDate'] ?? null;   // we will treat this as due_date
$description = $_POST['description'] ?? '';
$createdBy   = $_POST['createdBy'] ?? 'admin';
$filename    = null;

// Handle Attachment Upload
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $originalName = basename($_FILES['attachment']['name']);
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $filename = uniqid("task_") . '.' . $ext;  // Ensure unique file name
    $targetFile = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to upload file."]);
        exit();
    }
}

// Insert into Database using new tasks schema
// tasks: id, title, description, created_by, assigned_to, status, priority, due_date, image_path, created_at, updated_at
$stmt = $conn->prepare("
    INSERT INTO tasks 
    (title, description, created_by, assigned_to, status, priority, due_date, image_path) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database prepare error."]);
    exit();
}

$dueDate = $endDate; // map End Date field to due_date column
$title   = $subject;

$stmt->bind_param("ssssssss", $title, $description, $createdBy, $assignedTo, $status, $priority, $dueDate, $filename);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>

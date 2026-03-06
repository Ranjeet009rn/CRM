<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid JSON input"]);
    exit();
}

$id = isset($data->id) ? intval($data->id) : null;
$user_id = isset($data->user_id) ? intval($data->user_id) : null;
$user_type = isset($data->user_type) ? strtolower(trim($data->user_type)) : null;
$title = isset($data->title) ? trim($data->title) : '';
$content = isset($data->content) ? trim($data->content) : '';

if (!$id || !$user_id || !$user_type || !$title || !$content) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit();
}

if (strlen($title) < 3 || strlen($content) < 5) {
    echo json_encode(["success" => false, "error" => "Title or content too short"]);
    exit();
}

// Allow admin, employee, sales officer, and any other staff roles
$allowedTypes = ['admin', 'employee', 'sales officer', 'sales agent', 'agent', 'staff', 'manager', 'supervisor'];
$isAllowedType = in_array($user_type, $allowedTypes, true);
if (!$isAllowedType) {
    foreach (['employee', 'sales', 'agent', 'staff', 'manager'] as $kw) {
        if (strpos($user_type, $kw) !== false) {
            $isAllowedType = true;
            break;
        }
    }
}
if (!$isAllowedType) {
    echo json_encode(["success" => false, "error" => "Invalid user type"]);
    exit();
}

// Role-based access control
if ($user_type === 'admin') {
    // Admin can edit all notes (admin + employee)
    $sql = "UPDATE notes SET title = ?, content = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        exit();
    }
    $stmt->bind_param('ssi', $title, $content, $id);
} else {
    // Non-admin can only edit their own notes (any user_type)
    $sql = "UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
        exit();
    }
    $stmt->bind_param('ssii', $title, $content, $id, $user_id);
}

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true]);
    } else {
        // Debug: Check if note exists and why it wasn't updated
        $checkSql = "SELECT id, user_id, user_type, title, content FROM notes WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                "success" => false,
                "error" => "Note ID $id not found in database"
            ]);
        } else {
            $existingNote = $result->fetch_assoc();
            $reasons = [];

            if ($user_type === 'employee' && $existingNote['user_id'] != $user_id) {
                $reasons[] = "User ID mismatch (DB: {$existingNote['user_id']}, Sent: $user_id)";
            }
            if ($existingNote['user_type'] !== $user_type) {
                $reasons[] = "User type mismatch (DB: {$existingNote['user_type']}, Sent: $user_type)";
            }
            if ($existingNote['title'] === $title && $existingNote['content'] === $content) {
                $reasons[] = "No changes detected (title and content are identical)";
            }

            echo json_encode([
                "success" => false,
                "error" => "Update failed: " . implode(", ", $reasons)
            ]);
        }
        $checkStmt->close();
    }
} else {
    echo json_encode(["success" => false, "error" => "SQL Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();

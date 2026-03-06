<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

require_once __DIR__ . '/db.php';

$response = ['success' => false, 'error' => '', 'id' => null];

try {
  if (!isset($conn) || $conn->connect_error) {
    throw new Exception('DB connection failed');
  }

  // Support both JSON and multipart/form-data
  $title = '';
  $description = '';
  $created_by = '';
  $assigned_to = '';
  $status = 'pending';
  $priority = 'medium';
  $due_date = null;

  if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $created_by = trim($data['created_by'] ?? '');
    $assigned_to = trim($data['assigned_to'] ?? '');
    $status = $data['status'] ?? 'pending';
    $priority = $data['priority'] ?? 'medium';
    $due_date = $data['due_date'] ?? null;
  } else {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $created_by = trim($_POST['created_by'] ?? '');
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = $_POST['due_date'] ?? null;
  }

  if ($title === '' || $created_by === '') {
    throw new Exception('Title and created_by are required');
  }

  // Normalise enums
  $allowedStatus = ['pending','in_progress','completed'];
  if (!in_array($status, $allowedStatus, true)) $status = 'pending';
  $allowedPriority = ['low','medium','high'];
  if (!in_array($priority, $allowedPriority, true)) $priority = 'medium';

  $sql = "INSERT INTO tasks (title, description, created_by, assigned_to, status, priority, due_date) VALUES (?,?,?,?,?,?,?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

  $dueDateParam = $due_date ? $due_date : null;
  $stmt->bind_param('sssssss', $title, $description, $created_by, $assigned_to, $status, $priority, $dueDateParam);

  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }

  $taskId = $stmt->insert_id;
  $stmt->close();

  // Handle optional image upload (multipart only)
  $imagePath = null;
  if (!empty($_FILES['image']) && isset($_FILES['image']['error']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/uploads/tasks/' . $taskId;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

    $name = $_FILES['image']['name'];
    $tmp = $_FILES['image']['tmp_name'];
    $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($name));
    $dest = $uploadDir . '/' . time() . '_' . $safeName;

    if (@move_uploaded_file($tmp, $dest)) {
      $imagePath = 'backend/' . trim(str_replace(__DIR__ . '/', '', $dest), '/');
      $up = $conn->prepare('UPDATE tasks SET image_path = ? WHERE id = ?');
      if ($up) {
        $up->bind_param('si', $imagePath, $taskId);
        $up->execute();
        $up->close();
      }
    }
  }

  $response['success'] = true;
  $response['id'] = $taskId;
  $response['image_path'] = $imagePath;
} catch (Throwable $e) {
  http_response_code(500);
  $response['error'] = $e->getMessage();
} finally {
  if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); }
}

echo json_encode($response);

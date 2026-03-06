<?php
// JSON-only, production-safe endpoint
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

ob_start();
$response = ['success' => false];

try {
  require_once 'db.php';

  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    throw new Exception('Method Not Allowed', 405);
  }

  $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
  $user_type = $_GET['user_type'] ?? null;

  // Optional limit for dashboard / listing (default 100, max 1000)
  $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
  if ($limit <= 0) {
    $limit = 100;
  }
  if ($limit > 1000) {
    $limit = 1000;
  }

  if (!$user_type) {
    throw new Exception('Missing user_type', 400);
  }

  if ($user_type === 'admin') {
    // Admin: show ALL notes (both admin and employee)
    $sql = "SELECT * FROM notes ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error, 500);
    }
    $stmt->bind_param('i', $limit);
  } else {
    // Non-admin: fetch notes for this user_id regardless of user_type
    // This supports employees, sales officers, agents, and any other roles
    $sql = "SELECT * FROM notes WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error, 500);
    }
    if (!$user_id) {
      throw new Exception('Missing user_id', 400);
    }
    $stmt->bind_param("ii", $user_id, $limit);
  }

  if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error, 500);
  }
  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error, 500);
  }

  $result = $stmt->get_result();

  $notes = [];
  while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
  }

  http_response_code(200);
  $response = ['success' => true, 'notes' => $notes];
} catch (Exception $e) {
  http_response_code($e->getCode() ?: 500);
  $response = ['success' => false, 'error' => $e->getMessage()];
} finally {
  if (ob_get_length()) {
    ob_clean();
  }
  echo json_encode($response);
  if (isset($stmt) && $stmt) {
    $stmt->close();
  }
  if (isset($conn) && $conn) {
    $conn->close();
  }
}
?>
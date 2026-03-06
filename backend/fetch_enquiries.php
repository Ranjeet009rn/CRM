<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  echo json_encode(['ok' => true]);
  exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';

try {
  if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    return;
  }

  // Get user info
  $userInfo = getUserFromRequest();
  $role = $userInfo['role'];
  $username = $userInfo['username'];

  // Helper to check if a column exists on enquiry table
  $hasColumn = function (mysqli $conn, string $column): bool {
    $colEsc = $conn->real_escape_string($column);
    $dbRes = $conn->query("SELECT DATABASE() as db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow ? $conn->real_escape_string($dbRow['db']) : '';
    if (!$dbName)
      return false;
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . $dbName . "' AND TABLE_NAME='enquiry' AND COLUMN_NAME='" . $colEsc . "' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
      $res->free();
      return true;
    }
    if ($res) {
      $res->free();
    }
    return false;
  };

  // Base columns
  $columns = [
    'id',
    'first_name',
    'last_name',
    'email',
    'phone',
    'message',
    'created_at',
    'assigned_to',
    'status'
  ];

  // Optional created_by column (who generated enquiry)
  if ($hasColumn($conn, 'created_by')) {
    $columns[] = 'created_by';
  } else {
    $columns[] = "NULL AS created_by";
  }

  // Optional columns: prefer snake_case
  if ($hasColumn($conn, 'district')) {
    $columns[] = 'district';
  } else {
    $columns[] = 'NULL AS district';
  }
  if ($hasColumn($conn, 'loan_type')) {
    $columns[] = 'loan_type';
  } elseif ($hasColumn($conn, 'loanType')) {
    $columns[] = 'loanType';
  } else {
    $columns[] = 'NULL AS loan_type';
  }
  if ($hasColumn($conn, 'reference_through')) {
    $columns[] = 'reference_through';
  } elseif ($hasColumn($conn, 'referenceThrough')) {
    $columns[] = 'referenceThrough';
  } else {
    $columns[] = 'NULL AS reference_through';
  }
  // Optional amount column
  if ($hasColumn($conn, 'amount')) {
    $columns[] = 'amount';
  } else {
    $columns[] = 'NULL AS amount';
  }

  // Follow-up related columns
  if ($hasColumn($conn, 'followup_status')) {
    $columns[] = 'followup_status';
  } else {
    $columns[] = 'NULL AS followup_status';
  }
  if ($hasColumn($conn, 'reminder_type')) {
    $columns[] = 'reminder_type';
  } else {
    $columns[] = 'NULL AS reminder_type';
  }
  if ($hasColumn($conn, 'next_followup_date')) {
    $columns[] = 'next_followup_date';
  } else {
    $columns[] = 'NULL AS next_followup_date';
  }

  // Check if created_by column exists for filtering
  $hasCreatedBy = $hasColumn($conn, 'created_by');

  // Build SQL query with role-based filtering
  $selectClause = "SELECT " . implode(', ', $columns) . " FROM enquiry";

  if (canViewAllData($role)) {
    // Admin sees all enquiries
    $sql = $selectClause . " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
  } else if (!empty($username) && $hasCreatedBy) {
    // Non-admin users see only enquiries they created OR assigned to them (if created_by exists)
    $sql = $selectClause . " WHERE created_by = ? OR assigned_to = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("ss", $username, $username);
  } else if (!empty($username) && !$hasCreatedBy) {
    // Fallback: filter only by assigned_to if created_by doesn't exist
    $sql = $selectClause . " WHERE assigned_to = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("s", $username);
  } else {
    // If no username and not admin, show all (for backward compatibility)
    $sql = $selectClause . " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
  }

  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }

  $result = $stmt->get_result();
  if (!$result) {
    throw new Exception('Query failed: ' . $conn->error);
  }

  $enquiries = [];
  while ($row = $result->fetch_assoc()) {
    $enquiries[] = $row;
  }

  ob_end_clean();
  echo json_encode(['success' => true, 'data' => $enquiries]);
} catch (Throwable $e) {
  http_response_code(500);
  error_log("fetch_enquiries.php error: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  ob_end_clean();
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'file' => basename($e->getFile()),
    'line' => $e->getLine()
  ]);
} finally {
  if (isset($result) && $result instanceof mysqli_result) {
    @$result->free();
  }
  if (isset($conn) && $conn instanceof mysqli) {
    @$conn->close();
  }
}

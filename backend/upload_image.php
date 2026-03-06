<?php
// CORS + JSON headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  echo json_encode(['ok' => true]);
  exit;
}

// Convert warnings to JSON
set_error_handler(function($severity, $message, $file, $line){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>"PHP: $message at $file:$line"]);
  exit;
});

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
  }

  if (empty($_FILES['image']) || !isset($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'No image uploaded']);
    exit;
  }

  $title = isset($_POST['title']) ? trim($_POST['title']) : '';
  $customerId = isset($_POST['customer_id']) ? trim($_POST['customer_id']) : '';

  $file = $_FILES['image'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Upload error code: '.$file['error']]);
    exit;
  }

  if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'File too large (max 5 MB)']);
    exit;
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);
  $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
  if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid image type']);
    exit;
  }

  // Use /Images/ folder in document root (production) or local equivalent
  // Production: /home/u876295706/domains/crm.swift2ai.com/public_html/Images/
  // Localhost: F:/wamp64/www/CRM/CRM/Images/
  
  // Detect document root
  $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
  
  // For localhost with /CRM/CRM structure
  if (strpos($docRoot, 'wamp64') !== false || strpos($docRoot, 'xampp') !== false) {
    // Get the actual project root (F:/wamp64/www/CRM/CRM)
    $projectRoot = realpath(dirname(dirname(__DIR__)));
    $baseDir = $projectRoot . '/Images';
  } else {
    // Production: use document root + /Images
    $baseDir = $docRoot . '/Images';
  }
  
  if (!is_dir($baseDir)) { @mkdir($baseDir, 0777, true); }
  
  // Store all images directly in /Images/ folder (no customer subfolders)

  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
  if ($safeName === '') { $safeName = 'image'; }
  $filename = time() . '_' . $safeName . '.' . strtolower($ext ?: 'jpg');
  $target = $baseDir . '/' . $filename;

  if (!@move_uploaded_file($file['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to save file']);
    exit;
  }

  // Build absolute public URL for WhatsApp/Email
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  
  // Detect base path (for localhost with /CRM/CRM or production)
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
  $basePath = '';
  if (strpos($scriptName, '/CRM/CRM/') !== false) {
    $basePath = '/CRM/CRM';
  } elseif (strpos($scriptName, '/CRM/') !== false) {
    $basePath = '/CRM';
  }
  
  // Build the web-accessible path relative to document root
  // Example: Images/1762079198_Product.jpg (no customer subfolder)
  $relativePath = 'Images/' . $filename;
  
  // Full absolute URL for external access (WhatsApp, email, etc.)
  // Production: https://crm.swift2ai.com/Images/1762079198_Product.jpg
  // Localhost: http://localhost/CRM/CRM/Images/1762079198_Product.jpg
  $absoluteUrl = $scheme . '://' . $host . $basePath . '/' . $relativePath;
  
  // Relative URL for frontend display
  $relative = $relativePath;

  // Save to database
  require_once __DIR__ . '/db.php';
  
  $stmt = $conn->prepare("INSERT INTO images (customer_id, caption, file_name, stored_path, public_url, mime_type, file_size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
  $stmt->bind_param("isssssi", $customerId, $title, $filename, $target, $absoluteUrl, $mime, $file['size']);
  
  if ($stmt->execute()) {
    $imageId = $stmt->insert_id;
    $stmt->close();
    
    echo json_encode([
      'success' => true,
      'image_id' => $imageId,
      'title' => $title,
      'customer_id' => $customerId,
      'image_url' => $relative,
      'image_absolute_url' => $absoluteUrl,
      'filename' => $filename,
      'stored_path' => $target
    ]);
  } else {
    $stmt->close();
    echo json_encode([
      'success' => true,
      'warning' => 'File uploaded but DB insert failed',
      'title' => $title,
      'customer_id' => $customerId,
      'image_url' => $relative,
      'image_absolute_url' => $absoluteUrl,
      'filename' => $filename,
      'stored_path' => $target
    ]);
  }
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
  exit;
} finally {
  if (isset($stmt) && $stmt instanceof mysqli_stmt) { @$stmt->close(); }
  if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); }
}
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

if (!isset($conn) || $conn->connect_error) {
  echo json_encode(['success' => false, 'error' => 'DB connection failed']);
  exit();
}

// Basic input from multipart/form-data
// Expected: form-data with lead_id and multiple files (keys matching leads table fields)

header('Content-Type: application/json; charset=utf-8');

try {
  // Validate method
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
  }

  // lead_id can come as lead_id or id (optional).
  // When called from an existing Lead Details flow we may receive a lead_id
  // and want to attach documents to that lead. When called from the
  // standalone ThirdPartyClaims form, lead_id will be absent and we should
  // simply skip this block and create a new external lead below.
  $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
  if ($lead_id <= 0 && isset($_POST['id'])) {
    $lead_id = intval($_POST['id']);
  }

  if ($lead_id > 0) {
    // Target directory relative to this PHP file (backend/)
    $targetDir = __DIR__ . '/../uploads/leads/' . $lead_id . '/';
    if (!is_dir($targetDir)) {
      @mkdir($targetDir, 0755, true);
    }

    // Allowed document keys we will persist to leads table
    $allowedKeys = [
      'sanction_letter',
      'loan_statement',
      'emi_schedule',
      'cancel_cheque',
      'udyam_aadhar',
      'business_photo',
      'project_report',
      'rc_book',
      'other_docs',
      'aadhar_card',
      'aadhaar_copy',
      'pan_copy',
      'photo',
      'signature'
    ];

    $saved = [];

    // Normalize and move uploaded files
    foreach ($_FILES as $field => $fileInfo) {
      if (!in_array($field, $allowedKeys, true)) {
        continue; // ignore unknown fields
      }
      if (!isset($fileInfo['error'])) continue;

      // Multiple files not expected for these keys; handle single upload
      if ($fileInfo['error'] === UPLOAD_ERR_OK && is_uploaded_file($fileInfo['tmp_name'])) {
        $orig = $fileInfo['name'];
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
        $filename = time() . '_' . $field . '_' . $safeBase . ($ext ? ('.' . $ext) : '');
        $destPath = $targetDir . $filename;

        if (move_uploaded_file($fileInfo['tmp_name'], $destPath)) {
          // Relative path to be stored in DB used by frontend
          $relative = 'backend/uploads/leads/' . $lead_id . '/' . $filename;
          $saved[$field] = $relative;
        }
      }
    }

    if (!empty($saved)) {
      // Build dynamic update for leads table
      $sets = [];
      $types = '';
      $vals = [];
      foreach ($saved as $col => $val) {
        $sets[] = "$col = ?";
        $types .= 's';
        $vals[] = $val;
      }
      $types .= 'i';
      $vals[] = $lead_id;
      $sql = 'UPDATE leads SET ' . implode(',', $sets) . ' WHERE lead_id = ? OR id = ?';
      // Bind twice to cover both columns; some DBs may only have one
      $types2 = $types . 'i';
      $vals2 = $vals;
      $vals2[] = $lead_id;

      // Try update by lead_id; if no rows, try id
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        // For simplicity bind using call_user_func_array pattern
        // But mysqli doesn't support direct array unpack here in older PHP; we can bind manually
        $stmt->bind_param($types2, ...$vals2);
        $stmt->execute();
        $stmt->close();
      }
    }
  }
  // Do NOT exit here – continue to create external lead + sanctioned_loan entry below
} catch (Exception $e) {
  // Log error but continue; main flow below may still run
  error_log('Third-party document upload error: ' . $e->getMessage());
}
$mahamandal_name = $_POST['mahamandal_name'] ?? '';
$customer_name   = $_POST['customer_name'] ?? '';
$mobile          = $_POST['mobile'] ?? '';
$email           = $_POST['email'] ?? '';
$login_id        = $_POST['login_id'] ?? '';
$password        = $_POST['password'] ?? '';
$business_name   = $_POST['business_name'] ?? '';
$bank_name       = $_POST['bank_name'] ?? '';
$loan_amount     = isset($_POST['loan_amount']) ? floatval($_POST['loan_amount']) : 0;
$emi_type        = $_POST['emi_type'] ?? 'Monthly';
$interest_rate   = $_POST['interest_rate'] ?? '';
$loan_tenure     = isset($_POST['loan_tenure']) ? intval($_POST['loan_tenure']) : 0;

if (!$customer_name || !$mobile || !$email || !$bank_name || $loan_amount <= 0 || !$interest_rate || $loan_tenure <= 0) {
  echo json_encode(['success' => false, 'error' => 'Missing or invalid required fields']);
  $conn->close();
  exit();
}

// Calculate EMI based on amount, rate and tenure
// Same logic as frontend LeadForm EMI calculation
$principal   = $loan_amount;
$annualRate  = floatval($interest_rate);
$months      = $loan_tenure;

// Periods per year depending on EMI type
$periodsPerYear = 12; // Monthly
if (strcasecmp($emi_type, 'Quarterly') === 0) {
  $periodsPerYear = 4;
} elseif (strcasecmp($emi_type, 'Yearly') === 0) {
  $periodsPerYear = 1;
}

$emi = 0.0;
if ($principal > 0 && $annualRate > 0 && $months > 0) {
  $periodRate = ($annualRate / $periodsPerYear) / 100.0;
  $nPeriods   = max(1, round(($months * $periodsPerYear) / 12.0));

  if ($periodRate > 0) {
    $emiCalc = ($principal * $periodRate * pow(1 + $periodRate, $nPeriods)) /
               (pow(1 + $periodRate, $nPeriods) - 1);
    $emi = round($emiCalc, 2);
  }
}

// Ensure required columns exist on leads
$conn->query("ALTER TABLE leads ADD COLUMN IF NOT EXISTS is_external TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE leads ADD COLUMN IF NOT EXISTS mahamandal_login_id VARCHAR(100) NULL");
$conn->query("ALTER TABLE leads ADD COLUMN IF NOT EXISTS mahamandal_password VARCHAR(100) NULL");

// Create lead as external
// social_category = selected Mahamandal / Category, emi = calculated EMI
// We keep address empty so that customer view does not show business name as address.
$leadSql = "INSERT INTO leads (name, email, phone, source, status, address, loan_amount, loan_tenure, emi, interest_rate, loan_reason, is_external, social_category, mahamandal_login_id, mahamandal_password)
            VALUES (?, ?, ?, 'Third-Party', 'Sanctioned', ?, ?, ?, ?, ?, 'Third-Party Claim', 1, ?, ?, ?)";

if (!($stmt = $conn->prepare($leadSql))) {
  echo json_encode(['success' => false, 'error' => 'Prepare failed: '.$conn->error]);
  $conn->close();
  exit();
}

$dummyAddress = '';
$tenure = $loan_tenure;
// name (s), email (s), phone (s), address (s), loan_amount (d), loan_tenure (i), emi (d), interest_rate (s), social_category (s), login_id (s), password (s)
$stmt->bind_param('ssssiddssss', $customer_name, $email, $mobile, $dummyAddress, $loan_amount, $tenure, $emi, $interest_rate, $mahamandal_name, $login_id, $password);

if (!$stmt->execute()) {
  echo json_encode(['success' => false, 'error' => 'Failed to insert lead: '.$stmt->error]);
  $stmt->close();
  $conn->close();
  exit();
}

$lead_id = $stmt->insert_id;
$stmt->close();

// === Auto-create / update customer row for this external lead ===
// This ensures Third-Party clients appear as real customers, so that
// payments can link via customer_id and show names in the Payment table.
try {
  // Ensure customer table exists with at least lead_id, name, email, phone
  $conn->query("CREATE TABLE IF NOT EXISTS customer (
      id INT AUTO_INCREMENT PRIMARY KEY,
      lead_id INT NOT NULL,
      name VARCHAR(255) NULL,
      email VARCHAR(255) NULL,
      phone VARCHAR(50) NULL,
      address VARCHAR(255) NULL,
      loan_reason VARCHAR(255) NULL,
      loan_amount DECIMAL(15,2) NULL,
      interest_rate VARCHAR(50) NULL,
      emi DECIMAL(15,2) NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  // Check if a customer already exists for this lead
  if ($check = $conn->prepare("SELECT id FROM customer WHERE lead_id = ? LIMIT 1")) {
    $check->bind_param('i', $lead_id);
    if ($check->execute()) {
      $checkRes = $check->get_result();
      if ($checkRes && $checkRes->num_rows > 0) {
        // Update existing customer with latest data
        $row = $checkRes->fetch_assoc();
        $custId = (int)$row['id'];
        if ($upd = $conn->prepare("UPDATE customer SET name = ?, email = ?, phone = ?, address = ?, loan_reason = ?, loan_amount = ?, interest_rate = ?, emi = ? WHERE id = ?")) {
          $addr = '';
          $loanReason = 'Third-Party Claim';
          $upd->bind_param(
            'sssssdssi',
            $customer_name,
            $email,
            $mobile,
            $addr,
            $loanReason,
            $loan_amount,
            $interest_rate,
            $emi,
            $custId
          );
          $upd->execute();
          $upd->close();
        }
      } else {
        // Insert new customer row for this external lead
        if ($ins = $conn->prepare("INSERT INTO customer (lead_id, name, email, phone, address, loan_reason, loan_amount, interest_rate, emi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
          $addr = '';
          $loanReason = 'Third-Party Claim';
          $ins->bind_param(
            'isssssssd',
            $lead_id,
            $customer_name,
            $email,
            $mobile,
            $addr,
            $loanReason,
            $loan_amount,
            $interest_rate,
            $emi
          );
          $ins->execute();
          $ins->close();
        }
      }
      if ($checkRes) { $checkRes->free(); }
    }
    $check->close();
  }
} catch (Exception $ex) {
  // Do not block main flow if customer sync fails
  error_log('Third-party customer sync error: ' . $ex->getMessage());
}

// Ensure sanction_letter_status and bank_name columns exist on sanctioned_loan
$conn->query("ALTER TABLE sanctioned_loan ADD COLUMN IF NOT EXISTS sanction_letter_status VARCHAR(50) NULL");
$conn->query("ALTER TABLE sanctioned_loan ADD COLUMN IF NOT EXISTS bank_name VARCHAR(150) NULL");

// Create sanctioned_loan entry directly (approved sanction letter)
// Use same EMI, interest and tenure details and store bank_name
$sanSql = "INSERT INTO sanctioned_loan (lead_id, sanctioned_date, loan_amount, bank_name, emi_type, interest_rate, loan_tenure, emi, claim_status, disbursed, sanction_letter_status)
           VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, 'not started', 'no', 'Approved')";

if (!($sstmt = $conn->prepare($sanSql))) {
  echo json_encode(['success' => false, 'error' => 'Prepare failed: '.$conn->error]);
  $conn->close();
  exit();
}

// lead_id (i), loan_amount (d), bank_name (s), emi_type (s), interest_rate (s), loan_tenure (i), emi (d)
$sstmt->bind_param('idsssid', $lead_id, $loan_amount, $bank_name, $emi_type, $interest_rate, $tenure, $emi);

if (!$sstmt->execute()) {
  echo json_encode(['success' => false, 'error' => 'Failed to insert sanctioned loan: '.$sstmt->error]);
  $sstmt->close();
  $conn->close();
  exit();
}

$sanctioned_id = $sstmt->insert_id;
$sstmt->close();

// Handle document uploads into dedicated folder
$uploadDir = __DIR__ . '/uploads/third_party_claims/' . $sanctioned_id;
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0777, true);
}

$docKeys = [
  'sanction_letter',
  'loan_statement',
  'emi_schedule',
  'cancel_cheque',
  'udyam_aadhar',
  'business_photo',
  'project_report',
  'rc_book',
  'other_docs',
  'aadhar_card',
];

$storedPaths = [];

foreach ($docKeys as $key) {
  if (!isset($_FILES['files']['name'][$key]) || !$_FILES['files']['tmp_name'][$key]) {
    continue;
  }
  $name = $_FILES['files']['name'][$key];
  $tmp  = $_FILES['files']['tmp_name'][$key];

  $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($name));
  $target = $uploadDir . '/' . time() . '_' . $safeName;
  if (@move_uploaded_file($tmp, $target)) {
    $relPath = 'backend/' . trim(str_replace(__DIR__ . '/', '', $target), '/');
    $storedPaths[$key] = $relPath;
  }
}

// Store uploaded docs on sanctioned_loan AND also mirror paths on leads table
foreach ($storedPaths as $key => $path) {
  // Ensure column on sanctioned_loan
  $colCheck = $conn->query("SHOW COLUMNS FROM sanctioned_loan LIKE '".$conn->real_escape_string($key)."'");
  if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE sanctioned_loan ADD COLUMN `".$conn->real_escape_string($key)."` VARCHAR(255) NULL");
  }
  if ($colCheck) { $colCheck->free(); }

  $updSql = "UPDATE sanctioned_loan SET `$key` = ? WHERE sanctioned_id = ?";
  if ($u = $conn->prepare($updSql)) {
    $u->bind_param('si', $path, $sanctioned_id);
    $u->execute();
    $u->close();
  }

  // Also ensure corresponding column on leads table and update it so Lead Details modal can show it
  $leadColCheck = $conn->query("SHOW COLUMNS FROM leads LIKE '".$conn->real_escape_string($key)."'");
  if ($leadColCheck && $leadColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE leads ADD COLUMN `".$conn->real_escape_string($key)."` VARCHAR(255) NULL");
  }
  if ($leadColCheck) { $leadColCheck->free(); }

  $leadUpdSql = "UPDATE leads SET `$key` = ? WHERE lead_id = ?";
  if ($lu = $conn->prepare($leadUpdSql)) {
    $lu->bind_param('si', $path, $lead_id);
    $lu->execute();
    $lu->close();
  }
}

echo json_encode(['success' => true, 'lead_id' => $lead_id, 'sanctioned_id' => $sanctioned_id]);
$conn->close();

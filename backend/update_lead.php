<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); ob_end_clean(); echo json_encode(["ok"=>true]); exit; }

// Convert warnings/notices into JSON error
set_error_handler(function($severity, $message, $file, $line){
  http_response_code(500);
  $payload = ["success"=>false, "error"=>"PHP: $message at $file:$line"];
  ob_end_clean(); echo json_encode($payload); exit;
});

set_exception_handler(function($ex){
  http_response_code(500);
  $payload = ["success"=>false, "error"=>"EXCEPTION: ".$ex->getMessage()];
  if (ob_get_level()) { ob_end_clean(); }
  echo json_encode($payload); exit;
});

register_shutdown_function(function(){
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    http_response_code(500);
    $payload = ["success"=>false, "error"=>"FATAL: {$err['message']} at {$err['file']}:{$err['line']}"];
    if (ob_get_level()) { ob_end_clean(); }
    echo json_encode($payload);
  }
});

$data = null;
if (!empty($_POST['payload'])) {
  $data = json_decode($_POST['payload'], true);
} elseif (!empty($_POST)) {
  // Accept flat form fields too
  $data = $_POST;
} else {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw, true);
}

if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(400);
  $payload = ["success"=>false, "error"=>"Invalid JSON payload"];
  ob_end_clean(); echo json_encode($payload); exit;
}

if (!$data || !isset($data['id'])) {
  http_response_code(400);
  $payload = ["success" => false, "error" => "Missing lead ID"];
  ob_end_clean(); echo json_encode($payload); exit;
}

// Use db.php for database connection
require_once 'db.php';

// Core fields
$id = (int)$data['id'];
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$source = $data['source'] ?? '';
$status = $data['status'] ?? '';
$assignedTo = $data['assignedTo'] ?? '';
$address = $data['address'] ?? '';
$current_address = $data['current_address'] ?? null;
$permanent_address = $data['permanent_address'] ?? null;
// New granular address fields
$city = $data['city'] ?? null;
$state = $data['state'] ?? null;
$pincode = $data['pincode'] ?? null;
$father_name = $data['father_name'] ?? null;
$dob = $data['dob'] ?? null;
$age = isset($data['age']) ? (string)(int)$data['age'] : null;
$marital_status = $data['marital_status'] ?? null;
$education = $data['education'] ?? null;
$occupation = $data['occupation'] ?? null;
$monthly_income = $data['monthly_income'] ?? null;
$other_income_source = $data['other_income_source'] ?? null;
$cd_date = $data['cd_date'] ?? '';
$loan_reason = $data['loan_reason'] ?? '';
$loan_amount = $data['loan_amount'] ?? '';
$loan_tenure = $data['loan_tenure'] ?? null;
$emi = $data['emi'] ?? '';
$emi_frequency = $data['emi_frequency'] ?? 'Monthly';
$interest_rate = $data['interest_rate'] ?? '';
$cancel_reason = $data['cancel_reason'] ?? null;

// Guarantor & References
$guarantor_name  = $data['guarantor_name'] ?? null;
$guarantor_phone = $data['guarantor_phone'] ?? null;
$reference1_name = $data['reference1_name'] ?? null;
$reference1_phone= $data['reference1_phone'] ?? null;

// New LeadForm fields
$caste = $data['caste'] ?? null;
$religion = $data['religion'] ?? null;
$social_category = $data['social_category'] ?? null;
$alternate_phone = $data['alternate_phone'] ?? null;
$ration_card_type = $data['ration_card_type'] ?? null;
$mahamandal_login_id = $data['mahamandal_login_id'] ?? null;
$mahamandal_password = $data['mahamandal_password'] ?? null;

$account_holder_name = $data['account_holder_name'] ?? null;
$project_amount = $data['project_amount'] ?? null;
$cibil_score = isset($data['cibil_score']) ? $data['cibil_score'] : null;
$has_previous_loan = !empty($data['has_previous_loan']) ? 1 : 0;
$previous_loan_amount = $data['previous_loan_amount'] ?? null;

$lic_policy_no = $data['lic_policy_no'] ?? null;
$lic_sum_assured = $data['lic_sum_assured'] ?? null;
$lic_premium = $data['lic_premium'] ?? null;

$satbara_no = $data['satbara_no'] ?? null;
$survey_gat_no = $data['survey_gat_no'] ?? null;
$village = $data['village'] ?? null;
$taluka = $data['taluka'] ?? null;
$district = $data['district'] ?? null;

$has_income_certificate = !empty($data['has_income_certificate']) ? 1 : 0;
$has_business_bill = !empty($data['has_business_bill']) ? 1 : 0;
$has_aadhaar_copy = !empty($data['has_aadhaar_copy']) ? 1 : 0;
$has_bank_passbook = !empty($data['has_bank_passbook']) ? 1 : 0;

$sql = "UPDATE leads SET
  name=?, email=?, phone=?, source=?, status=?, assigned_to=?, address=?, current_address=?, permanent_address=?, city=?, state=?, pincode=?, father_name=?, dob=?, age=?, marital_status=?, education=?, occupation=?, monthly_income=?, other_income_source=?, cd_date=?, loan_reason=?, loan_amount=?, loan_tenure=?, emi=?, emi_frequency=?, interest_rate=?, guarantor_name=?, guarantor_phone=?, reference1_name=?, reference1_phone=?, cancel_reason=?,
  caste=?, religion=?, social_category=?, alternate_phone=?, ration_card_type=?, mahamandal_login_id=?, mahamandal_password=?,
  account_holder_name=?, project_amount=?, cibil_score=?, has_previous_loan=?, previous_loan_amount=?,
  lic_policy_no=?, lic_sum_assured=?, lic_premium=?,
  satbara_no=?, survey_gat_no=?, village=?, taluka=?, district=?,
  has_income_certificate=?, has_business_bill=?, has_aadhaar_copy=?, has_bank_passbook=?
  WHERE lead_id=?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  $payload = ["success" => false, "error" => "Prepare failed: " . $conn->error];
  $conn->close();
  ob_end_clean(); echo json_encode($payload); exit;
}

// Types: first 42 strings (added other_income_source), then has_previous_loan (i), next 9 strings, then 4 ints (flags), then id (i)
$types = str_repeat('s', 42) . 'i' . str_repeat('s', 9) . str_repeat('i', 5);

$stmt->bind_param(
  $types,
  $name, $email, $phone, $source, $status, $assignedTo, $address, $current_address, $permanent_address, $city, $state, $pincode, $father_name, $dob, $age, $marital_status, $education, $occupation, $monthly_income, $other_income_source, $cd_date, $loan_reason, $loan_amount, $loan_tenure, $emi, $emi_frequency, $interest_rate, $guarantor_name, $guarantor_phone, $reference1_name, $reference1_phone, $cancel_reason,
  $caste, $religion, $social_category, $alternate_phone, $ration_card_type, $mahamandal_login_id, $mahamandal_password,
  $account_holder_name, $project_amount, $cibil_score, $has_previous_loan, $previous_loan_amount,
  $lic_policy_no, $lic_sum_assured, $lic_premium,
  $satbara_no, $survey_gat_no, $village, $taluka, $district,
  $has_income_certificate, $has_business_bill, $has_aadhaar_copy, $has_bank_passbook,
  $id
);

if ($stmt->execute()) {
  // Auto-save to sanctioned_loan table if status is "Sanctioned"
  if ($status === 'Sanctioned') {
    // Use the already extracted $emi_frequency variable
    $sanction_sql = "INSERT INTO sanctioned_loan 
                     (lead_id, sanctioned_date, loan_amount, emi_type, interest_rate, loan_tenure, emi, claim_status, disbursed) 
                     VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'not started', 'no')
                     ON DUPLICATE KEY UPDATE 
                     loan_amount = VALUES(loan_amount),
                     emi_type = VALUES(emi_type),
                     interest_rate = VALUES(interest_rate),
                     loan_tenure = VALUES(loan_tenure),
                     emi = VALUES(emi),
                     sanctioned_date = CURDATE(),
                     updated_at = CURRENT_TIMESTAMP";
    
    $sanction_stmt = $conn->prepare($sanction_sql);
    if ($sanction_stmt) {
      $sanction_stmt->bind_param("idssis", $id, $loan_amount, $emi_frequency, $interest_rate, $loan_tenure, $emi);
      $sanction_stmt->execute();
      $sanction_stmt->close();
    }
  }
  
  // Handle uploaded files if any (FormData path)
  if (!empty($_FILES['files']) && isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
    $uploadDir = __DIR__ . '/uploads/leads/' . $id;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

    $allowedKeys = [
      'aadhaar_copy','pan_copy','photo','signature','voter_id','driving_license',
      'income_certificate','salary_slip','form16','itr_ack','bank_statement','passbook',
      'udyam_udhyog','gst_certificate','shop_act','roc','partnership_deed','moa_aoa','business_bill','quotation','project_report',
      'electricity_bill','rent_agreement',
      'satbara_7_12','property_map','sale_deed','noc','valuation_report',
      'lic_policy_copy',
      'guarantor_aadhaar','guarantor_pan','guarantor_photo'
    ];

    // Ensure columns exist
    foreach ($allowedKeys as $col) {
      $check = $conn->query("SHOW COLUMNS FROM leads LIKE '" . $conn->real_escape_string($col) . "'");
      if ($check && $check->num_rows === 0) {
        @$conn->query("ALTER TABLE leads ADD COLUMN `$col` VARCHAR(255) NULL");
      }
    }

    $setParts = [];
    $values = [];
    foreach ($_FILES['files']['name'] as $key => $name) {
      if (!in_array($key, $allowedKeys, true)) continue;
      if (!$_FILES['files']['tmp_name'][$key]) continue;
      $tmp = $_FILES['files']['tmp_name'][$key];
      $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($name));
      $target = $uploadDir . '/' . time() . '_' . $safeName;
      if (@move_uploaded_file($tmp, $target)) {
        $relPath = 'backend/' . trim(str_replace(__DIR__ . '/', '', $target), '/');
        $setParts[] = "`$key` = ?";
        $values[] = $relPath;
      }
    }
    if (!empty($setParts)) {
      $sql = "UPDATE leads SET " . implode(', ', $setParts) . " WHERE lead_id = ?";
      $upd = $conn->prepare($sql);
      $types = str_repeat('s', count($values)) . 'i';
      $values[] = $id;
      $upd->bind_param($types, ...$values);
      $upd->execute();
      $upd->close();
    }
  }

  $payload = ["success" => true];
  $stmt->close();
  $conn->close();
  ob_end_clean(); echo json_encode($payload); exit;
} else {
  http_response_code(500);
  $payload = ["success" => false, "error" => $stmt->error];
  $stmt->close();
  $conn->close();
  ob_end_clean(); echo json_encode($payload); exit;
}

// Fallback close (should not reach here due to exits above)
if (isset($stmt) && $stmt instanceof mysqli_stmt) { $stmt->close(); }
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }



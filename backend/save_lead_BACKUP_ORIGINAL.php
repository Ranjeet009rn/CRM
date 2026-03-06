<?php
// Runtime hardening for stable JSON over HTTP/2
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

// CORS + JSON headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require_once 'db.php';

// Read incoming JSON data or multipart FormData payload
$input = null;
if (!empty($_POST['payload'])) {
    $input = json_decode($_POST['payload'], true);
} else {
    $input = json_decode(file_get_contents("php://input"), true);
}

// Validate required fields
if (
    !$input ||
    !isset($input['name']) ||
    !isset($input['phone']) ||
    !isset($input['email']) ||
    !isset($input['source']) ||
    !isset($input['status']) ||
    !isset($input['assignedTo']) ||
    !isset($input['address']) ||
    !isset($input['cd_date']) ||
    !isset($input['loan_reason']) ||
    !isset($input['loan_amount']) ||
    !isset($input['emi']) ||
    !isset($input['interest_rate'])
) {
    http_response_code(400);
    $payload = ["error" => "Missing required fields"];
    ob_end_clean();
    echo json_encode($payload);
    exit();
}

// Extract values
$name           = $input['name'];
$email          = $input['email'];
$phone          = $input['phone'];
$source         = $input['source'];
$status         = $input['status'];
$assignedTo     = $input['assignedTo'];
$notes          = $input['notes'] ?? '';
$label          = $input['label'] ?? '';
$reference      = $input['reference'] ?? '';
$address        = $input['address'];
$current_address = $input['current_address'] ?? null;
$permanent_address = $input['permanent_address'] ?? null;
$father_name    = $input['father_name'] ?? null;
$dob            = $input['dob'] ?? null; // YYYY-MM-DD
$age            = isset($input['age']) ? (string) (int) $input['age'] : null;
$marital_status = $input['marital_status'] ?? null;
$education      = $input['education'] ?? null;
$occupation     = $input['occupation'] ?? null;
$monthly_income = $input['monthly_income'] ?? null;
$cd_date        = $input['cd_date'];
$loan_reason    = $input['loan_reason'];
$loan_amount    = $input['loan_amount'];
$emi            = $input['emi'];
$emi_frequency  = $input['emi_frequency'] ?? 'Monthly';
$interest_rate  = $input['interest_rate'];
$loan_tenure    = $input['loan_tenure'] ?? null;
$cancel_reason  = $input['cancel_reason'] ?? ''; // NEW FIELD
$ai_token       = $input['ai_token'] ?? null;

$pan_card = $input['pan_card'] ?? null;
$aadhar_card = $input['aadhar_card'] ?? null;
$ifsc_code = $input['ifsc_code'] ?? null;
$branch_name = $input['branch_name'] ?? null;

// Newly added Guarantor & References fields
$guarantor_name  = $input['guarantor_name'] ?? null;
$guarantor_phone = $input['guarantor_phone'] ?? null;
$reference1_name = $input['reference1_name'] ?? null;
$reference1_phone= $input['reference1_phone'] ?? null;

// Newly added LeadForm fields
$caste = $input['caste'] ?? null;
$religion = $input['religion'] ?? null;
$social_category = $input['social_category'] ?? null;
$alternate_phone = $input['alternate_phone'] ?? null;
$ration_card_type = $input['ration_card_type'] ?? null;

$account_holder_name = $input['account_holder_name'] ?? null;
$project_amount = $input['project_amount'] ?? null;
$cibil_score = $input['cibil_score'] ?? null;
$has_previous_loan = !empty($input['has_previous_loan']) ? 1 : 0;
$previous_loan_amount = $input['previous_loan_amount'] ?? null;

$lic_policy_no = $input['lic_policy_no'] ?? null;
$lic_sum_assured = $input['lic_sum_assured'] ?? null;
$lic_premium = $input['lic_premium'] ?? null;

$satbara_no = $input['satbara_no'] ?? null;
$survey_gat_no = $input['survey_gat_no'] ?? null;
$village = $input['village'] ?? null;
$taluka = $input['taluka'] ?? null;
$district = $input['district'] ?? null;

$has_income_certificate = !empty($input['has_income_certificate']) ? 1 : 0;
$has_business_bill = !empty($input['has_business_bill']) ? 1 : 0;
$has_aadhaar_copy = !empty($input['has_aadhaar_copy']) ? 1 : 0;
$has_bank_passbook = !empty($input['has_bank_passbook']) ? 1 : 0;

// Insert query
// Ensure 'documents' column exists (TEXT to support broad MySQL versions)
$colCheck = $conn->query("SHOW COLUMNS FROM leads LIKE 'documents'");
if ($colCheck && $colCheck->num_rows === 0) {
    @$conn->query("ALTER TABLE leads ADD COLUMN documents TEXT NULL");
}

// Handle uploaded files (if any) and build documents JSON
$documents_paths = [];
if (!empty($_FILES['files']) && isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
    // Defer moving files until after we know insert id; we will move after execute
}

// Legacy aggregate documents column removed; using per-document columns instead

// Ensure common non-document columns exist (if needed)
foreach ([
  ['name' => 'pan_card', 'type' => 'VARCHAR(30) NULL'],
  ['name' => 'aadhar_card', 'type' => 'VARCHAR(20) NULL'],
  ['name' => 'ifsc_code', 'type' => 'VARCHAR(20) NULL'],
  ['name' => 'branch_name', 'type' => 'VARCHAR(100) NULL']
] as $col) {
  $chk = $conn->query("SHOW COLUMNS FROM leads LIKE '".$conn->real_escape_string($col['name'])."'");
  if ($chk && $chk->num_rows === 0) {
    @$conn->query("ALTER TABLE leads ADD COLUMN `{$col['name']}` {$col['type']}");
  }
}

$stmt = $conn->prepare("
    INSERT INTO leads 
    (name, email, phone, source, status, assigned_to, notes, label, reference, address, current_address, permanent_address, father_name, dob, age, marital_status, education, occupation, monthly_income, cd_date, loan_reason, loan_amount, emi, emi_frequency, loan_tenure, interest_rate, guarantor_name, guarantor_phone, reference1_name, reference1_phone, cancel_reason, ai_token,
     caste, religion, social_category, alternate_phone, ration_card_type,
     account_holder_name, project_amount, cibil_score, has_previous_loan, previous_loan_amount,
     lic_policy_no, lic_sum_assured, lic_premium,
     satbara_no, survey_gat_no, village, taluka, district,
     has_income_certificate, has_business_bill, has_aadhaar_copy, has_bank_passbook, pan_card, aadhar_card, ifsc_code, branch_name)
    VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?
           )
");

if (!$stmt) {
    http_response_code(500);
    $payload = ["error" => "Prepare failed: " . $conn->error];
    ob_end_clean();
    echo json_encode($payload);
    exit();
}

// Dynamically build bind params and types to prevent count mismatches
$params = [
    $name,
    $email,
    $phone,
    $source,
    $status,
    $assignedTo,
    $notes,
    $label,
    $reference,
    $address,
    $current_address,
    $permanent_address,
    $father_name,
    $dob,
    $age,
    $marital_status,
    $education,
    $occupation,
    $monthly_income,
    $cd_date,
    $loan_reason,
    $loan_amount,
    $emi,
    $emi_frequency,
    $loan_tenure,
    $interest_rate,
    $guarantor_name,
    $guarantor_phone,
    $reference1_name,
    $reference1_phone,
    $cancel_reason,
    $ai_token,
    $caste,
    $religion,
    $social_category,
    $alternate_phone,
    $ration_card_type,
    $account_holder_name,
    $project_amount,
    $cibil_score,
    $has_previous_loan,
    $previous_loan_amount,
    $lic_policy_no,
    $lic_sum_assured,
    $lic_premium,
    $satbara_no,
    $survey_gat_no,
    $village,
    $taluka,
    $district,
    $has_income_certificate,
    $has_business_bill,
    $has_aadhaar_copy,
    $has_bank_passbook,
    $pan_card,
    $aadhar_card,
    $ifsc_code,
    $branch_name
];
$types = str_repeat('s', count($params));

// Debug: Log column and param count
error_log("save_lead.php - Param count: " . count($params));
error_log("save_lead.php - Types: " . $types);

if (!$stmt->bind_param($types, ...$params)) {
    http_response_code(500);
    $payload = ["error" => "Bind param failed: " . $stmt->error, "param_count" => count($params)];
    ob_end_clean();
    echo json_encode($payload);
    exit();
}

// Execute and respond
if ($stmt->execute()) {
    $newId = $conn->insert_id;

    // If files were uploaded, move them and update corresponding columns on leads
    if (!empty($_FILES['files']) && isset($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
        $uploadDir = __DIR__ . '/uploads/leads/' . $newId;
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

        // Whitelist of allowed document keys mapping 1:1 to leads table columns
        // Keep in sync with frontend docChecklist
        $allowedKeys = [
            // Identity & KYC
            'aadhaar_copy','pan_copy','photo','signature','voter_id','driving_license',
            // Income / Bank
            'income_certificate','salary_slip','form16','itr_ack','bank_statement','passbook',
            // Business / Registration
            'udyam_udhyog','gst_certificate','shop_act','roc','partnership_deed','moa_aoa','business_bill','quotation','project_report',
            // Residence / Utility
            'electricity_bill','rent_agreement',
            // Property / Collateral
            'satbara_7_12','property_map','sale_deed','noc','valuation_report',
            // LIC / Insurance
            'lic_policy_copy',
            // Guarantor
            'guarantor_aadhaar','guarantor_pan','guarantor_photo'
        ];

        // Ensure columns exist for each allowed key
        foreach ($allowedKeys as $col) {
            $check = $conn->query("SHOW COLUMNS FROM leads LIKE '" . $conn->real_escape_string($col) . "'");
            if ($check && $check->num_rows === 0) {
                // use VARCHAR(255) to store relative path
                @$conn->query("ALTER TABLE leads ADD COLUMN `$col` VARCHAR(255) NULL");
            }
        }

        $setParts = [];
        $values = [];
        foreach ($_FILES['files']['name'] as $key => $name) {
            if (!in_array($key, $allowedKeys, true)) continue; // ignore unknown keys
            if (!$_FILES['files']['tmp_name'][$key]) continue;
            $tmp = $_FILES['files']['tmp_name'][$key];
            // Preserve original extension for pdf/doc/images
            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($name));
            $target = $uploadDir . '/' . time() . '_' . $safeName;
            if (@move_uploaded_file($tmp, $target)) {
                $relPath = 'backend/uploads/leads/' . $newId . '/' . basename($target);
                // Map to legacy column name if present (aadhaar_copy -> aadhar_copy)
                $colName = $key;
                if ($key === 'aadhaar_copy') {
                  $exists = $conn->query("SHOW COLUMNS FROM leads LIKE 'aadhar_copy'");
                  if ($exists && $exists->num_rows > 0) { $colName = 'aadhar_copy'; }
                }
                $setParts[] = "`$colName` = ?";
                $values[] = $relPath;
            }
        }
        if (!empty($setParts)) {
            $sql = "UPDATE leads SET " . implode(', ', $setParts) . " WHERE id = ?";
            $upd = $conn->prepare($sql);
            $types = str_repeat('s', count($values)) . 'i';
            $values[] = $newId;
            $upd->bind_param($types, ...$values);
            $upd->execute();
            $upd->close();
        }
    }

    $payload = ["success" => true, "id" => $newId];
    ob_end_clean();
    echo json_encode($payload);
    exit();
} else {
    http_response_code(500);
    $payload = ["error" => $stmt->error];
    ob_end_clean();
    echo json_encode($payload);
    exit();
}

$stmt->close();
$conn->close();
?>

<?php
// COMPLETE SAVE LEAD - All fields version
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Read JSON input - handle both direct JSON and FormData with payload
$input = null;
if (!empty($_POST['payload'])) {
    // FormData with payload field
    $input = json_decode($_POST['payload'], true);
    error_log("Received FormData with payload");
} else {
    // Direct JSON
    $input = json_decode(file_get_contents("php://input"), true);
    error_log("Received direct JSON");
}

// Debug logging
error_log("=== SAVE LEAD DEBUG ===");
error_log("Input data: " . json_encode($input));
error_log("Input fields count: " . (is_array($input) ? count($input) : 0));
error_log("Input fields: " . (is_array($input) ? implode(", ", array_keys($input)) : "none"));
error_log("=======================");

// Validate required fields
if (!$input || !isset($input['name']) || !isset($input['phone'])) {
    $missing = [];
    if (!$input)
        $missing[] = "No input data";
    if (!isset($input['name']))
        $missing[] = "name";
    if (!isset($input['phone']))
        $missing[] = "phone";

    http_response_code(400);
    echo json_encode([
        "error" => "Missing required fields: " . implode(", ", $missing),
        "received" => $input ? array_keys($input) : []
    ]);
    exit();
}

// Helper function to get value or null
function getValue($input, $key, $default = null)
{
    $value = $input[$key] ?? $default;
    return ($value === "" || $value === "null") ? null : $value;
}

// Handle document file uploads
$uploadedDocuments = [];
$uploadDir = '../uploads/leads/';

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Process uploaded files
error_log("=== FILE UPLOAD DEBUG ===");
error_log("_FILES array: " . json_encode($_FILES));
error_log("_POST array: " . json_encode($_POST));
error_log("Upload directory: " . $uploadDir);
error_log("Directory exists: " . (file_exists($uploadDir) ? 'yes' : 'no'));

if (!empty($_FILES)) {
    error_log("Processing uploaded files: " . json_encode(array_keys($_FILES)));

    foreach ($_FILES as $fieldName => $fileInfo) {
        error_log("Processing field: $fieldName");
        error_log("File info structure: " . json_encode(array_keys($fileInfo)));

        // Handle multiple files uploaded as files[key1], files[key2], etc.
        if ($fieldName === 'files' && is_array($fileInfo['name'])) {
            error_log("Processing multiple files array");

            foreach ($fileInfo['name'] as $docKey => $fileName) {
                if ($fileInfo['error'][$docKey] === UPLOAD_ERR_OK && !empty($fileName)) {
                    $tmpName = $fileInfo['tmp_name'][$docKey];
                    $safeFileName = time() . '_' . $docKey . '_' . basename($fileName);
                    $targetPath = $uploadDir . $safeFileName;

                    error_log("Processing document: $docKey -> $fileName");
                    error_log("Target path: $targetPath");

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $uploadedDocuments[$docKey] = $safeFileName;
                        error_log("SUCCESS: Uploaded document: $docKey -> $safeFileName");
                    } else {
                        error_log("FAILED: Could not move uploaded file for $docKey");
                    }
                } else {
                    error_log("Skipping $docKey - error: " . ($fileInfo['error'][$docKey] ?? 'unknown'));
                }
            }
        }
        // Handle single file uploads (fallback)
        else if (strpos($fieldName, 'files[') === 0 && $fileInfo['error'] === UPLOAD_ERR_OK) {
            preg_match('/files\[([^\]]+)\]/', $fieldName, $matches);
            $docKey = $matches[1] ?? '';

            if ($docKey) {
                $fileName = time() . '_' . $docKey . '_' . basename($fileInfo['name']);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
                    $uploadedDocuments[$docKey] = $fileName;
                    error_log("SUCCESS: Uploaded single document: $docKey -> $fileName");
                } else {
                    error_log("FAILED: Could not move single uploaded file for $docKey");
                }
            }
        } else {
            error_log("Skipping field $fieldName - not a file upload or has errors");
        }
    }
} else {
    error_log("No files in _FILES array");
}

error_log("Final uploaded documents: " . json_encode($uploadedDocuments));
error_log("========================");

// Get exact database columns to avoid extra fields
$result = $conn->query("SHOW COLUMNS FROM leads");
$dbColumns = [];
while ($row = $result->fetch_assoc()) {
    $dbColumns[] = $row['Field'];
}

error_log("Database columns: " . implode(", ", $dbColumns));

// Extract ONLY database fields from the input
$fields = [];
foreach ($dbColumns as $column) {
    if ($column === 'lead_id')
        continue; // Skip auto-increment primary key
    if ($column === 'id')
        continue; // Skip if exists
    if ($column === 'created_at') {
        $fields[$column] = date('Y-m-d H:i:s');
    } else if ($column === 'created_by') {
        // Set the creator based on who is creating the lead (trim to avoid trailing spaces)
        $rawValue = getValue($input, 'created_by') ?? getValue($input, 'username') ?? 'admin';
        $fields[$column] = trim($rawValue);
    } else if ($column === 'username') {
        // Set username field if it exists (trim to avoid trailing spaces)
        $rawValue = getValue($input, 'username') ?? getValue($input, 'created_by') ?? 'admin';
        $fields[$column] = trim($rawValue);
    } else if ($column === 'assigned_to') {
        // Map frontend field name and trim
        $rawValue = getValue($input, 'assignedTo') ?? getValue($input, 'assigned_to') ?? '';
        $fields[$column] = trim($rawValue);
    } else if ($column === 'is_external') {
        // Normalize third-party flag: save 1 for third party, otherwise 0 (tinyint(1))
        $raw = getValue($input, 'is_external');

        // Treat various truthy values as third party
        $truthyValues = ['1', 1, true, 'true', 'yes', 'on', 'third_party'];
        $isThirdParty = in_array($raw, $truthyValues, true);

        $fields[$column] = $isThirdParty ? 1 : 0;
    } else if (isset($uploadedDocuments[str_replace('_copy', '_copy', $column)])) {
        // Document file columns
        $fields[$column] = $uploadedDocuments[$column] ?? null;
    } else {
        // Regular fields
        $fields[$column] = getValue($input, $column);
    }
}

// Normalize values to avoid "Array to string conversion" and ensure bind_param compatibility
foreach ($fields as $key => $val) {
    if (is_array($val) || is_object($val)) {
        $fields[$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
    } else if (is_bool($val)) {
        $fields[$key] = $val ? 1 : 0;
    }
}

error_log("Fields to insert: " . implode(", ", array_keys($fields)));

// Ensure all columns exist in the database before inserting
error_log("Checking and creating missing columns...");
foreach (array_keys($fields) as $column) {
    $check = $conn->query("SHOW COLUMNS FROM leads LIKE '" . $conn->real_escape_string($column) . "'");
    if ($check && $check->num_rows === 0) {
        $alterSql = "ALTER TABLE leads ADD COLUMN `$column` VARCHAR(255) NULL";
        if ($conn->query($alterSql)) {
            error_log("Created missing column: $column");
        } else {
            error_log("Failed to create column $column: " . $conn->error);
        }
    }
}

// Build dynamic INSERT query
$columns = array_keys($fields);
$placeholders = str_repeat('?,', count($columns) - 1) . '?';
$sql = "INSERT INTO leads (" . implode(',', $columns) . ") VALUES ($placeholders)";

error_log("SQL Query: " . $sql);
error_log("Values count: " . count($fields));

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(["error" => "Prepare failed: " . $conn->error]);
    exit();
}

// Create type string (all strings for simplicity)
$types = str_repeat('s', count($fields));
$values = array_values($fields);

$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    $leadId = $conn->insert_id;
    error_log("Lead saved successfully with ID: " . $leadId);

    // === Auto-create / update customer from lead ===
    // Reuse the same mapping as convert_to_customer.php but without status restriction
    $leadName = $fields['name'] ?? null;
    $leadEmail = $fields['email'] ?? null;
    $leadPhone = $fields['phone'] ?? null;
    $leadAddress = $fields['address'] ?? null;
    $leadReason = $fields['loan_reason'] ?? null;
    $leadAmount = $fields['loan_amount'] ?? null;
    $leadInterest = $fields['interest_rate'] ?? null;
    $leadEmi = $fields['emi'] ?? null;

    // Only attempt customer creation when we have at least a name and phone
    if (!empty($leadName) && !empty($leadPhone)) {
        // Check if customer already exists for this lead_id
        $check = $conn->prepare("SELECT id FROM customer WHERE lead_id = ?");
        if ($check) {
            $check->bind_param("i", $leadId);
            $check->execute();
            $checkRes = $check->get_result();

            if ($checkRes && $checkRes->num_rows > 0) {
                // Update existing customer with latest lead data
                $update = $conn->prepare(
                    "UPDATE customer SET name = ?, email = ?, phone = ?, address = ?, loan_reason = ?, loan_amount = ?, interest_rate = ?, emi = ? WHERE lead_id = ?"
                );
                if ($update) {
                    $update->bind_param(
                        "ssssssssi",
                        $leadName,
                        $leadEmail,
                        $leadPhone,
                        $leadAddress,
                        $leadReason,
                        $leadAmount,
                        $leadInterest,
                        $leadEmi,
                        $leadId
                    );
                    if (!$update->execute()) {
                        error_log("Auto-update customer failed for lead_id {$leadId}: " . $update->error);
                    }
                    $update->close();
                } else {
                    error_log("Prepare failed for auto-update customer: " . $conn->error);
                }
            } else {
                // Insert new customer row
                $insert = $conn->prepare(
                    "INSERT INTO customer (lead_id, name, email, phone, address, loan_reason, loan_amount, interest_rate, emi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                if ($insert) {
                    $insert->bind_param(
                        "issssssss",
                        $leadId,
                        $leadName,
                        $leadEmail,
                        $leadPhone,
                        $leadAddress,
                        $leadReason,
                        $leadAmount,
                        $leadInterest,
                        $leadEmi
                    );
                    if (!$insert->execute()) {
                        error_log("Auto-insert customer failed for lead_id {$leadId}: " . $insert->error);
                    }
                    $insert->close();
                } else {
                    error_log("Prepare failed for auto-insert customer: " . $conn->error);
                }
            }

            $check->close();
        } else {
            error_log("Prepare failed for customer existence check: " . $conn->error);
        }
    } else {
        error_log("Skipping auto customer create: missing required name/phone for lead_id {$leadId}");
    }

    echo json_encode(["success" => true, "id" => $leadId]);
} else {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(["error" => "Execute failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
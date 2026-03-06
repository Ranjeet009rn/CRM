<?php
// Script to add missing columns to leads table
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

$missingColumns = [
    // Bank Details
    'bank_name' => 'VARCHAR(100) NULL',
    'account_no' => 'VARCHAR(20) NULL',
    
    // Family Details  
    'spouse_name' => 'VARCHAR(100) NULL',
    'spouse_occupation' => 'VARCHAR(100) NULL',
    'children_count' => 'INT(11) NULL',
    'family_members' => 'INT(11) NULL',
    
    // Reference Details
    'reference1_relation' => 'VARCHAR(50) NULL',
    'reference2_name' => 'VARCHAR(100) NULL', 
    'reference2_phone' => 'VARCHAR(10) NULL',
    'reference2_relation' => 'VARCHAR(50) NULL',
    
    // Property Details
    'property_type' => 'VARCHAR(50) NULL',
    'property_value' => 'DECIMAL(12,2) NULL',
    'property_address' => 'TEXT NULL',
    'property_documents' => 'VARCHAR(255) NULL',
    
    // Other Details
    'loan_reason_other' => 'VARCHAR(255) NULL',
    'caste_other' => 'VARCHAR(100) NULL',
    'religion_other' => 'VARCHAR(100) NULL',
    
    // Income Documents
    'salary_certificate' => 'TINYINT(1) DEFAULT 0',
    'income_tax_return' => 'TINYINT(1) DEFAULT 0',
    'other_documents' => 'TEXT NULL',
    'insurance_required' => 'TINYINT(1) DEFAULT 0',
    
    // Creator tracking
    'creator_name' => 'VARCHAR(100) NULL',
    'created_by' => 'VARCHAR(100) NULL',
    'added_by' => 'VARCHAR(100) NULL',
    'username' => 'VARCHAR(100) NULL',
    'emp_id' => 'VARCHAR(50) NULL',
    'user_id' => 'VARCHAR(50) NULL'
];

$results = [];
$errors = [];

foreach ($missingColumns as $column => $definition) {
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM leads LIKE '$column'");
    
    if ($check && $check->num_rows === 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE leads ADD COLUMN `$column` $definition";
        
        if ($conn->query($sql)) {
            $results[] = "✅ Added column: $column";
        } else {
            $errors[] = "❌ Failed to add $column: " . $conn->error;
        }
    } else {
        $results[] = "ℹ️ Column already exists: $column";
    }
}

echo json_encode([
    'success' => count($errors) === 0,
    'results' => $results,
    'errors' => $errors,
    'total_columns_checked' => count($missingColumns),
    'columns_added' => count(array_filter($results, function($r) { return strpos($r, '✅') === 0; }))
]);

$conn->close();
?>

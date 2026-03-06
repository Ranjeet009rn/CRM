<?php
// Debug script to test save_lead.php column count
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

// Check if emi_frequency column exists
$result = $conn->query("SHOW COLUMNS FROM leads LIKE 'emi_frequency'");
if ($result->num_rows > 0) {
    echo "✅ emi_frequency column EXISTS in leads table\n";
} else {
    echo "❌ emi_frequency column DOES NOT EXIST in leads table\n";
    echo "Run this SQL to add it:\n";
    echo "ALTER TABLE `leads` ADD `emi_frequency` ENUM('Monthly', 'Quarterly', 'Half-Yearly', 'Yearly', 'Other') DEFAULT 'Monthly' AFTER `emi`;\n";
}

// Count total columns in leads table
$result = $conn->query("SELECT COUNT(*) as col_count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads'");
$row = $result->fetch_assoc();
echo "\nTotal columns in leads table: " . $row['col_count'] . "\n";

// List all columns
echo "\nAll columns in leads table:\n";
$result = $conn->query("SHOW COLUMNS FROM leads");
$i = 1;
while ($col = $result->fetch_assoc()) {
    echo "$i. " . $col['Field'] . " (" . $col['Type'] . ")\n";
    $i++;
}

$conn->close();
?>

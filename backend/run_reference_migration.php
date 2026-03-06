<?php
// Run migration to add reference fields
require_once 'db.php';

echo "Adding reference fields to leads table...\n";

// Add reference_type column
$sql1 = "ALTER TABLE leads ADD COLUMN reference_type VARCHAR(50) DEFAULT NULL COMMENT 'Self or Enter Name'";
if ($conn->query($sql1) === TRUE) {
    echo "✓ Added reference_type column\n";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "✓ reference_type column already exists\n";
    } else {
        echo "✗ Error adding reference_type: " . $conn->error . "\n";
    }
}

// Add reference_name column
$sql2 = "ALTER TABLE leads ADD COLUMN reference_name VARCHAR(255) DEFAULT NULL COMMENT 'Custom reference name'";
if ($conn->query($sql2) === TRUE) {
    echo "✓ Added reference_name column\n";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "✓ reference_name column already exists\n";
    } else {
        echo "✗ Error adding reference_name: " . $conn->error . "\n";
    }
}

echo "\nMigration complete!\n";
$conn->close();
?>
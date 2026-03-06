<?php
// Run database migration to add created_by columns
require_once 'db.php';

echo "Starting RBAC migration...\n";

try {
    // Add created_by to customer table
    $sql = "ALTER TABLE customer ADD COLUMN created_by VARCHAR(100) DEFAULT 'admin'";
    if ($conn->query($sql)) {
        echo "✓ Added created_by to customer table\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "- created_by already exists in customer table\n";
        } else {
            echo "✗ Error adding created_by to customer: " . $conn->error . "\n";
        }
    }

    // Add created_by to sanctioned_loans table  
    $sql = "ALTER TABLE sanctioned_loan ADD COLUMN created_by VARCHAR(100) DEFAULT 'admin'";
    if ($conn->query($sql)) {
        echo "✓ Added created_by to sanctioned_loan table\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "- created_by already exists in sanctioned_loan table\n";
        } else {
            echo "✗ Error adding created_by to sanctioned_loan: " . $conn->error . "\n";
        }
    }

    // Add created_by to payments table
    $sql = "ALTER TABLE payments ADD COLUMN created_by VARCHAR(100) DEFAULT 'admin'";
    if ($conn->query($sql)) {
        echo "✓ Added created_by to payments table\n";
    } else {
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "- created_by already exists in payments table\n";
        } else {
            echo "✗ Error adding created_by to payments: " . $conn->error . "\n";
        }
    }

    // Update existing records
    echo "\nUpdating existing records...\n";

    $conn->query("UPDATE customer SET created_by = 'admin' WHERE created_by IS NULL OR created_by = ''");
    echo "✓ Updated customer records\n";

    $conn->query("UPDATE sanctioned_loan SET created_by = 'admin' WHERE created_by IS NULL OR created_by = ''");
    echo "✓ Updated sanctioned_loan records\n";

    $conn->query("UPDATE payments SET created_by = 'admin' WHERE created_by IS NULL OR created_by = ''");
    echo "✓ Updated payments records\n";

    echo "\n✓ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}

$conn->close();
?>
<?php
// Fix trailing spaces in leads table
require_once 'db.php';

header('Content-Type: application/json');

try {
    echo "Fixing trailing spaces in leads table...\n\n";

    // Update created_by to remove trailing spaces
    $sql = "UPDATE leads SET created_by = TRIM(created_by) WHERE created_by IS NOT NULL";
    if ($conn->query($sql)) {
        echo "✓ Trimmed created_by field\n";
        echo "  Rows affected: " . $conn->affected_rows . "\n\n";
    }

    // Update assigned_to to remove trailing spaces
    $sql = "UPDATE leads SET assigned_to = TRIM(assigned_to) WHERE assigned_to IS NOT NULL";
    if ($conn->query($sql)) {
        echo "✓ Trimmed assigned_to field\n";
        echo "  Rows affected: " . $conn->affected_rows . "\n\n";
    }

    // Update scrutinized_by to remove trailing spaces
    $sql = "UPDATE leads SET scrutinized_by = TRIM(scrutinized_by) WHERE scrutinized_by IS NOT NULL";
    if ($conn->query($sql)) {
        echo "✓ Trimmed scrutinized_by field\n";
        echo "  Rows affected: " . $conn->affected_rows . "\n\n";
    }

    echo "✓ All trailing spaces removed successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
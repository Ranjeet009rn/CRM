<?php
/**
 * Database Migration Script
 * Purpose: Remove face_image column from employee table
 * This allows employees to login without face recognition data
 */

require_once 'db.php';

echo "Starting database migration...\n\n";

// Check if face_image column exists
$checkColumn = "SHOW COLUMNS FROM employee LIKE 'face_image'";
$result = $conn->query($checkColumn);

if ($result && $result->num_rows > 0) {
    echo "✓ face_image column found in employee table\n";
    echo "Removing face_image column...\n";
    
    // Remove the face_image column
    $sql = "ALTER TABLE employee DROP COLUMN face_image";
    
    if ($conn->query($sql) === TRUE) {
        echo "✅ SUCCESS: face_image column removed successfully!\n";
        echo "Employees can now login with username and password only.\n";
    } else {
        echo "❌ ERROR: " . $conn->error . "\n";
    }
} else {
    echo "✓ face_image column does not exist (already removed or never existed)\n";
    echo "✅ Database is ready for username/password login only.\n";
}

// Verify the current table structure
echo "\n--- Current employee table structure ---\n";
$showColumns = "SHOW COLUMNS FROM employee";
$columnsResult = $conn->query($showColumns);

if ($columnsResult) {
    while ($row = $columnsResult->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

$conn->close();
echo "\n✅ Migration completed!\n";
?>

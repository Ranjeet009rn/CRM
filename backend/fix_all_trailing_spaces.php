<?php
// Fix trailing spaces in all tables
require_once 'db.php';

header('Content-Type: application/json');

try {
    echo "Fixing trailing spaces in all tables...\n\n";

    // Fix tasks table
    echo "=== TASKS TABLE ===\n";
    $tables = [
        'tasks' => ['created_by', 'assigned_to'],
        'customer' => ['created_by'],
        'sanctioned_loan' => ['created_by'],
        'payments' => ['created_by'],
        'enquiry' => ['created_by', 'assigned_to']
    ];

    foreach ($tables as $table => $columns) {
        echo "\n$table:\n";
        foreach ($columns as $column) {
            // Check if column exists first
            $checkCol = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
            if ($checkCol && $checkCol->num_rows > 0) {
                $sql = "UPDATE $table SET $column = TRIM($column) WHERE $column IS NOT NULL";
                if ($conn->query($sql)) {
                    echo "  ✓ Trimmed $column (affected: {$conn->affected_rows})\n";
                }
            } else {
                echo "  - Column $column doesn't exist\n";
            }
        }
    }

    echo "\n✓ All trailing spaces removed successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
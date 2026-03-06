<?php
// Add Pending type to payments table
// Run this once: http://localhost/CRM/CRM/backend/setup_pending_type.php

header("Content-Type: text/html; charset=utf-8");

$conn = new mysqli("localhost", "root", "", "crm");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE `payments` 
        MODIFY COLUMN `type` ENUM('Inward', 'Outward', 'Pending') NOT NULL 
        COMMENT 'Payment type: Inward (+), Outward (-), or Pending'";

if ($conn->query($sql) === TRUE) {
    echo "<h2 style='color: green;'>✅ Success!</h2>";
    echo "<p>Payment type 'Pending' has been added successfully.</p>";
    echo "<p>You can now select Pending when adding payments.</p>";
    echo "<hr>";
    echo "<h3>Payment Types:</h3>";
    echo "<ul>";
    echo "<li>💰 <strong>Inward</strong> - Money In (+)</li>";
    echo "<li>💸 <strong>Outward</strong> - Money Out (-)</li>";
    echo "<li>⏳ <strong>Pending</strong> - Not calculated in balance</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<p><a href='../frontend/public/index.html'>Go to Dashboard</a></p>";
} else {
    echo "<h2 style='color: red;'>❌ Error!</h2>";
    echo "<p>Error updating table: " . $conn->error . "</p>";
    echo "<p><small>Note: If the error says 'Duplicate column', it means Pending type already exists.</small></p>";
}

$conn->close();
?>

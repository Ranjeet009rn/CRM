<?php
// This script creates the payments table if it doesn't exist
// Run this once by visiting: http://localhost/CRM/CRM/backend/setup_payments_table.php

header("Content-Type: text/html; charset=utf-8");

$conn = new mysqli("localhost", "root", "", "crm");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` ENUM('Inward', 'Outward') NOT NULL COMMENT 'Payment type: Inward (+) or Outward (-)',
  `date` DATE NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `emp_id` INT(11) DEFAULT NULL COMMENT 'Foreign key to employee table',
  `customer_id` INT(11) DEFAULT NULL COMMENT 'Foreign key to customer table',
  `mode` ENUM('Cash', 'Online') DEFAULT NULL COMMENT 'Payment mode',
  `online_mode` VARCHAR(50) DEFAULT NULL COMMENT 'Online payment method: GPay, PhonePe, Other',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`date`),
  KEY `idx_emp_id` (`emp_id`),
  KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<h2 style='color: green;'>✅ Success!</h2>";
    echo "<p>The 'payments' table has been created successfully.</p>";
    echo "<p>You can now use the Payment Management system in your dashboard.</p>";
    echo "<hr>";
    echo "<p><a href='../frontend/public/index.html'>Go to Dashboard</a></p>";
} else {
    echo "<h2 style='color: red;'>❌ Error!</h2>";
    echo "<p>Error creating table: " . $conn->error . "</p>";
}

$conn->close();
?>

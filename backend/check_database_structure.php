<?php
// Simple script to check database structure and data
require_once 'db.php';

echo "<h2>Checking Leads Table Structure</h2>";

// Check table structure
echo "<h3>Table Columns:</h3>";
$result = $conn->query("SHOW COLUMNS FROM leads");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check sample data
echo "<h3>Sample Lead Data:</h3>";
$result = $conn->query("SELECT lead_id, name, assigned_to, created_by, username FROM leads LIMIT 5");
echo "<table border='1'>";
echo "<tr><th>Lead ID</th><th>Name</th><th>Assigned To</th><th>Created By</th><th>Username</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . ($row['lead_id'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['name'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['assigned_to'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['created_by'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['username'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Count leads with missing creator info
$result = $conn->query("SELECT COUNT(*) as count FROM leads WHERE created_by IS NULL OR created_by = ''");
$missingCreator = $result->fetch_assoc()['count'];
echo "<h3>Leads with missing creator info: " . $missingCreator . "</h3>";

echo "<h3>Recommended Actions:</h3>";
echo "<ol>";
echo "<li>If 'created_by' column doesn't exist, run: <code>ALTER TABLE leads ADD COLUMN created_by VARCHAR(255) DEFAULT NULL;</code></li>";
echo "<li>If 'username' column doesn't exist, run: <code>ALTER TABLE leads ADD COLUMN username VARCHAR(255) DEFAULT NULL;</code></li>";
echo "<li>Update existing leads: <code>UPDATE leads SET created_by = 'admin' WHERE created_by IS NULL OR created_by = '';</code></li>";
echo "</ol>";
?>

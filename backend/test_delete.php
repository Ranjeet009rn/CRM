<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Test database connection
$host = "193.203.184.228";
$username = "u876295706_ingawale";
$password = "Ranjeet@1810";
$database = "u876295706_ingavale";

try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        echo json_encode([
            "success" => false,
            "message" => "Connection failed: " . $conn->connect_error
        ]);
        exit;
    }

    // Test if tasks table exists
    $result = $conn->query("SHOW TABLES LIKE 'tasks'");
    if ($result->num_rows == 0) {
        echo json_encode([
            "success" => false,
            "message" => "Tasks table does not exist"
        ]);
        exit;
    }

    // Test query
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "message" => "Database connection successful",
        "task_count" => $row['count']
    ]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
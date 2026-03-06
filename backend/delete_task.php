<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["id"])) {
        echo json_encode(["success" => false, "message" => "Task ID is required"]);
        exit;
    }

    $taskId = $data["id"];

    // Production database credentials (same as db.php)
    $host = "193.203.184.228";
    $username = "u876295706_ingawale";
    $password = "Ranjeet@1810";
    $database = "u876295706_ingavale";

    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        echo json_encode(["success" => false, "message" => "DB connection failed: " . $conn->connect_error]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        $conn->close();
        exit;
    }

    $stmt->bind_param("i", $taskId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Task deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to delete task: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
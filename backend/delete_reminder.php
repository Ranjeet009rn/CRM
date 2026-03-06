<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Use central production database connection
require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["id"])) {
    echo json_encode(["success" => false, "message" => "Reminder ID is required"]);
    exit;
}

$reminderId = (int)$data["id"];

try {
    $stmt = $conn->prepare("DELETE FROM reminders WHERE id = ?");
    $stmt->bind_param("i", $reminderId);
    $stmt->execute();

    echo json_encode(["success" => true]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to delete reminder"
    ]);
}
?>

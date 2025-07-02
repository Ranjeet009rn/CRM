<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET");

include("db.php");

if (!isset($_GET['id'])) {
    echo json_encode(["success" => false, "error" => "Meeting ID is required"]);
    exit;
}

$meetingId = intval($_GET['id']);
$query = "SELECT * FROM meetings WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $meetingId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $meeting = $result->fetch_assoc();
    echo json_encode(["success" => true, "meeting" => $meeting]);
} else {
    echo json_encode(["success" => false, "error" => "Meeting not found"]);
}

$stmt->close();
$conn->close();

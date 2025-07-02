<?php
// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once 'db.php';

// Parse JSON
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid JSON"]);
    exit();
}

$date = $input['date'] ?? null;
$records = $input['records'] ?? [];

if (!$date || !is_array($records)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing data"]);
    exit();
}

foreach ($records as $rec) {
    $empId = intval($rec['employee_id']);
    $status = $rec['status'] === 'Present' ? 'Present' : 'Absent';

    // Check if attendance already exists for this employee and date
    $check = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
    $check->bind_param("is", $empId, $date);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "error" => "Attendance already saved for this employee on $date"
        ]);
        $check->close();
        $conn->close();
        exit();
    }
    $check->close();

    // Insert new record
    $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, status) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $empId, $date, $status);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
echo json_encode(["success" => true]);
<?php
// DEBUGGING (remove for production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require_once 'db.php';

// Read JSON input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data['id'], $data['status'], $data['name'])) {
    echo json_encode(["success" => false, "error" => "Missing required data"]);
    exit();
}

$id = intval($data['id']);
$status = strtolower(trim($data['status']));
$name = trim($data['name']);

// Validate status
if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(["success" => false, "error" => "Invalid status value"]);
    exit();
}

// 1. Update leave status in DB
$updateStmt = $conn->prepare("UPDATE leaves SET status = ? WHERE id = ?");
if (!$updateStmt) {
    echo json_encode(["success" => false, "error" => "Prepare failed: " . $conn->error]);
    exit();
}
$updateStmt->bind_param("si", $status, $id);

if (!$updateStmt->execute()) {
    echo json_encode(["success" => false, "error" => "Update failed: " . $updateStmt->error]);
    $updateStmt->close();
    exit();
}
$updateStmt->close();

// 2. Fetch employee email and leave details
$fetchStmt = $conn->prepare("
    SELECT e.email, l.startDate, l.endDate, l.reason 
    FROM employee e 
    JOIN leaves l ON l.name = e.username 
    WHERE l.id = ?
");
$fetchStmt->bind_param("i", $id);
$fetchStmt->execute();
$result = $fetchStmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "No matching employee or leave found"]);
    exit();
}

$row = $result->fetch_assoc();
$email = $row['email'];
$startDate = date("d M Y", strtotime($row['startDate']));
$endDate = date("d M Y", strtotime($row['endDate']));
$reason = $row['reason'];
$fetchStmt->close();

// 3. Send email notification
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'support@crm.swift2ai.com';
    $mail->Password   = 'Ranjeet@1810';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('support@crm.swift2ai.com', 'CRM Team');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Leave Request " . ucfirst($status);

    if ($status === 'approved') {
        $mail->Body = "
            <p>Dear <strong>$name</strong>,</p>
            <p>Your leave request has been <span style='color:green;font-weight:bold;'>approved</span>.</p>
            <p><strong>Details:</strong></p>
            <ul>
                <li>Reason: $reason</li>
                <li>From: $startDate</li>
                <li>To: $endDate</li>
            </ul>
            <p>Regards,<br>CRM Team</p>
        ";
    } else {
        $mail->Body = "
            <p>Dear <strong>$name</strong>,</p>
            <p>Your leave request has been <span style='color:red;font-weight:bold;'>rejected</span>.</p>
            <p><strong>Details:</strong></p>
            <ul>
                <li>Reason: $reason</li>
                <li>From: $startDate</li>
                <li>To: $endDate</li>
            </ul>
            <p>Please contact admin for more information.</p>
            <p>Regards,<br>CRM Team</p>
        ";
    }

    $mail->send();
    echo json_encode(["success" => true, "message" => "Leave status updated and notification sent."]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "error" => "Status updated but email failed to send",
        "debug" => $mail->ErrorInfo
    ]);
}

$conn->close();
?>
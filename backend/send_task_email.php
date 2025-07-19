<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Load PHPMailer
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Decode incoming JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (
    !isset($data['assignedTo']) ||
    !isset($data['subject']) ||
    !isset($data['priority']) ||
    !isset($data['startDate']) ||
    !isset($data['endDate'])
) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

// DB connection
$conn = new mysqli("localhost", "root", "", "crm");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

// Get assigned employee's email using 'username' (not 'name')
$assignedTo = $data['assignedTo'];
$stmt = $conn->prepare("SELECT email FROM employee WHERE username = ?");
$stmt->bind_param("s", $assignedTo);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    echo json_encode(["success" => false, "error" => "Employee not found"]);
    exit;
}

$to = $row['email'];

// Prepare and send email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = "smtp.hostinger.com"; // ✅ Update if different
    $mail->SMTPAuth = true;
    $mail->Username = "support@crm.swift2ai.com"; // ✅ Your SMTP email
    $mail->Password = "Ranjeet@1810";             // ✅ Your email password
    $mail->SMTPSecure = "tls";                    // Or 'ssl'
    $mail->Port = 587;                            // Or 465 for ssl

    $mail->setFrom("support@crm.swift2ai.com", "CRM Notification");
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = "New Task Assigned: " . htmlspecialchars($data['subject']);

    $mail->Body = "
        <h2>You've been assigned a new task</h2>
        <p><strong>Subject:</strong> " . htmlspecialchars($data['subject']) . "</p>
        <p><strong>Priority:</strong> " . htmlspecialchars($data['priority']) . "</p>
        <p><strong>Start Date:</strong> " . htmlspecialchars($data['startDate']) . "</p>
        <p><strong>End Date:</strong> " . htmlspecialchars($data['endDate']) . "</p>
        <p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($data['description'] ?? '')) . "</p>
    ";

    $mail->send();

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Mailer Error: " . $mail->ErrorInfo]);
}
?>

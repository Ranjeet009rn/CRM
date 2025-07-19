<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// DB Connection
require_once __DIR__ . '/db.php';

// Load PHPMailer
require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get POST input
$data = json_decode(file_get_contents("php://input"), true);

$firstName = $data['firstName'] ?? '';
$lastName = $data['lastName'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';

$message = $data['message'] ?? '';

// Validate input
if (!$firstName || !$lastName || !$email || !$message) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// ✅ Insert into enquiry table
$stmt = $conn->prepare("INSERT INTO enquiry (first_name, last_name, email, phone, message) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $firstName, $lastName, $email, $phone,$message);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Database insert failed: ' . $stmt->error]);
    exit;
}

// ✅ Send email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->SMTPDebug = 0; // Change to 2 if debugging
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@crm.swift2ai.com';
    $mail->Password = 'Ranjeet@1810';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('support@crm.swift2ai.com', 'CRM System');
    $mail->addAddress($email);

    $mail->isHTML(true);
   $mail->Subject = "Thank You for Contacting Us, $firstName";
    // Use a more structured HTML email body
$mail->Body = "
    <div style='font-family: Arial, sans-serif; font-size: 15px; color: #333; line-height: 1.6;'>
        <p>Dear <strong>$firstName $lastName</strong>,</p>

        <p>Thank you for getting in touch with us. We’ve received your message and our team will review it shortly.</p>

        <p><strong>Here’s a summary of what you submitted:</strong></p>
        <ul style='margin: 10px 0; padding-left: 20px;'>
         
            <li><strong>Phone:</strong> " . ($phone ?: 'Not provided') . "</li>
        </ul>

        <p><strong>Your Message:</strong></p>
        <blockquote style='border-left: 4px solid #ccc; padding-left: 10px; color: #555;'>$message</blockquote>

        <p>We appreciate your interest and will respond as soon as possible.</p>

        <p>Best regards,<br><strong>CRM Support Team</strong><br>
        <a href='mailto:support@crm.swift2ai.com'>support@crm.swift2ai.com</a></p>
    </div>
";


    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Mailer Error: ' . $mail->ErrorInfo]);
}

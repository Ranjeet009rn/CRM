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

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$customMessage = $data['message'] ?? '';

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Missing email']);
    exit;
}

// Generate 6-digit OTP
$otp = rand(100000, 999999);

// ✅ Check if it's just a welcome message (contains "congratulations")
$isWelcomeOnly = stripos($customMessage, 'congratulations') !== false;

// ✅ Build email body
if ($customMessage) {
    $bodyMessage = $isWelcomeOnly
        ? "<p>{$customMessage}</p>"  // 🔒 No OTP shown in this case
        : "<p>{$customMessage}<br><strong>OTP: $otp</strong></p>";
} else {
    $bodyMessage = "<p>👋 Welcome to <strong>CRM Software</strong>!<br>Your One-Time Password (OTP) is: <strong>$otp</strong></p>";
}

// Send email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@crm.swift2ai.com';
    $mail->Password = 'Ranjeet@1810'; // ⚠️ Secure this in production
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('support@crm.swift2ai.com', 'CRM System');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = $isWelcomeOnly ? '🎉 Welcome to CRM Team' : 'CRM OTP Verification';
    $mail->Body = $bodyMessage;

    $mail->send();

    // ✅ Response — include OTP only if relevant
    if ($isWelcomeOnly) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => true, 'otp' => "$otp"]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}

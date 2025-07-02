<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Load PHPMailer manually
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Read incoming JSON
$data = json_decode(file_get_contents("php://input"), true);
$to = $data['to'] ?? '';
$subject = $data['subject'] ?? '';
$message = $data['message'] ?? '';

if (!$to || !$subject || !$message) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Setup PHPMailer
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // Or your SMTP host
    $mail->SMTPAuth = true;
    $mail->Username = 'support@crm.swift2ai.com'; // Replace with your Gmail
    $mail->Password = 'Ranjeet@1810';    // Replace with Gmail App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('support@crm.swift2ai.com', 'CRM System');
    $mail->addAddress($to);

    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body    = $message;

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}
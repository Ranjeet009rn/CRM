<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Load PHPMailer
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get POST JSON input
$data = json_decode(file_get_contents("php://input"), true);
$to = $data['to'] ?? '';
$subject = $data['subject'] ?? '';
$message = $data['message'] ?? '';

if (!$to || !$subject || !$message) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$mail = new PHPMailer(true);

try {
    // SMTP Setup
    $mail->isSMTP();
    $mail->SMTPDebug = 2; // 💥 VERY IMPORTANT: Enable debug output
    $mail->Debugoutput = function ($str, $level) {
        file_put_contents('mail_log.txt', $str . PHP_EOL, FILE_APPEND);
    };

    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@crm.swift2ai.com';
    $mail->Password = 'Ranjeet@1810'; // Double-check this
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

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

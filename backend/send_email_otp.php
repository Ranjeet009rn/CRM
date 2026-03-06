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

// Function to create professional OTP email template
function createOTPEmailTemplate($subject, $message, $otp = null, $isWelcomeOnly = false) {
    $template = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 15px; 
                overflow: hidden; 
                box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 40px 30px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: 700; 
            }
            .header .subtitle { 
                margin: 15px 0 0 0; 
                opacity: 0.95; 
                font-size: 16px; 
            }
            .content { 
                padding: 40px 30px; 
            }
            .otp-box { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px; 
                margin: 25px 0; 
                border-radius: 10px; 
                text-align: center; 
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); 
            }
            .otp-code { 
                font-size: 36px; 
                font-weight: 700; 
                letter-spacing: 8px; 
                margin: 15px 0; 
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3); 
            }
            .message { 
                background: #f8f9fa; 
                border-left: 4px solid #667eea; 
                padding: 25px; 
                margin: 25px 0; 
                border-radius: 0 8px 8px 0; 
                font-size: 16px; 
            }
            .footer { 
                background: #2c3e50; 
                color: white; 
                padding: 30px; 
                text-align: center; 
                font-size: 14px; 
            }
            .footer a { 
                color: #3498db; 
                text-decoration: none; 
            }
            .security-note { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                font-size: 14px; 
            }
            .contact-info { 
                margin: 20px 0; 
                padding: 20px; 
                background: #ecf0f1; 
                border-radius: 8px; 
            }
            .contact-info h3 { 
                margin: 0 0 15px 0; 
                color: #2c3e50; 
                font-size: 18px; 
            }
            .contact-info p { 
                margin: 8px 0; 
                color: #555; 
            }
            .welcome-message { 
                background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%); 
                color: white; 
                padding: 30px; 
                border-radius: 10px; 
                text-align: center; 
                margin: 20px 0; 
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>🏢 CRM System</h1>
                <p class="subtitle">Professional Customer Relationship Management</p>
            </div>
            
            <div class="content">';
    
    if ($isWelcomeOnly) {
        $template .= '
                <div class="welcome-message">
                    <h2>🎉 Welcome to CRM Team!</h2>
                    <p style="font-size: 18px; margin: 15px 0;">' . nl2br(htmlspecialchars($message)) . '</p>
                </div>';
    } else {
        $template .= '
                <div class="message">
                    <h2>🔐 Verification Required</h2>
                    <p>' . nl2br(htmlspecialchars($message)) . '</p>
                </div>
                
                <div class="otp-box">
                    <h3>Your Verification Code</h3>
                    <div class="otp-code">' . ($otp ? $otp : 'XXXXXX') . '</div>
                    <p style="margin: 0; opacity: 0.9;">Enter this code to complete your verification</p>
                </div>
                
                <div class="security-note">
                    <strong>🔒 Security Notice:</strong><br>
                    • This code will expire in 3 minutes<br>
                    • Never share this code with anyone<br>
                    • If you didn\'t request this code, please ignore this email
                </div>';
    }
    
    $template .= '
                <div class="contact-info">
                    <h3>📞 Contact Information</h3>
                    <p><strong>Phone:</strong> +91-XXXXXXXXXX</p>
                    <p><strong>Email:</strong> <a href="mailto:support@ingavalebusinesssolution.in">support@ingavalebusinesssolution.in</a></p>
                    <p><strong>Website:</strong> <a href="https://www.ingavalebusinesssolution.in">www.ingavalebusinesssolution.in</a></p>
                </div>
            </div>
            
            <div class="footer">
                <p>© 2024 CRM System. All rights reserved.</p>
                <p>Professional CRM Solutions | <a href="mailto:support@ingavalebusinesssolution.in">Support</a></p>
            </div>
        </div>
    </body>
    </html>';
    
    return $template;
}

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
        ? $customMessage
        : $customMessage . "\n\nYour One-Time Password (OTP) is: $otp";
} else {
    $bodyMessage = "Welcome to CRM Software! Your One-Time Password (OTP) is: $otp";
}

// Send email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@ingavalebusinesssolution.in';
    $mail->Password = 'Ranjeet@1810';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('support@ingavalebusinesssolution.in', 'CRM System');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = $isWelcomeOnly ? '🎉 Welcome to CRM Team' : '🔐 CRM Verification Code';
    $mail->Body = createOTPEmailTemplate($mail->Subject, $bodyMessage, $otp, $isWelcomeOnly);

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

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

// Function to create professional email template
function createEmailTemplate($subject, $message, $type = 'general') {
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
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
                font-weight: 600; 
            }
            .header .subtitle { 
                margin: 10px 0 0 0; 
                opacity: 0.9; 
                font-size: 14px; 
            }
            .content { 
                padding: 40px 30px; 
            }
            .message { 
                background: #f8f9fa; 
                border-left: 4px solid #667eea; 
                padding: 20px; 
                margin: 20px 0; 
                border-radius: 0 5px 5px 0; 
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
            .contact-info { 
                margin: 20px 0; 
                padding: 15px; 
                background: #ecf0f1; 
                border-radius: 5px; 
            }
            .contact-info h3 { 
                margin: 0 0 10px 0; 
                color: #2c3e50; 
                font-size: 16px; 
            }
            .contact-info p { 
                margin: 5px 0; 
                color: #555; 
            }
            .highlight { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 15px 0; 
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>🏢 CRM System</h1>
                <p class="subtitle">Professional Customer Relationship Management</p>
            </div>
            
            <div class="content">
                <div class="message">
                    ' . nl2br(htmlspecialchars($message)) . '
                </div>
                
                <div class="contact-info">
                    <h3>📞 Contact Information</h3>
                    <p><strong>Phone:</strong> +91-XXXXXXXXXX</p>
                    <p><strong>Email:</strong> <a href="mailto:support@ingavalebusinesssolution.in">support@ingavalebusinesssolution.in</a></p>
                    <p><strong>Website:</strong> <a href="https://www.ingavalebusinesssolution.in">www.ingavalebusinesssolution.in</a></p>
                </div>
                
                <div class="highlight">
                    <strong>💡 Need Help?</strong><br>
                    Our support team is available 24/7 to assist you with any questions or concerns.
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
    $mail->SMTPDebug = 0; // Set to 2 for debugging
    $mail->Debugoutput = function ($str, $level) {
        file_put_contents('mail_log.txt', $str . PHP_EOL, FILE_APPEND);
    };

    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@ingavalebusinesssolution.in';
    $mail->Password = 'Ranjeet@1810';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('support@ingavalebusinesssolution.in', 'CRM System');
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = createEmailTemplate($subject, $message);

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $mail->ErrorInfo]);
}

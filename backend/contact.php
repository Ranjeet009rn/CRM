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
// Optional new fields
$district = $data['district'] ?? '';
$loanType = $data['loanType'] ?? ($data['loan_type'] ?? '');
$referenceThrough = $data['referenceThrough'] ?? ($data['reference_through'] ?? '');
$amount = isset($data['amount']) ? trim((string)$data['amount']) : '';
// Optional created_by (for CRM users like Sales Officer)
$createdBy = isset($data['created_by']) && $data['created_by'] !== '' ? trim((string)$data['created_by']) : null;

// Validate input
if (!$firstName || !$lastName || !$email || !$message) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Helper to check if column exists in table
$hasColumn = function(mysqli $conn, string $table, string $column): bool {
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $dbRes = $conn->query("SELECT DATABASE() as db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow ? $conn->real_escape_string($dbRow['db']) : '';
    if (!$dbName) return false;
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".$dbName."' AND TABLE_NAME='".$tableEsc."' AND COLUMN_NAME='".$columnEsc."' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
};

// Build dynamic insert depending on available columns
$cols = [
  'first_name' => $firstName,
  'last_name' => $lastName,
  'email' => $email,
  'phone' => $phone,
  'message' => $message,
];

// Optional created_by (who generated enquiry)
if ($hasColumn($conn, 'enquiry', 'created_by')) {
  $cols['created_by'] = $createdBy !== null ? $createdBy : 'Website';
}
if ($district !== '' && $hasColumn($conn, 'enquiry', 'district')) {
  $cols['district'] = $district;
}
if ($loanType !== '' && ($hasColumn($conn, 'enquiry', 'loan_type') || $hasColumn($conn, 'enquiry', 'loanType'))) {
  // Prefer snake_case column if present
  if ($hasColumn($conn, 'enquiry', 'loan_type')) $cols['loan_type'] = $loanType; else $cols['loanType'] = $loanType;
}
if ($referenceThrough !== '' && ($hasColumn($conn, 'enquiry', 'reference_through') || $hasColumn($conn, 'enquiry', 'referenceThrough'))) {
  if ($hasColumn($conn, 'enquiry', 'reference_through')) $cols['reference_through'] = $referenceThrough; else $cols['referenceThrough'] = $referenceThrough;
}
// Optional amount (numeric)
if ($amount !== '' && $hasColumn($conn, 'enquiry', 'amount')) {
  $cols['amount'] = preg_replace('/\D/', '', $amount);
}

$columnsSql = implode(', ', array_keys($cols));
$placeholders = implode(', ', array_fill(0, count($cols), '?'));
$stmt = $conn->prepare("INSERT INTO enquiry ($columnsSql) VALUES ($placeholders)");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
    exit;
}
$types = str_repeat('s', count($cols));
$values = array_values($cols);
$stmt->bind_param($types, ...$values);
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
    $mail->Username = 'support@ingavalebusinesssolution.in';
    $mail->Password = 'Ranjeet@1810';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('support@ingavalebusinesssolution.in', 'CRM System');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Thank You for Contacting Us - CRM System";
    
    // Professional contact confirmation email template
    $mail->Body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Thank You for Contacting Us</title>
        <style>
            body { 
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f8f9fa; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 8px; 
                overflow: hidden; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            }
            .header { 
                background: #2c3e50; 
                color: white; 
                padding: 30px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
                font-weight: 600; 
            }
            .content { 
                padding: 30px; 
            }
            .thank-you-section { 
                background: #ecf0f1; 
                padding: 20px; 
                border-radius: 6px; 
                margin-bottom: 25px; 
                text-align: center; 
            }
            .thank-you-title { 
                color: #2c3e50; 
                font-size: 20px; 
                font-weight: 600; 
                margin: 0 0 10px 0; 
            }
            .thank-you-text { 
                color: #555; 
                margin: 0; 
                font-size: 16px; 
            }
            .message-summary { 
                background: #f8f9fa; 
                border: 1px solid #e9ecef; 
                border-radius: 6px; 
                padding: 20px; 
                margin: 20px 0; 
            }
            .summary-title { 
                color: #2c3e50; 
                font-size: 18px; 
                font-weight: 600; 
                margin: 0 0 15px 0; 
            }
            .summary-item { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 8px 0; 
                border-bottom: 1px solid #e9ecef; 
            }
            .summary-item:last-child { 
                border-bottom: none; 
            }
            .summary-label { 
                font-weight: 600; 
                color: #2c3e50; 
                font-size: 14px; 
            }
            .summary-value { 
                color: #555; 
                font-size: 14px; 
                text-align: right; 
            }
            .message-content { 
                background: white; 
                border: 1px solid #e9ecef; 
                border-radius: 6px; 
                padding: 15px; 
                margin: 15px 0; 
            }
            .message-label { 
                font-weight: 600; 
                color: #2c3e50; 
                margin-bottom: 8px; 
                font-size: 14px; 
            }
            .message-text { 
                color: #555; 
                line-height: 1.6; 
                font-size: 14px; 
                font-style: italic; 
            }
            .next-steps { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 15px; 
                border-radius: 6px; 
                margin: 15px 0; 
            }
            .next-steps h3 { 
                margin: 0 0 10px 0; 
                color: #856404; 
                font-size: 16px; 
            }
            .next-steps ul { 
                margin: 0; 
                padding-left: 20px; 
                color: #856404; 
            }
            .next-steps li { 
                margin: 5px 0; 
                font-size: 14px; 
            }
            .footer { 
                background: #2c3e50; 
                color: white; 
                padding: 20px; 
                text-align: center; 
                font-size: 12px; 
            }
            .footer a { 
                color: #3498db; 
                text-decoration: none; 
            }
            .contact-info { 
                margin: 15px 0; 
                padding: 15px; 
                background: #ecf0f1; 
                border-radius: 6px; 
            }
            .contact-info h3 { 
                margin: 0 0 10px 0; 
                color: #2c3e50; 
                font-size: 16px; 
            }
            .contact-info p { 
                margin: 5px 0; 
                color: #555; 
                font-size: 14px; 
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>CRM System</h1>
            </div>
            
            <div class="content">
                <div class="thank-you-section">
                    <h2 class="thank-you-title">Thank You for Contacting Us!</h2>
                    <p class="thank-you-text">Dear ' . htmlspecialchars($firstName . ' ' . $lastName) . ', we\'ve received your message and our team will review it shortly.</p>
                </div>
                
                <div class="message-summary">
                    <h3 class="summary-title">Message Summary</h3>
                    <div class="summary-item">
                        <span class="summary-label">Name</span>
                        <span class="summary-value">' . htmlspecialchars($firstName . ' ' . $lastName) . '</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Email</span>
                        <span class="summary-value">' . htmlspecialchars($email) . '</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Phone</span>
                        <span class="summary-value">' . htmlspecialchars($phone ?: 'Not provided') . '</span>
                    </div>
                </div>
                
                <div class="message-content">
                    <div class="message-label">Your Message</div>
                    <div class="message-text">' . nl2br(htmlspecialchars($message)) . '</div>
                </div>
                
                <div class="next-steps">
                    <h3>What Happens Next?</h3>
                    <ul>
                        <li>Our team will review your message within 24 hours</li>
                        <li>You\'ll receive a detailed response via email</li>
                        <li>If needed, we may contact you for additional information</li>
                    </ul>
                </div>
                
                <div class="contact-info">
                    <h3>Contact Information</h3>
                    <p><strong>Phone:</strong> +91-XXXXXXXXXX</p>
                    <p><strong>Email:</strong> <a href="mailto:support@ingavalebusinesssolution.in">support@ingavalebusinesssolution.in</a></p>
                    <p><strong>Website:</strong> <a href="https://www.ingavalebusinesssolution.in">www.ingavalebusinesssolution.in</a></p>
                </div>
            </div>
            
            <div class="footer">
                <p>© 2024 CRM System. All rights reserved.</p>
                <p>Professional CRM Solutions</p>
            </div>
        </div>
    </body>
    </html>';

    $mail->send();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Mailer Error: ' . $mail->ErrorInfo]);
}

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

// Function to create professional leave status email template
function createLeaveStatusEmailTemplate($status, $name, $reason, $startDate, $endDate) {
    $statusColor = $status === 'approved' ? '#27ae60' : '#e74c3c';
    $statusIcon = $status === 'approved' ? '✅' : '❌';
    $statusText = ucfirst($status);
    
    $template = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Leave Request ' . $statusText . '</title>
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
            .status-card { 
                background: ' . $statusColor . '; 
                color: white; 
                padding: 30px; 
                border-radius: 12px; 
                text-align: center; 
                margin: 25px 0; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
            }
            .status-icon { 
                font-size: 48px; 
                margin-bottom: 15px; 
            }
            .status-title { 
                font-size: 24px; 
                font-weight: 700; 
                margin: 0 0 10px 0; 
            }
            .status-subtitle { 
                font-size: 16px; 
                opacity: 0.9; 
                margin: 0; 
            }
            .leave-details { 
                background: #f8f9fa; 
                border: 2px solid #e9ecef; 
                border-radius: 12px; 
                padding: 25px; 
                margin: 25px 0; 
            }
            .detail-item { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 12px 0; 
                border-bottom: 1px solid #e9ecef; 
            }
            .detail-item:last-child { 
                border-bottom: none; 
            }
            .detail-label { 
                font-weight: 600; 
                color: #2c3e50; 
                font-size: 16px; 
            }
            .detail-value { 
                color: #555; 
                font-size: 16px; 
                text-align: right; 
            }
            .message-box { 
                background: #ecf0f1; 
                border-left: 4px solid ' . $statusColor . '; 
                padding: 20px; 
                border-radius: 0 8px 8px 0; 
                margin: 20px 0; 
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
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>📅 Leave Request Update</h1>
                <p class="subtitle">Your leave request has been processed</p>
            </div>
            
            <div class="content">
                <div class="status-card">
                    <div class="status-icon">' . $statusIcon . '</div>
                    <h2 class="status-title">Leave Request ' . $statusText . '</h2>
                    <p class="status-subtitle">Dear ' . htmlspecialchars($name) . ', your leave request has been ' . $statusText . '</p>
                </div>
                
                <div class="leave-details">
                    <h3 style="margin: 0 0 20px 0; color: #2c3e50; font-size: 20px;">📋 Leave Details</h3>
                    <div class="detail-item">
                        <span class="detail-label">📝 Reason</span>
                        <span class="detail-value">' . htmlspecialchars($reason) . '</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">📅 Start Date</span>
                        <span class="detail-value">' . htmlspecialchars($startDate) . '</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">📅 End Date</span>
                        <span class="detail-value">' . htmlspecialchars($endDate) . '</span>
                    </div>
                </div>';
    
    if ($status === 'rejected') {
        $template .= '
                <div class="message-box">
                    <h4 style="margin: 0 0 10px 0; color: #e74c3c;">⚠️ Additional Information</h4>
                    <p style="margin: 0; color: #555;">Please contact your supervisor or HR department for more information about this decision.</p>
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

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'support@ingavalebusinesssolution.in';
    $mail->Password   = 'Ranjeet@1810';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('support@ingavalebusinesssolution.in', 'CRM HR Team');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "📅 Leave Request " . ucfirst($status);
    $mail->Body = createLeaveStatusEmailTemplate($status, $name, $reason, $startDate, $endDate);

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
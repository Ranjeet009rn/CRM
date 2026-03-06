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

// Function to create professional task assignment email template
function createTaskEmailTemplate($subject, $taskData) {
    $priorityColor = '';
    $priorityIcon = '';
    
    switch(strtolower($taskData['priority'])) {
        case 'high':
            $priorityColor = '#e74c3c';
            $priorityIcon = '🔴';
            break;
        case 'medium':
            $priorityColor = '#f39c12';
            $priorityIcon = '🟡';
            break;
        case 'low':
            $priorityColor = '#27ae60';
            $priorityIcon = '🟢';
            break;
        default:
            $priorityColor = '#3498db';
            $priorityIcon = '🔵';
    }
    
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
                max-width: 650px; 
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
            .task-card { 
                background: #f8f9fa; 
                border: 2px solid #e9ecef; 
                border-radius: 12px; 
                padding: 30px; 
                margin: 25px 0; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            }
            .task-header { 
                display: flex; 
                align-items: center; 
                margin-bottom: 20px; 
                padding-bottom: 15px; 
                border-bottom: 2px solid #e9ecef; 
            }
            .task-icon { 
                font-size: 32px; 
                margin-right: 15px; 
            }
            .task-title { 
                font-size: 24px; 
                font-weight: 700; 
                color: #2c3e50; 
                margin: 0; 
            }
            .priority-badge { 
                background: ' . $priorityColor . '; 
                color: white; 
                padding: 8px 16px; 
                border-radius: 20px; 
                font-size: 14px; 
                font-weight: 600; 
                margin-left: auto; 
            }
            .task-details { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 20px; 
                margin: 20px 0; 
            }
            .detail-item { 
                background: white; 
                padding: 15px; 
                border-radius: 8px; 
                border-left: 4px solid #667eea; 
            }
            .detail-label { 
                font-weight: 600; 
                color: #2c3e50; 
                margin-bottom: 5px; 
                font-size: 14px; 
            }
            .detail-value { 
                color: #555; 
                font-size: 16px; 
            }
            .description-box { 
                background: white; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border: 1px solid #e9ecef; 
            }
            .description-label { 
                font-weight: 600; 
                color: #2c3e50; 
                margin-bottom: 10px; 
                font-size: 16px; 
            }
            .description-text { 
                color: #555; 
                line-height: 1.8; 
                font-size: 15px; 
            }
            .action-button { 
                display: inline-block; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 25px; 
                font-weight: 600; 
                margin: 20px 0; 
                text-align: center; 
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); 
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
            @media (max-width: 600px) {
                .task-details { 
                    grid-template-columns: 1fr; 
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>📋 Task Assignment</h1>
                <p class="subtitle">You have been assigned a new task</p>
            </div>
            
            <div class="content">
                <div class="task-card">
                    <div class="task-header">
                        <div class="task-icon">📝</div>
                        <h2 class="task-title">' . htmlspecialchars($taskData['subject']) . '</h2>
                        <div class="priority-badge">' . $priorityIcon . ' ' . htmlspecialchars(ucfirst($taskData['priority'])) . ' Priority</div>
                    </div>
                    
                    <div class="task-details">
                        <div class="detail-item">
                            <div class="detail-label">📅 Start Date</div>
                            <div class="detail-value">' . htmlspecialchars($taskData['startDate']) . '</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">⏰ End Date</div>
                            <div class="detail-value">' . htmlspecialchars($taskData['endDate']) . '</div>
                        </div>
                    </div>
                    
                    <div class="description-box">
                        <div class="description-label">📄 Task Description</div>
                        <div class="description-text">' . nl2br(htmlspecialchars($taskData['description'] ?? 'No description provided')) . '</div>
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="#" class="action-button">✅ Mark as Started</a>
                    </div>
                </div>
                
                <div class="contact-info">
                    <h3>📞 Need Help?</h3>
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

// DB connection via centralized configuration (db.php)
require_once __DIR__ . '/db.php';

// Get assigned employee's email using 'username' (not 'name')
$assignedTo = $data['assignedTo'];
$stmt = $conn->prepare("SELECT email FROM employee WHERE username = ?");
$stmt->bind_param("s", $assignedTo);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    echo json_encode(["success" => false, "error" => "Employee not found"]);
    $stmt->close();
    $conn->close();
    exit;
}

$to = $row['email'];
// Close DB resources before sending email
$stmt->close();
$conn->close();

// Prepare and send email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = "smtp.hostinger.com";
    $mail->SMTPAuth = true;
    $mail->Username = "support@ingavalebusinesssolution.in";
    $mail->Password = "Ranjeet@1810";
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;

    $mail->setFrom("support@ingavalebusinesssolution.in", "CRM Task Manager");
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = "📋 New Task Assigned: " . htmlspecialchars($data['subject']);
    $mail->Body = createTaskEmailTemplate($mail->Subject, $data);

    $mail->send();

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Mailer Error: " . $mail->ErrorInfo]);
}
?>

<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method Not Allowed"]);
    exit();
}

require_once 'db.php';
require_once 'vendor/autoload.php'; // Make sure you have TCPDF installed

use TCPDF as TCPDF;

// Get customer data
$input = json_decode(file_get_contents('php://input'), true);
$customerId = $input['customer_id'] ?? null;

if (!$customerId) {
    echo json_encode(["success" => false, "error" => "Customer ID is required"]);
    exit();
}

// Fetch customer data
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Customer not found"]);
    exit();
}

$customer = $result->fetch_assoc();

// Create PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('CRM System');
$pdf->SetAuthor('Professional Financial Solutions');
$pdf->SetTitle('Loan Approval Certificate');
$pdf->SetSubject('Loan Approval Certificate for ' . $customer['name']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(20, 20, 20);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Create the certificate content
$html = '
<style>
    .header { text-align: center; margin-bottom: 30px; }
    .title { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
    .subtitle { font-size: 14px; color: #7f8c8d; }
    .certificate-box { 
        border: 3px solid #3498db; 
        padding: 30px; 
        margin: 20px 0; 
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    .customer-name { 
        font-size: 28px; 
        font-weight: bold; 
        color: #2c3e50; 
        text-align: center; 
        margin: 20px 0; 
    }
    .section { margin: 20px 0; }
    .section-title { 
        font-size: 16px; 
        font-weight: bold; 
        color: #2c3e50; 
        border-bottom: 2px solid #3498db; 
        padding-bottom: 5px; 
        margin-bottom: 15px; 
    }
    .detail-row { 
        display: flex; 
        justify-content: space-between; 
        margin: 8px 0; 
        padding: 5px 0; 
    }
    .detail-label { font-weight: bold; color: #34495e; }
    .detail-value { color: #2c3e50; }
    .highlight { 
        background-color: #3498db; 
        color: white; 
        padding: 10px; 
        border-radius: 5px; 
        text-align: center; 
        margin: 15px 0; 
        font-weight: bold; 
    }
    .footer { 
        text-align: center; 
        margin-top: 30px; 
        color: #7f8c8d; 
        font-size: 12px; 
    }
    .stamp { 
        text-align: center; 
        margin: 20px 0; 
    }
    .stamp-text { 
        border: 2px solid #e74c3c; 
        color: #e74c3c; 
        padding: 10px 20px; 
        display: inline-block; 
        font-weight: bold; 
        transform: rotate(-5deg); 
    }
</style>

<div class="header">
    <div class="title">🏢 PROFESSIONAL FINANCIAL SOLUTIONS</div>
    <div class="subtitle">Your Trusted Financial Partner</div>
</div>

<div class="certificate-box">
    <div style="text-align: center; margin-bottom: 30px;">
        <div style="font-size: 32px; color: #27ae60; margin-bottom: 10px;">🎉</div>
        <div style="font-size: 20px; font-weight: bold; color: #2c3e50;">LOAN APPROVAL CERTIFICATE</div>
    </div>
    
    <div class="customer-name">' . htmlspecialchars($customer['name']) . '</div>
    
    <div style="text-align: center; margin: 20px 0; font-size: 16px; color: #7f8c8d;">
        This is to certify that your loan application has been <strong>APPROVED</strong> and processed successfully.
    </div>
    
    <div class="section">
        <div class="section-title">📋 LOAN DETAILS</div>
        <div class="detail-row">
            <span class="detail-label">Loan Amount:</span>
            <span class="detail-value">₹' . number_format($customer['loan_amount']) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Interest Rate:</span>
            <span class="detail-value">' . $customer['interest_rate'] . '%</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Loan Tenure:</span>
            <span class="detail-value">' . ($customer['loan_tenure'] ?? '12') . ' months</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Monthly EMI:</span>
            <span class="detail-value">₹' . number_format($customer['emi']) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Approval Date:</span>
            <span class="detail-value">' . date('F j, Y', strtotime($customer['created_at'])) . '</span>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">👤 CUSTOMER INFORMATION</div>
        <div class="detail-row">
            <span class="detail-label">Full Name:</span>
            <span class="detail-value">' . htmlspecialchars($customer['name']) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Phone Number:</span>
            <span class="detail-value">' . htmlspecialchars($customer['phone']) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email Address:</span>
            <span class="detail-value">' . htmlspecialchars($customer['email']) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Address:</span>
            <span class="detail-value">' . htmlspecialchars($customer['address']) . '</span>
        </div>
    </div>
    
    <div class="highlight">
        🎯 NEXT STEPS: Our team will contact you within 24 hours to complete the documentation process.
    </div>
    
    <div class="stamp">
        <div class="stamp-text">APPROVED</div>
    </div>
</div>

<div class="footer">
    <div style="margin-bottom: 10px;">
        <strong>Professional Financial Solutions</strong><br>
        📞 Contact: +91-XXXXXXXXXX | 📧 Email: support@ingavalebusinesssolution.in<br>
        🌐 Website: www.ingavalebusinesssolution.in
    </div>
    <div style="font-size: 10px; color: #95a5a6;">
        This certificate is computer generated and does not require a physical signature.<br>
        Generated on: ' . date('F j, Y \a\t g:i A') . '
    </div>
</div>
';

// Print HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Generate unique filename
$filename = 'loan_approval_' . $customer['id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
$filepath = __DIR__ . '/uploads/pdfs/' . $filename;
$relativePath = 'uploads/pdfs/' . $filename;

// Create uploads/pdfs directory if it doesn't exist
$uploadDir = __DIR__ . '/uploads/pdfs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Output PDF to file
$pdf->Output($filepath, 'F');

// Return success with download link
echo json_encode([
    "success" => true,
    "message" => "PDF generated successfully",
    "download_url" => "http://localhost/CRM/CRM/backend/" . $relativePath,
    "filename" => $filename
]);

$stmt->close();
$conn->close();
?> 
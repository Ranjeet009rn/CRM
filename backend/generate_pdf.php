<?php
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");

$data = json_decode(file_get_contents("php://input"), true);
$lead = $data['lead'] ?? null;

if (!$lead) {
  echo json_encode(["success" => false, "error" => "Lead data missing"]);
  exit;
}

// Format HTML content
$html = '
<style>
  body { font-family: Arial, sans-serif; }
  h2 { color: #5d49f8; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
  .section { margin-top: 20px; }
</style>

<h2>🎉 Loan Approval Letter</h2>
<p>Dear ' . htmlspecialchars($lead["name"]) . ',</p>
<p>We are pleased to inform you that your loan has been <strong>approved</strong>.</p>

<div class="section">
  <h4>Lead Details</h4>
  <table>
    <tr><th>Phone</th><td>' . $lead["phone"] . '</td></tr>
    <tr><th>Email</th><td>' . $lead["email"] . '</td></tr>
    <tr><th>Address</th><td>' . nl2br($lead["address"]) . '</td></tr>
    <tr><th>Source</th><td>' . $lead["source"] . '</td></tr>
    <tr><th>Assigned To</th><td>' . $lead["assigned_to"] . '</td></tr>
    <tr><th>Created Date</th><td>' . explode(" ", $lead["cd_date"] ?? $lead["created_at"])[0] . '</td></tr>
  </table>
</div>

<div class="section">
  <h4>Loan Info</h4>
  <table>
    <tr><th>Loan Amount</th><td>₹' . $lead["loan_amount"] . '</td></tr>
    <tr><th>Interest Rate</th><td>' . ($lead["interest_rate"] ?? "10%") . '</td></tr>
    <tr><th>Tenure</th><td>' . ($lead["loan_tenure"] ?? "12") . ' months</td></tr>
    <tr><th>EMI</th><td>₹' . $lead["emi"] . '</td></tr>
    <tr><th>Status</th><td>' . $lead["status"] . '</td></tr>
  </table>
</div>

<p class="section">Regards,<br><strong>CRM Team</strong></p>
';

// Generate PDF
$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();

$outputDir = __DIR__ . '/pdfs';
if (!is_dir($outputDir)) mkdir($outputDir);

$filename = "loan_" . $lead["id"] . ".pdf";
file_put_contents("$outputDir/$filename", $pdf->output());

echo json_encode([
  "success" => true,
  "pdf_url" => "http://localhost/CRM/CRM/backend/pdfs/$filename"
]);

<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

// Support fallback 'phone' key
if (empty($data['mobile']) && !empty($data['phone'])) {
    $data['mobile'] = $data['phone'];
}

$apikey = "2660c91538894998818c9391509a23d0"; // ✅ Use your Opus API key
$mobile = $data['mobile'] ?? '';
$customMessage = $data['message'] ?? '';

if (!$mobile) {
    echo json_encode(['success' => false, 'error' => 'Mobile number is required']);
    exit;
}

// Generate a 6-digit OTP
$otp = rand(100000, 999999);

// Build message
$msgText = $customMessage
    ? $customMessage . " $otp"
    : "👋 Welcome to CRM Software! Your OTP is: $otp";

$msg = urlencode($msgText);

// Send via Opus API
$url = "https://api.opustechnology.in/wapp/v2/api/send?apikey=$apikey&mobile=$mobile&msg=$msg";

$response = file_get_contents($url);

// Send result back
echo json_encode([
    'success' => true,
    'otp' => $otp,
    'sent' => true,
    'message' => 'OTP sent successfully via WhatsApp',
    'api_response' => $response
]);

<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"));

$phone = $data->phone ?? '';
$message = $data->message ?? '';

if (!$phone || !$message) {
  echo json_encode(["success" => false, "error" => "Phone or message missing"]);
  exit;
}

// ✅ WhatsApp API integration (use real API like Twilio, Gupshup, Interakt, etc.)
$encodedMessage = urlencode($message);
$url = "https://wa.me/91{$phone}?text={$encodedMessage}"; // or your actual API call

// For testing, we return the URL instead of sending
echo json_encode([
  "success" => true,
  "msg" => "WhatsApp message triggered",
  "link" => $url
]);

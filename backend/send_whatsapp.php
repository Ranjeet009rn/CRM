<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

// ✅ Fix: Support 'phone' as well
if (empty($data['mobile']) && !empty($data['phone'])) {
    $data['mobile'] = $data['phone'];
}

$apikey = "2759f0e9c0ad4571a9c99c8cdc47b75d"; // replace with your key
$mobile = $data['mobile'] ?? '';
$msg = urlencode($data['message'] ?? '');

$url = "https://api.opustechnology.in/wapp/v2/api/send?apikey=$apikey&mobile=$mobile&msg=$msg";

$response = file_get_contents($url);
echo $response;

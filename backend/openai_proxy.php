<?php
require_once __DIR__ . '/config.php';
api_send_cors_headers();
api_handle_options_and_exit();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
if ($method !== 'POST') {
  api_json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = []; }

$prompt = $data['prompt'] ?? '';
$model  = $data['model']  ?? OPENAI_MODEL;
if (!$prompt) {
  api_json_response(['success' => false, 'error' => 'Missing prompt'], 400);
}

if (!OPENAI_API_KEY || OPENAI_API_KEY === 'YOUR_OPENAI_API_KEY') {
  api_json_response(['success' => false, 'error' => 'OPENAI_API_KEY not configured on server'], 500);
}

$payload = [
  'model' => $model,
  'messages' => [
    ['role' => 'user', 'content' => $prompt]
  ],
  'temperature' => 0.2,
];

$ch = curl_init(OPENAI_BASE_URL . '/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . OPENAI_API_KEY,
    'Content-Type: application/json',
  ],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($payload),
  CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$err      = curl_error($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
  api_json_response(['success' => false, 'error' => 'cURL error: ' . $err], 502);
}

$out = json_decode($response, true);
if (!is_array($out)) {
  api_json_response(['success' => false, 'error' => 'Invalid response from OpenAI', 'raw' => $response], 502);
}

$answer = $out['choices'][0]['message']['content'] ?? '';
api_json_response(['success' => true, 'answer' => $answer, 'raw' => $out]);

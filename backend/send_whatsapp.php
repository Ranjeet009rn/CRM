<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data['mobile']) && !empty($data['phone'])) { $data['mobile'] = $data['phone']; }

function formatWhatsAppMessage($message) {
    // Skip formatting for payment reminders (they already have proper format)
    if (stripos($message, 'Payment Reminder') !== false || stripos($message, 'pending payment') !== false) {
        return $message;
    }
    
    if (strpos($message, '[CRM]') === false) {
        $message = "🏢 *CRM System*\n\n" . $message;
    }
    $message .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📞 *Contact:* +91-XXXXXXXXXX\n📧 *Email:* support@ingavalebusinesssolution.in\n🌐 *Website:* www.ingavalebusinesssolution.in\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n💼 *Professional CRM Solutions*";
    return $message;
}

$apikey     = "001fe1245a70484fbcd1c9f5f5f22dd9"; // replace with your key
$mobileRaw  = $data['mobile'] ?? '';
$msg        = $data['message'] ?? '';
$media_url  = $data['media_url'] ?? null;
$image_path = $data['image_path'] ?? null;

$digits = preg_replace('/\D+/', '', (string)$mobileRaw);
$mobile = strlen($digits) >= 10 ? ('91' . substr($digits, -10)) : $digits;
$isLocalUrl = $media_url && (strpos($media_url, 'localhost') !== false || strpos($media_url, '127.0.0.1') !== false);

function curl_exec_json($ch) {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    // TEMP (local dev): relax SSL validation to avoid missing CA bundle
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    return [$response, $httpCode, $curlErr];
}

// Simple logger for debugging WhatsApp API behaviour in production
function logWhatsAppApi($context, $mobile, $message, $apiResponse, $rawResponse = null, $extra = []) {
    $logFile = __DIR__ . '/whatsapp_api_log.txt';
    $entry = [
        'timestamp'     => date('Y-m-d H:i:s'),
        'context'       => $context,   // e.g. text_send, media_multipart, media_url_fallback
        'mobile_raw'    => $mobile,
        'message'       => $message,
        'api_response'  => $apiResponse,
        'raw_response'  => $rawResponse,
        'extra'         => $extra,
    ];
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
}

// Try to send file via multipart first (so recipients get the actual image)
if ($image_path && file_exists($image_path)) {
    $caption = $msg ?: 'Advertisement';
    $mimeType = mime_content_type($image_path);
    $fileName = basename($image_path);

    $endpoints = [
        'https://api.opustechnology.in/wapp/v2/api/sendMedia',
        'https://api.opustechnology.in/wapp/v2/api/sendFile',
        'https://api.opustechnology.in/wapp/v2/api/sendImage',
    ];
    $fileKeys = ['file','media','image'];
    $captionKeys = ['caption','msg','message'];

    $attemptLogs = [];
    foreach ($endpoints as $ep) {
        foreach ($fileKeys as $fk) {
            foreach ($captionKeys as $ck) {
                $ch = curl_init($ep);
                $payload = [
                    'apikey' => $apikey,
                    'mobile' => $mobile,
                    'number' => $mobile,
                    $ck      => $caption,
                    $fk      => new CURLFile($image_path, $mimeType, $fileName),
                ];
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                [$response, $http, $err] = curl_exec_json($ch);
                $json = json_decode($response, true);
                if ($http === 200 && $json && ((isset($json['status']) && $json['status']==='success') || (isset($json['success']) && $json['success']))) {
                    logWhatsAppApi('media_multipart_success', $mobile, $caption, $json, $response, [
                        'endpoint'    => $ep,
                        'fileKey'     => $fk,
                        'captionKey'  => $ck,
                        'http_code'   => $http,
                        'curl_error'  => $err,
                        'file_info'   => ['path' => $image_path, 'mime' => $mimeType, 'name' => $fileName],
                    ]);
                    echo json_encode(['success'=>true,'message'=>'Image sent successfully','endpoint'=>$ep,'params'=>['fileKey'=>$fk,'captionKey'=>$ck],'api_response'=>$json]);
                    exit;
                }
                $attemptLogs[] = [
                    'endpoint'=>$ep,
                    'fileKey'=>$fk,
                    'captionKey'=>$ck,
                    'http_code'=>$http,
                    'curl_error'=>$err,
                    'raw_response'=>$response,
                ];
            }
        }
    }

    // If multipart failed and we have a public URL, try URL method
    if ($media_url && !$isLocalUrl) {
        $try = [
            "https://api.opustechnology.in/wapp/v2/api/sendMedia?apikey=$apikey&mobile=$mobile&file=" . urlencode($media_url) . "&caption=" . urlencode($caption),
            "https://api.opustechnology.in/wapp/v2/api/sendMedia?apikey=$apikey&number=$mobile&media_url=" . urlencode($media_url) . "&caption=" . urlencode($caption),
            "https://api.opustechnology.in/wapp/v2/api/sendMedia?apikey=$apikey&mobile=$mobile&image=" . urlencode($media_url) . "&caption=" . urlencode($caption),
        ];
        foreach ($try as $u) {
            $res = @file_get_contents($u);
            if ($res !== false) {
                $jr = json_decode($res, true);
                if ($jr && ((isset($jr['status']) && $jr['status']==='success') || (isset($jr['success']) && $jr['success']))) {
                    logWhatsAppApi('media_url_success', $mobile, $caption, $jr, $res, ['endpoint' => $u, 'media_url' => $media_url]);
                    echo json_encode(['success'=>true,'message'=>'Image sent via URL (fallback)','api_response'=>$jr]);
                    exit;
                }
                $attemptLogs[] = ['endpoint'=>$u,'http_code'=>null,'curl_error'=>null,'raw_response'=>$res];
            }
        }
    }

    echo json_encode([
        'success'=>false,
        'error'=>'Failed to send image. WhatsApp API did not accept the image.',
        'details'=>[
            'file_info'=>['path'=>$image_path,'mime'=>$mimeType,'name'=>$fileName],
            'attempts'=>$attemptLogs,
            'media_url'=>$media_url
        ]
    ]);
    logWhatsAppApi('media_failed', $mobile, $caption, ['error' => 'Failed to send image'], null, [
        'file_info' => ['path'=>$image_path,'mime'=>$mimeType,'name'=>$fileName],
        'attempts'  => $attemptLogs,
        'media_url' => $media_url,
    ]);
    exit;
}

// No local file: try media_url if public, else send text
if ($media_url && !$isLocalUrl) {
    $caption = $msg ?: 'Advertisement';
    $urls = [
        "https://api.opustechnology.in/wapp/v2/api/sendMedia?apikey=$apikey&mobile=$mobile&file=" . urlencode($media_url) . "&caption=" . urlencode($caption),
        "https://api.opustechnology.in/wapp/v2/api/sendMedia?apikey=$apikey&number=$mobile&media_url=" . urlencode($media_url) . "&caption=" . urlencode($caption),
        "https://api.opustechnology.in/wapp/v2/api/sendMedia?apikey=$apikey&mobile=$mobile&image=" . urlencode($media_url) . "&caption=" . urlencode($caption),
    ];
    foreach ($urls as $url) {
        $response = @file_get_contents($url);
        if ($response !== false) {
            $result = json_decode($response, true);
            if ($result && ((isset($result['status']) && $result['status']==='success') || (isset($result['success']) && $result['success']))) {
                logWhatsAppApi('media_url_only_success', $mobile, $caption, $result, $response, ['endpoint' => $url, 'media_url' => $media_url]);
                echo $response; exit;
            }
        }
    }
    $fallbackMsg = "*{$caption}*\n\nView image: {$media_url}";
    $formattedMsg = formatWhatsAppMessage($fallbackMsg);
    $encoded = urlencode($formattedMsg);
    $fallback = "https://api.opustechnology.in/wapp/v2/api/send?apikey=$apikey&mobile=$mobile&msg=$encoded";
    $r = @file_get_contents($fallback);
    if ($r !== false) {
        $jr = json_decode($r, true);
        logWhatsAppApi('media_url_text_fallback', $mobile, $fallbackMsg, $jr, $r, ['fallback_url' => $fallback]);
        echo $r;
    } else {
        logWhatsAppApi('media_url_text_fallback_failed', $mobile, $fallbackMsg, ['error' => 'URL method failed'], null, ['fallback_url' => $fallback]);
        echo json_encode(['success'=>false,'error'=>'URL method failed']);
    }
    exit;
}

// Text only
$formatted = formatWhatsAppMessage($msg);
$encoded = urlencode($formatted);
$url = "https://api.opustechnology.in/wapp/v2/api/send?apikey=$apikey&mobile=$mobile&msg=$encoded";
$response = @file_get_contents($url);

if ($response !== false) {
    $result = json_decode($response, true);
    if ($result && ((isset($result['status']) && $result['status'] === 'success') || (isset($result['success']) && $result['success']))) {
        logWhatsAppApi('text_send_success', $mobile, $msg, $result, $response, ['url' => $url]);
        echo json_encode(['success' => true, 'message' => 'WhatsApp message sent successfully', 'api_response' => $result]);
    } else {
        logWhatsAppApi('text_send_api_error', $mobile, $msg, $result, $response, ['url' => $url]);
        echo json_encode(['success' => false, 'error' => 'WhatsApp API returned error', 'api_response' => $result, 'raw_response' => $response]);
    }
} else {
    logWhatsAppApi('text_send_connect_failed', $mobile, $msg, ['error' => 'Failed to connect to WhatsApp API'], null, ['url' => $url]);
    echo json_encode(['success' => false, 'error' => 'Failed to connect to WhatsApp API', 'url' => $url]);
}

<?php
// Simple test to check if save_lead.php is working
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

echo json_encode([
    "message" => "Backend is working",
    "timestamp" => date('Y-m-d H:i:s'),
    "server_info" => [
        "php_version" => phpversion(),
        "post_max_size" => ini_get('post_max_size'),
        "upload_max_filesize" => ini_get('upload_max_filesize')
    ]
]);
?>

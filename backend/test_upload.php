<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");

$uploadDir = '../uploads/leads/';
$testFile = $uploadDir . 'test_' . time() . '.txt';

$result = [
    'upload_dir' => $uploadDir,
    'absolute_path' => realpath($uploadDir),
    'dir_exists' => file_exists($uploadDir),
    'dir_writable' => is_writable($uploadDir),
    'test_write' => false,
    'error' => null
];

try {
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        $result['dir_created'] = true;
    }
    
    // Test write
    if (file_put_contents($testFile, 'test content')) {
        $result['test_write'] = true;
        unlink($testFile); // Clean up
    }
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>

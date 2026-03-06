<?php
// Test permissions.php to see if it's working
require_once 'db.php';
require_once 'permissions.php';

header('Content-Type: application/json');

try {
    // Test getUserFromRequest
    $userInfo = getUserFromRequest();
    echo json_encode([
        'success' => true,
        'userInfo' => $userInfo,
        'canViewAllData' => canViewAllData($userInfo['role']),
        'message' => 'Permissions helper is working'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
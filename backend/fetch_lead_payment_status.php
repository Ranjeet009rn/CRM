<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

try {
    // Check if payments table has lead_id column; if not, just return empty list
    $hasLeadId = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'lead_id'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasLeadId = true;
    }

    if (!$hasLeadId) {
        echo json_encode([
            'success' => true,
            'payment_status' => []
        ]);
        $conn->close();
        exit();
    }

    // Compute per-lead payment status using AMOUNTS instead of just counts.
    //  - 'Pending' rows represent expected receivable amounts
    //  - 'Inward' rows represent actual received payments
    // Status rules:
    //  - If total_pending_amount > total_inward_amount  => 'due'
    //  - If total_pending_amount <= total_inward_amount AND total_inward_amount > 0 => 'approved'
    //  - Otherwise => 'none'
    $sql = "SELECT 
                lead_id,
                SUM(CASE WHEN type = 'Pending' THEN amount ELSE 0 END) AS pending_amount,
                SUM(CASE WHEN type = 'Inward' THEN amount ELSE 0 END) AS inward_amount
            FROM payments
            WHERE lead_id IS NOT NULL
            GROUP BY lead_id";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $status = [];
    while ($row = $result->fetch_assoc()) {
        $leadId        = (int)$row['lead_id'];
        $pendingAmount = isset($row['pending_amount']) ? (float)$row['pending_amount'] : 0.0;
        $inwardAmount  = isset($row['inward_amount']) ? (float)$row['inward_amount'] : 0.0;

        $st = 'none';

        if ($pendingAmount > 0 && $inwardAmount < $pendingAmount) {
            // Still some pending balance
            $st = 'due';
        } elseif ($inwardAmount > 0 && $inwardAmount >= $pendingAmount) {
            // Fully or over-paid
            $st = 'approved';
        }

        $status[] = [
            'lead_id' => $leadId,
            'status'  => $st
        ];
    }

    echo json_encode([
        'success' => true,
        'payment_status' => $status
    ]);

    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

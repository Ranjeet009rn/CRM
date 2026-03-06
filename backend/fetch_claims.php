<?php
// Simple claims listing API for Claim Management page
// Filters claimed loans from sanctioned_loan + leads with optional period filters

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php';

try {
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?: [];

    $periodType = isset($input['periodType']) ? strtolower(trim($input['periodType'])) : 'month';
    $periodValue = isset($input['periodValue']) ? strtolower(trim($input['periodValue'])) : 'current';
    $emiTypeFilter = isset($input['emiType']) ? trim($input['emiType']) : '';

    // Base query: list all sanctioned loans with optional claim info
    $sql = "SELECT 
                sl.sanctioned_id,
                sl.lead_id,
                sl.sanctioned_date,
                sl.loan_amount,
                sl.emi_type,
                sl.loan_tenure,
                sl.claim_status,
                sl.claim_stage,
                sl.claim_disbursement_date,
                l.name AS customer_name,
                l.phone AS customer_phone,
                l.social_category,
                l.status AS lead_status,
                ch.last_paid_date,
                ch.total_paid_claims
            FROM sanctioned_loan sl
            INNER JOIN leads l ON sl.lead_id = l.lead_id
            LEFT JOIN (
                SELECT sanctioned_id,
                       MAX(event_date) AS last_paid_date,
                       COUNT(*) AS total_paid_claims
                FROM claim_history
                WHERE notes = 'Claim paid'
                GROUP BY sanctioned_id
            ) ch ON sl.sanctioned_id = ch.sanctioned_id";

    // Parameter arrays for optional filters
    $params = [];
    $types = '';

    // Optional EMI type filter (Monthly / Quarterly / Half-Yearly / Yearly ...)
    if ($emiTypeFilter !== '' && strtolower($emiTypeFilter) !== 'all') {
        $sql .= " AND sl.emi_type = ?";
        $types .= 's';
        $params[] = $emiTypeFilter;
    }

    $sql .= " ORDER BY sl.sanctioned_date DESC, sl.sanctioned_id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $claims = [];
    while ($row = $result->fetch_assoc()) {
        // Compute next_claim_date based on last_paid_date and emi_type
        $nextClaimDate = null;
        if (!empty($row['last_paid_date'])) {
            $emiType = isset($row['emi_type']) ? strtolower($row['emi_type']) : '';
            $baseDate = new DateTime($row['last_paid_date']);
            if ($emiType === 'monthly') {
                $baseDate->modify('+1 month');
            } elseif ($emiType === 'quarterly') {
                $baseDate->modify('+3 months');
            } elseif ($emiType === 'half-yearly' || $emiType === 'half yearly') {
                $baseDate->modify('+6 months');
            } elseif ($emiType === 'yearly' || $emiType === 'year') {
                $baseDate->modify('+12 months');
            }
            $nextClaimDate = $baseDate->format('Y-m-d');
        }

        $row['next_claim_date'] = $nextClaimDate;

        // Compute claim counts based on emi_type and loan_tenure
        $emiTypeForCount = isset($row['emi_type']) ? strtolower($row['emi_type']) : '';
        $tenureMonths = isset($row['loan_tenure']) ? (int)$row['loan_tenure'] : 0;
        $totalClaims = 0;
        if ($tenureMonths > 0) {
            if ($emiTypeForCount === 'monthly') {
                $totalClaims = $tenureMonths; // 1 claim per month
            } elseif ($emiTypeForCount === 'quarterly') {
                $totalClaims = (int)floor($tenureMonths / 3);
            } elseif ($emiTypeForCount === 'half-yearly' || $emiTypeForCount === 'half yearly') {
                $totalClaims = (int)floor($tenureMonths / 6);
            } elseif ($emiTypeForCount === 'yearly' || $emiTypeForCount === 'year') {
                $totalClaims = (int)floor($tenureMonths / 12);
            }
        }

        $claimsDone = isset($row['total_paid_claims']) ? (int)$row['total_paid_claims'] : 0;
        if ($claimsDone < 0) {
            $claimsDone = 0;
        }
        if ($totalClaims > 0 && $claimsDone > $totalClaims) {
            $claimsDone = $totalClaims;
        }
        $claimsRemaining = $totalClaims > 0 ? max($totalClaims - $claimsDone, 0) : 0;

        $row['total_claims'] = $totalClaims;
        $row['claims_done'] = $claimsDone;
        $row['claims_remaining'] = $claimsRemaining;
        $claims[] = $row;
    }

    echo json_encode([
        'success' => true,
        'claims' => $claims,
        'count' => count($claims)
    ]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}

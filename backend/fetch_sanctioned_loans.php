<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, return JSON instead

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once 'db.php';
    require_once 'permissions.php';

    // Get optional filters
    $input = json_decode(file_get_contents('php://input'), true);
    $username = isset($input['username']) ? $input['username'] : '';
    $role = isset($input['role']) ? $input['role'] : '';
    $subRole = isset($input['sub_role']) ? strtolower($input['sub_role']) : '';

    // Base query: fetch all sanctioned loans joined with their leads
    $sql = "SELECT 
    sl.sanctioned_id,
    sl.lead_id,
    sl.sanctioned_date,
    sl.loan_amount,
    sl.emi_type,
    sl.interest_rate,
    sl.loan_tenure,
    sl.emi,
    sl.claim_status,
    sl.sanction_letter_status,
    sl.sanction_letter,
    sl.disbursed,
    sl.created_at,
    sl.updated_at,
    l.name as customer_name,
    l.phone as customer_phone,
    l.email as customer_email,
    l.social_category,
    l.assigned_to,
    l.loan_reason,
    l.status as lead_status,
    COALESCE(
        sl.bank_name,
        (
            SELECT fm.sent_to 
            FROM file_movements fm 
            WHERE fm.lead_id = sl.lead_id
            ORDER BY fm.movement_date DESC, fm.id DESC 
            LIMIT 1
        )
    ) AS sanctioned_bank
FROM sanctioned_loan sl
INNER JOIN leads l ON sl.lead_id = l.lead_id";

    // Filter by assigned user for non-admin roles, except Sanctioning Authority
// Sanctioning Authority employees should see ALL sanctioned loans, regardless of assignment
// Also, always show external (third-party) sanctioned loans (leads.is_external = 1)
    $isAdmin = canViewAllData($role);
    $isSanctioningAuthority = (strtolower($role) === 'employee') && (strpos($subRole, 'sanction') !== false);

    if (!$isAdmin && !$isSanctioningAuthority && !empty($username)) {
        // For regular employees and sales officers, restrict to ONLY their created OR assigned leads
        // External/third-party leads (is_external = 1) are visible only to admin
        $sql .= " AND (l.created_by = ? OR l.assigned_to = ?)";
    }

    $sql .= " ORDER BY sl.sanctioned_date DESC, sl.sanctioned_id DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!$isAdmin && !$isSanctioningAuthority && !empty($username)) {
        $stmt->bind_param("ss", $username, $username);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }

    echo json_encode([
        'success' => true,
        'loans' => $loans,
        'count' => count($loans)
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
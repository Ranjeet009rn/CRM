<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$sanctioned_id = isset($input['sanctioned_id']) ? intval($input['sanctioned_id']) : 0;
$lead_id = isset($input['lead_id']) ? intval($input['lead_id']) : 0;
$claim_status = isset($input['claim_status']) ? $input['claim_status'] : '';
$claim_stage = isset($input['claim_stage']) ? $input['claim_stage'] : '';
$pending_documents = array_key_exists('pending_documents', $input) ? $input['pending_documents'] : null; // can be null or string/JSON
$claim_notes = array_key_exists('claim_notes', $input) ? $input['claim_notes'] : null;
$claim_disbursement_date = isset($input['claim_disbursement_date']) ? $input['claim_disbursement_date'] : '';
$disbursed = isset($input['disbursed']) ? $input['disbursed'] : '';
$sanction_letter = isset($input['sanction_letter']) ? $input['sanction_letter'] : null;
// New: independent sanction letter status (pending_letter | approved_letter | hold)
$sanction_letter_status = isset($input['sanction_letter_status']) ? $input['sanction_letter_status'] : null;
// Optional: number of instalments to claim in one go (defaults to 1)
$installmentsToClaim = isset($input['installments_to_claim']) ? (int)$input['installments_to_claim'] : 1;
if ($installmentsToClaim <= 0) {
    $installmentsToClaim = 1;
}

if ($sanctioned_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid sanctioned loan ID']);
    exit;
}

// Build update query
$updates = [];
$types = '';
$values = [];

if (!empty($claim_status)) {
    $updates[] = "claim_status = ?";
    $types .= 's';
    $values[] = $claim_status;
}

if (!empty($claim_stage)) {
    $updates[] = "claim_stage = ?";
    $types .= 's';
    $values[] = $claim_stage;
}

if ($pending_documents !== null) {
    $updates[] = "pending_documents = ?";
    $types .= 's';
    $values[] = $pending_documents;
}

if ($claim_notes !== null) {
    $updates[] = "claim_notes = ?";
    $types .= 's';
    $values[] = $claim_notes;
}

if (!empty($claim_disbursement_date)) {
    $updates[] = "claim_disbursement_date = ?";
    $types .= 's';
    $values[] = $claim_disbursement_date;
}

if (!empty($disbursed)) {
    $updates[] = "disbursed = ?";
    $types .= 's';
    $values[] = $disbursed;
}

if ($sanction_letter !== null) {
    $updates[] = "sanction_letter = ?";
    $types .= 's';
    $values[] = $sanction_letter;
}

// Ensure column sanction_letter_status exists (idempotent)
$checkCol = $conn->query("SHOW COLUMNS FROM sanctioned_loan LIKE 'sanction_letter_status'");
if ($checkCol && $checkCol->num_rows === 0) {
    $conn->query("ALTER TABLE sanctioned_loan ADD COLUMN sanction_letter_status VARCHAR(32) NULL DEFAULT 'pending_letter'");
}

if ($sanction_letter_status !== null && $sanction_letter_status !== '') {
    $updates[] = "sanction_letter_status = ?";
    $types .= 's';
    $values[] = $sanction_letter_status;
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

$sql = "UPDATE sanctioned_loan SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE sanctioned_id = ?";
$types .= 'i';
$values[] = $sanctioned_id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    $matchedBy = 'sanctioned_id';
    // If nothing updated and we have a lead_id, try fallback update by lead_id
    if ($stmt->affected_rows === 0 && $lead_id > 0) {
        $stmt->close();
        $sql2 = "UPDATE sanctioned_loan SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE lead_id = ?";
        $stmt2 = $conn->prepare($sql2);
        if ($stmt2) {
            $types2 = substr($types, 0, -1) . 'i'; // replace last i with new lead_id binding
            $vals2 = $values;
            $vals2[count($vals2)-1] = $lead_id; // replace last value with lead_id
            $stmt2->bind_param($types2, ...$vals2);
            if ($stmt2->execute() && $stmt2->affected_rows > 0) {
                $matchedBy = 'lead_id';
            }
            $stmt2->close();
        }
    }
    // If claim has just been started and we have a lead_id, also mark that lead as Claimed
    if (!empty($claim_status) && strtolower($claim_status) === 'started' && $lead_id > 0) {
        $leadSql = "UPDATE leads SET status = 'Claimed' WHERE lead_id = ?";
        if ($leadStmt = $conn->prepare($leadSql)) {
            $leadStmt->bind_param('i', $lead_id);
            $leadStmt->execute();
            $leadStmt->close();
        }
    }

    // If claim has been marked as completed (paid), record an entry and financials
    if (!empty($claim_status) && strtolower($claim_status) === 'completed') {
        // Fetch emi_type, lead_id, loan_amount, loan_tenure and aggregates from sanctioned_loan, in case lead_id was not passed
        // Also fetch claim schedule tracking fields
        $infoSql = "SELECT emi_type, lead_id, loan_amount, loan_tenure, total_claimed, total_charges, total_claims, claims_done, claims_remaining, next_claim_date FROM sanctioned_loan WHERE sanctioned_id = ?";
        if ($infoStmt = $conn->prepare($infoSql)) {
            $infoStmt->bind_param('i', $sanctioned_id);
            if ($infoStmt->execute()) {
                $infoResult = $infoStmt->get_result();
                if ($row = $infoResult->fetch_assoc()) {
                    $emiTypeForHistory = $row['emi_type'] ?? '';
                    $leadIdForHistory = $row['lead_id'] ?? $lead_id;
                    $loanAmount = isset($row['loan_amount']) ? (float)$row['loan_amount'] : 0.0;
                    $loanTenure = isset($row['loan_tenure']) ? (int)$row['loan_tenure'] : 0;
                    $existingTotalClaims = isset($row['total_claims']) ? (int)$row['total_claims'] : 0;
                    $existingClaimsDone = isset($row['claims_done']) ? (int)$row['claims_done'] : 0;
                    $existingClaimsRemaining = isset($row['claims_remaining']) ? (int)$row['claims_remaining'] : 0;
                    $existingNextClaimDate = isset($row['next_claim_date']) ? $row['next_claim_date'] : null;

                    // === Calculate financials for this claim (possibly multiple instalments) ===
                    $perEmiAmount = 0.0;
                    if ($loanAmount > 0 && $loanTenure > 0) {
                        $perEmiAmount = $loanAmount / $loanTenure;
                    }

                    $effectiveInstalments = max(1, $installmentsToClaim);
                    $claimAmount = $perEmiAmount * $effectiveInstalments;
                    $claimCharges = round($claimAmount * 0.05, 2); // 5% charges on total claimed now
                    $netPayout = $claimAmount - $claimCharges;

                    // === Derive total_claims based on EMI frequency (if not already set) ===
                    $emiLower = strtolower($emiTypeForHistory);
                    $monthsPerEmi = 1; // default monthly
                    if ($emiLower === 'quarterly') {
                        $monthsPerEmi = 3;
                    } elseif ($emiLower === 'half-yearly' || $emiLower === 'half yearly') {
                        $monthsPerEmi = 6;
                    } elseif ($emiLower === 'yearly' || $emiLower === 'year') {
                        $monthsPerEmi = 12;
                    }

                    $totalClaims = $existingTotalClaims;
                    if ($totalClaims <= 0 && $loanTenure > 0 && $monthsPerEmi > 0) {
                        $totalClaims = (int)ceil($loanTenure / $monthsPerEmi);
                    }

                    // Protect against invalid values
                    if ($totalClaims < 0) $totalClaims = 0;
                    if ($existingClaimsDone < 0) $existingClaimsDone = 0;

                    // === Update claims_done / claims_remaining ===
                    $newClaimsDone = $existingClaimsDone + $effectiveInstalments;
                    if ($totalClaims > 0 && $newClaimsDone > $totalClaims) {
                        $newClaimsDone = $totalClaims;
                    }
                    $newClaimsRemaining = ($totalClaims > 0) ? max(0, $totalClaims - $newClaimsDone) : 0;

                    // === Compute next_claim_date ===
                    $newNextClaimDate = null;
                    if ($newClaimsRemaining > 0 && $monthsPerEmi > 0) {
                        // Base date: existing next_claim_date if present, else disbursement date, else today
                        $baseDate = null;
                        if (!empty($existingNextClaimDate)) {
                            $baseDate = $existingNextClaimDate;
                        } elseif (!empty($claim_disbursement_date)) {
                            $baseDate = $claim_disbursement_date;
                        } else {
                            $baseDate = date('Y-m-d');
                        }

                        $monthsToAdd = $monthsPerEmi * $effectiveInstalments;
                        $dt = new DateTime($baseDate);
                        $dt->modify('+' . $monthsToAdd . ' month');
                        $newNextClaimDate = $dt->format('Y-m-d');
                    }

                    // Insert row into claim_transactions table (aggregate for N instalments)
                    $claimedBy = isset($input['username']) ? $input['username'] : 'system';
                    $ctSql = "INSERT INTO claim_transactions (sanctioned_id, lead_id, emi_no, claim_amount, claim_charges, net_payout, claimed_by) VALUES (?, ?, NULL, ?, ?, ?, ?)";
                    if ($ctStmt = $conn->prepare($ctSql)) {
                        $ctStmt->bind_param('iiddss', $sanctioned_id, $leadIdForHistory, $claimAmount, $claimCharges, $netPayout, $claimedBy);
                        $ctStmt->execute();
                        $ctStmt->close();
                    }

                    // Update aggregate totals on sanctioned_loan, plus claim schedule fields
                    $aggSql = "UPDATE sanctioned_loan 
                               SET total_claimed = IFNULL(total_claimed,0) + ?,
                                   total_charges = IFNULL(total_charges,0) + ?,
                                   total_claims = ?,
                                   claims_done = ?,
                                   claims_remaining = ?,
                                   next_claim_date = ?
                               WHERE sanctioned_id = ?";
                    if ($aggStmt = $conn->prepare($aggSql)) {
                        $nextDateParam = $newNextClaimDate; // can be null
                        $aggStmt->bind_param(
                            'ddiiiss',
                            $claimAmount,
                            $claimCharges,
                            $totalClaims,
                            $newClaimsDone,
                            $newClaimsRemaining,
                            $nextDateParam,
                            $sanctioned_id
                        );
                        $aggStmt->execute();
                        $aggStmt->close();
                    }

                    // Finally, record an entry in claim_history for audit trail
                    $histSql = "INSERT INTO claim_history (sanctioned_id, lead_id, event_date, emi_type, notes) VALUES (?, ?, CURDATE(), ?, ?)";
                    if ($histStmt = $conn->prepare($histSql)) {
                        $histNotes = 'Claim paid for ' . max(1, $installmentsToClaim) . ' instalment(s)';
                        // Types: i = sanctioned_id, i = lead_id, s = emi_type, s = notes
                        $histStmt->bind_param('iiss', $sanctioned_id, $leadIdForHistory, $emiTypeForHistory, $histNotes);
                        $histStmt->execute();
                        $histStmt->close();
                    }
                }
            }
            $infoStmt->close();
        }
    }

    echo json_encode(['success' => true, 'message' => 'Sanctioned loan updated successfully', 'matched_by' => $matchedBy]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Include database connection
require_once 'db.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['lead_id']) || !isset($input['assigned_to'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Lead ID and assigned employee are required'
        ]);
        exit();
    }
    
    $lead_id = intval($input['lead_id']);
    $assigned_to = trim($input['assigned_to']);
    $assigned_by = isset($input['assigned_by']) ? trim($input['assigned_by']) : '';
    
    // Validate lead_id
    if ($lead_id <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid lead ID'
        ]);
        exit();
    }
    
    // Validate assigned_to
    if (empty($assigned_to)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Employee to assign is required'
        ]);
        exit();
    }
    
    // Check if lead exists using lead_id column
    $check_stmt = $conn->prepare("SELECT lead_id as id, assigned_to FROM leads WHERE lead_id = ?");
    $check_stmt->bind_param("i", $lead_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $lead = $result->fetch_assoc();
    
    if (!$lead) {
        echo json_encode([
            'success' => false, 
            'message' => 'Lead not found'
        ]);
        exit();
    }
    
    // Update the lead assignment (current assignee in leads table)
    $update_sql = "UPDATE leads SET assigned_to = ? WHERE lead_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    
    if (!$update_stmt) {
        echo json_encode([
            'success' => false, 
            'message' => 'Prepare failed: ' . $conn->error
        ]);
        exit();
    }
    
    $update_stmt->bind_param("si", $assigned_to, $lead_id);
    $update_result = $update_stmt->execute();

    if ($update_result) {
        // Maintain assignment history in lead_assignments table
        try {
            // 1) Close any previous active assignment for this lead
            if (!empty($lead['assigned_to'])) {
                $close_sql = "UPDATE lead_assignments 
                              SET unassigned_at = NOW() 
                              WHERE lead_id = ? 
                                AND employee_username = ? 
                                AND unassigned_at IS NULL";
                $close_stmt = $conn->prepare($close_sql);
                if ($close_stmt) {
                    $close_stmt->bind_param("is", $lead_id, $lead['assigned_to']);
                    $close_stmt->execute();
                    $close_stmt->close();
                }
            }

            // 2) Insert new assignment row
            $assigned_by_final = !empty($assigned_by) ? $assigned_by : 'admin';
            $insert_sql = "INSERT INTO lead_assignments 
                           (lead_id, employee_username, assigned_by) 
                           VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            if ($insert_stmt) {
                $insert_stmt->bind_param("iss", $lead_id, $assigned_to, $assigned_by_final);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
        } catch (Exception $historyEx) {
            // Don't break main response if history fails; just log it
            error_log("Error updating lead_assignments history: " . $historyEx->getMessage());
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Lead assigned successfully',
            'data' => [
                'lead_id' => $lead_id,
                'assigned_to' => $assigned_to,
                'previous_assignee' => $lead['assigned_to']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to assign lead: ' . $conn->error
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in assign_lead.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while assigning the lead: ' . $e->getMessage()
    ]);
}
?>

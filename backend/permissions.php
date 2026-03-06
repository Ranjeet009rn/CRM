<?php
/**
 * Role-Based Access Control (RBAC) Permission Helper
 * Centralized permission checking for the CRM system
 */

/**
 * Check if user role can view all data (admin privilege)
 */
function canViewAllData($role)
{
    return strtolower(trim($role)) === 'admin';
}

/**
 * Check if user role should only see their own data
 */
function canViewOwnDataOnly($role)
{
    $restrictedRoles = ['sales_officer', 'bank_agent', 'employee'];
    return in_array(strtolower(trim($role)), $restrictedRoles);
}

/**
 * Build SQL WHERE clause for ownership filtering
 * 
 * @param string $role User's role
 * @param string $username User's username
 * @param string $tableName Optional table name prefix for joins
 * @param mysqli $conn Database connection
 * @return string SQL WHERE clause (without WHERE keyword)
 */
function buildOwnershipFilter($role, $username, $tableName = '', $conn = null)
{
    if (canViewAllData($role)) {
        return '1=1'; // Always true for admin
    }

    // For non-admins, filter by created_by OR assigned_to
    if ($conn) {
        $usernameEsc = $conn->real_escape_string($username);
    } else {
        $usernameEsc = addslashes($username);
    }

    if ($tableName) {
        return "({$tableName}.created_by = '{$usernameEsc}' OR {$tableName}.assigned_to = '{$usernameEsc}')";
    }
    return "(created_by = '{$usernameEsc}' OR assigned_to = '{$usernameEsc}')";
}

/**
 * Validate if user has access to a specific record
 * 
 * @param string $role User's role
 * @param string $username User's username
 * @param string $recordCreator Who created the record
 * @param string|null $recordAssignee Who the record is assigned to
 * @return bool True if user can access the record
 */
function validateUserAccess($role, $username, $recordCreator, $recordAssignee = null)
{
    if (canViewAllData($role)) {
        return true; // Admin can access everything
    }

    // Non-admin can only access if they created it or it's assigned to them
    return ($recordCreator === $username) || ($recordAssignee === $username);
}

/**
 * Get user info from request (GET or POST)
 * 
 * @return array ['role' => string, 'username' => string]
 */
function getUserFromRequest()
{
    $role = $_GET['role'] ?? $_POST['role'] ?? $_GET['user_type'] ?? '';
    $username = $_GET['username'] ?? $_POST['username'] ?? '';

    return [
        'role' => trim($role),
        'username' => trim($username)
    ];
}
?>
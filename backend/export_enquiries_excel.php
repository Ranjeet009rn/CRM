<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo "ok";
    exit;
}

require_once __DIR__ . '/db.php';

try {
    if (!isset($conn) || $conn->connect_error) {
        http_response_code(500);
        ob_end_clean();
        echo 'DB connection failed';
        return;
    }

    $hasColumn = function(mysqli $conn, string $column): bool {
        $colEsc = $conn->real_escape_string($column);
        $dbRes = $conn->query("SELECT DATABASE() as db");
        $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
        $dbName = $dbRow ? $conn->real_escape_string($dbRow['db']) : '';
        if (!$dbName) return false;
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".$dbName."' AND TABLE_NAME='enquiry' AND COLUMN_NAME='".$colEsc."' LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) { $res->free(); return true; }
        if ($res) { $res->free(); }
        return false;
    };

    $columns = [
        'id', 'first_name', 'last_name', 'email', 'phone', 'message', 'created_at', 'assigned_to', 'status'
    ];

    if ($hasColumn($conn, 'created_by')) {
        $columns[] = 'created_by';
    } else {
        $columns[] = "NULL AS created_by";
    }

    if ($hasColumn($conn, 'district')) {
        $columns[] = 'district';
    } else {
        $columns[] = 'NULL AS district';
    }

    if ($hasColumn($conn, 'loan_type')) {
        $columns[] = 'loan_type';
    } elseif ($hasColumn($conn, 'loanType')) {
        $columns[] = 'loanType';
    } else {
        $columns[] = 'NULL AS loan_type';
    }

    if ($hasColumn($conn, 'reference_through')) {
        $columns[] = 'reference_through';
    } elseif ($hasColumn($conn, 'referenceThrough')) {
        $columns[] = 'referenceThrough';
    } else {
        $columns[] = 'NULL AS reference_through';
    }

    if ($hasColumn($conn, 'amount')) {
        $columns[] = 'amount';
    } else {
        $columns[] = 'NULL AS amount';
    }

    $sql = "SELECT ".implode(', ', $columns)." FROM enquiry ORDER BY created_at DESC";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $filename = 'enquiries_'.date('Ymd_His').'.csv';
    ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'ID',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'District',
        'Loan Type',
        'Reference Through',
        'Amount',
        'Message',
        'Created At',
        'Created By',
        'Assigned To',
        'Status',
    ]);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'] ?? '',
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['district'] ?? ($row['District'] ?? ''),
            $row['loan_type'] ?? ($row['loanType'] ?? ''),
            $row['reference_through'] ?? ($row['referenceThrough'] ?? ''),
            $row['amount'] ?? '',
            $row['message'] ?? '',
            $row['created_at'] ?? '',
            $row['created_by'] ?? '',
            $row['assigned_to'] ?? '',
            $row['status'] ?? '',
        ]);
    }

    fclose($output);
} catch (Throwable $e) {
    http_response_code(500);
    ob_end_clean();
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error exporting enquiries: ' . $e->getMessage();
} finally {
    if (isset($result) && $result instanceof mysqli_result) { @$result->free(); }
    if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); }
}


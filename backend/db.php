<?php
// Central DB configuration (Hostinger production)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Hostinger Production Configuration ONLY
$host = "193.203.184.228"; // Updated to localhost as per standard Hostinger practice
$username = "u876295706_ingawale";
$password = "Ranjeet@1810";
$database = "u876295706_ingavale";

// Configure mysqli to throw exceptions instead of emitting warnings/notices
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Global handler for mysqli_sql_exception so all DB errors return clean JSON
set_exception_handler(function ($e) {
    if ($e instanceof mysqli_sql_exception) {
        header("Content-Type: application/json");
        http_response_code(503);
        echo json_encode([
            "success" => false,
            "error" => "Database limit reached. Please try again later."
        ]);
        exit();
    }

    // Re-throw non-mysqli exceptions so other handlers or default behavior apply
    throw $e;
});

// Establish the database connection safely
try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    header("Content-Type: application/json");
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "error" => "Database limit reached. Please try again later."
    ]);
    exit();
}

?>
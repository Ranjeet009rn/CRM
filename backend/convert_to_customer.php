<?php
// === CORS & JSON headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// === Disable PHP warnings from being printed (so it doesn't break JSON)
ini_set('display_errors', 0);
error_reporting(0);

// === DB Connection
$conn = new mysqli("localhost", "root", "", "CRM");
if ($conn->connect_error) {
  echo json_encode(["success" => false, "error" => "DB connection failed"]);
  exit;
}

// === Read JSON input
$data = json_decode(file_get_contents("php://input"), true);
$id = $data["id"] ?? null;

if (!$id) {
  echo json_encode(["success" => false, "error" => "Lead ID is required"]);
  exit;
}

// === Get Lead
$q = "SELECT * FROM leads WHERE id = ?";
$stmt = $conn->prepare($q);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$lead = $res->fetch_assoc();

if (!$lead) {
  echo json_encode(["success" => false, "error" => "Lead not found"]);
  exit;
}

if ($lead["status"] !== "Confirm") {
  echo json_encode(["success" => false, "error" => "Only Confirm leads can be converted"]);
  exit;
}

// === Insert into customer table
$insert = $conn->prepare("INSERT INTO customer 
  (lead_id, name, email, phone, address, loan_reason, loan_amount, interest_rate, emi) 
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$insert->bind_param("issssssss",
  $lead["id"],
  $lead["name"],
  $lead["email"],
  $lead["phone"],
  $lead["address"],
  $lead["loan_reason"],
  $lead["loan_amount"],
  $lead["interest_rate"],
  $lead["emi"]
);

if ($insert->execute()) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "error" => "Insert failed"]);
}

$conn->close();
?>

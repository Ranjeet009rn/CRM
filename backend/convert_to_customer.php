<?php
// === CORS & JSON headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// Use shared database configuration (handles localhost vs Hostinger)
require_once __DIR__ . '/db.php';

// === Read JSON input
$data = json_decode(file_get_contents("php://input"), true);
// Accept either id or lead_id from frontend, but internally use lead_id column
$leadId = isset($data["lead_id"]) ? intval($data["lead_id"]) : intval($data["id"] ?? 0);

if (!$leadId) {
  echo json_encode(["success" => false, "error" => "Lead ID is required"]);
  $conn->close();
  exit;
}

// === Get Lead
$stmt = $conn->prepare("SELECT * FROM leads WHERE lead_id = ?");
$stmt->bind_param("i", $leadId);
$stmt->execute();
$res = $stmt->get_result();
$lead = $res->fetch_assoc();

if (!$lead) {
  echo json_encode(["success" => false, "error" => "Lead not found"]);
  $stmt->close();
  $conn->close();
  exit;
}

// Allow conversion once lead is Scrutinized
if ($lead["status"] !== "Scrutinized") {
  echo json_encode(["success" => false, "error" => "Only Scrutinized leads can be converted"]);
  $stmt->close();
  $conn->close();
  exit;
}

// === Check if already converted
$check = $conn->prepare("SELECT id FROM customer WHERE lead_id = ?");
$check->bind_param("i", $leadId);
$check->execute();
$checkRes = $check->get_result();

if ($checkRes->num_rows > 0) {
  echo json_encode(["success" => false, "error" => "Already converted"]);
  $check->close();
  $stmt->close();
  $conn->close();
  exit;
}

// === Insert into customer table
$insert = $conn->prepare("INSERT INTO customer 
  (lead_id, name, email, phone, address, loan_reason, loan_amount, interest_rate, emi) 
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$insert->bind_param("issssssss",
  $lead["lead_id"],
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

$insert->close();
$check->close();
$stmt->close();
$conn->close();
?>

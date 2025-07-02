<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
  echo json_encode(["success" => false, "error" => "Missing lead ID"]);
  exit;
}

$conn = new mysqli("localhost", "root", "", "CRM");

$id = $conn->real_escape_string($data['id']);
$name = $conn->real_escape_string($data['name']);
$email = $conn->real_escape_string($data['email']);
$phone = $conn->real_escape_string($data['phone']);
$source = $conn->real_escape_string($data['source']);
$status = $conn->real_escape_string($data['status']);
$assignedTo = $conn->real_escape_string($data['assignedTo']);
$address = $conn->real_escape_string($data['address']);
$cd_date = $conn->real_escape_string($data['cd_date']);
$loan_reason = $conn->real_escape_string($data['loan_reason']);
$loan_amount = $conn->real_escape_string($data['loan_amount']);
$emi = $conn->real_escape_string($data['emi']);
$interest_rate = $conn->real_escape_string($data['interest_rate']);

$sql = "UPDATE leads SET
  name = '$name',
  email = '$email',
  phone = '$phone',
  source = '$source',
  status = '$status',
  assigned_to = '$assignedTo',
  address = '$address',
  cd_date = '$cd_date',
  loan_reason = '$loan_reason',
  loan_amount = '$loan_amount',
  emi = '$emi',
  interest_rate = '$interest_rate'
  WHERE id = '$id'";

if ($conn->query($sql)) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "error" => $conn->error]);
}

$conn->close();

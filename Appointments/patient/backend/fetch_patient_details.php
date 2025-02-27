<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !isset($_GET['patient_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized request."]);
    exit();
}

$patientID = $_GET['patient_id'];
$accountID = $_SESSION['account_ID'];

$query = "SELECT patient_id, first_name, last_name, age, gender, profile_picture FROM patients WHERE patient_id = ? AND account_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ii", $patientID, $accountID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(["status" => "error", "message" => "Patient not found."]);
    exit();
}

$patient = $result->fetch_assoc();
echo json_encode(["status" => "success", "patient" => $patient]);

$stmt->close();
?>

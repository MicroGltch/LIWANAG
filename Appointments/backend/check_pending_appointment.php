<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !isset($_GET['patient_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$patientID = $_GET['patient_id'];

$query = "SELECT session_type FROM appointments WHERE patient_id = ? AND status IN ('Pending', 'Confirmed')";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(["status" => "error", "existing_type" => $row['session_type']]);
} else {
    echo json_encode(["status" => "success"]);
}

$stmt->close();
?>

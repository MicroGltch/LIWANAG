<?php 
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// ✅ Check if the patient has completed Initial Evaluation
if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    $query = "SELECT COUNT(*) as completed FROM appointments 
              WHERE patient_id = ? AND session_type = 'Initial Evaluation' AND status = 'completed'";

    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode(["completed_ie" => $row['completed'] > 0]);
    exit();
}

?>
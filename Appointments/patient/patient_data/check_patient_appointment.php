<?php
require_once "../../../dbconfig.php";

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['patient_id'])) {
    $patientID = $_GET['patient_id'];

    // ✅ Check if patient has an existing Pending/Approved appointment
    $query = "SELECT appointment_id, date, time, status FROM appointments 
              WHERE patient_id = ? AND status IN ('Pending', 'Approved')";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            "status" => "error",
            "message" => "This patient already has a pending or approved appointment.",
            "existing_status" => $row['status'],
            "existing_date" => $row['date'],
            "existing_time" => $row['time']
        ]);
    } else {
        echo json_encode(["status" => "success"]);
    }
    exit();
}
?>

<?php
    require_once "../../../dbconfig.php";

    $patientID = $_GET['patient_id'] ?? null;

    if (!$patientID) {
        echo json_encode(["status" => "error", "message" => "Invalid patient."]);
        exit();
    }

    // ✅ Check if the patient already has a pending or approved appointment
    $checkExistingQuery = "SELECT appointment_id, date, time, status FROM appointments 
                        WHERE patient_id = ? 
                        AND status IN ('Pending', 'Approved')";
    $stmt = $connection->prepare($checkExistingQuery);
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    if ($appointment) {
        echo json_encode([
            "status" => "error",
            "message" => "This patient already has an active appointment.",
            "existing_status" => $appointment['status'],
            "existing_date" => $appointment['date'],
            "existing_time" => $appointment['time']
        ]);
    } else {
        echo json_encode(["status" => "success"]);
    }
?>

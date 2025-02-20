<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !isset($_GET['appointment_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$appointmentId = $_GET['appointment_id'];

// ✅ Fetch appointment details
$query = "SELECT a.appointment_id, a.date, a.time, a.status, a.session_type, a.doctor_referral,
                 p.first_name AS patient_firstname, p.last_name AS patient_lastname,
                 u.account_FName AS client_firstname, u.account_LName AS client_lastname
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          WHERE a.appointment_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $appointment = $result->fetch_assoc();
    $date = $appointment["date"];
    $time = $appointment["time"];
    $dayOfWeek = date('l', strtotime($date)); // Get day of the week (e.g., Monday)

    // ✅ Fetch ALL therapists and their availability
    $therapistQuery = "SELECT u.account_ID, u.account_FName, u.account_LName,
                              ta.start_time, ta.end_time, ta.break_start_time, ta.break_end_time 
                       FROM users u
                       LEFT JOIN therapist_availability ta ON u.account_ID = ta.therapist_id AND ta.day = ?
                       WHERE u.account_Type = 'therapist'";
    $stmt = $connection->prepare($therapistQuery);
    $stmt->bind_param("s", $dayOfWeek);
    $stmt->execute();
    $therapistResult = $stmt->get_result();
    $therapists = [];

    while ($therapist = $therapistResult->fetch_assoc()) {
        $available = "Unavailable";

        if ($therapist["start_time"] && $therapist["end_time"]) {
            if ($time >= $therapist["start_time"] && $time < $therapist["end_time"]) {
                if ($therapist["break_start_time"] && $therapist["break_end_time"]) {
                    if ($time >= $therapist["break_start_time"] && $time < $therapist["break_end_time"]) {
                        $available = "On Break";
                    } else {
                        $available = "Available";
                    }
                } else {
                    $available = "Available";
                }
            }
        }

        $therapists[] = [
            "id" => $therapist["account_ID"],
            "name" => $therapist["account_FName"] . " " . $therapist["account_LName"],
            "availability" => $available
        ];
    }

    echo json_encode([
        "status" => "success",
        "details" => [
            "patient_name" => htmlspecialchars($appointment["patient_firstname"] . " " . $appointment["patient_lastname"]),
            "client_name" => htmlspecialchars($appointment["client_firstname"] . " " . $appointment["client_lastname"]),
            "date" => htmlspecialchars($appointment["date"]),
            "time" => htmlspecialchars($appointment["time"]),
            "session_type" => htmlspecialchars($appointment["session_type"]),
            "status" => htmlspecialchars($appointment["status"]),
            "doctor_referral" => $appointment["doctor_referral"] ? "<a href='../uploads/doctors_referrals/{$appointment["doctor_referral"]}' target='_blank'>View Document</a>" : "Not Provided"
        ],
        "therapists" => $therapists
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Appointment details not found."]);
}
$stmt->close();

?>

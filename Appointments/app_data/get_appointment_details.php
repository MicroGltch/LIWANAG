<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !isset($_GET['appointment_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$appointmentId = $_GET['appointment_id'];
$userId = $_SESSION['account_ID'];
$userType = strtolower($_SESSION['account_Type']);

// ✅ Check if user is authorized
$accessQuery = "SELECT a.account_id, a.therapist_id 
                FROM appointments a 
                WHERE a.appointment_id = ?";
$stmt = $connection->prepare($accessQuery);
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$accessResult = $stmt->get_result();

if ($accessResult->num_rows !== 1) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$appointmentAccess = $accessResult->fetch_assoc();
$isAuthorized = in_array($userType, ["admin", "head therapist"]) || 
                $appointmentAccess["account_id"] == $userId || 
                $appointmentAccess["therapist_id"] == $userId;

if (!$isAuthorized) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// ✅ Fetch appointment details with updated referral information
$query = "SELECT a.date, a.time, a.status, a.session_type, 
                 p.first_name AS patient_firstname, p.last_name AS patient_lastname, p.profile_picture AS patient_picture,
                 u.account_FName AS client_firstname, u.account_LName AS client_lastname, u.profile_picture AS client_picture,
                 dr.official_referral_file, dr.proof_of_booking_referral_file
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          LEFT JOIN doctor_referrals dr ON a.referral_id = dr.referral_id -- ✅ Join doctor_referrals for updated referral structure
          WHERE a.appointment_id = ?";

$stmt = $connection->prepare($query);
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $appointment = $result->fetch_assoc();

    // ✅ Handle doctor referral links correctly
    $officialReferral = !empty($appointment["official_referral_file"]) 
        ? "<a href='../uploads/doctors_referrals/{$appointment["official_referral_file"]}' target='_blank'>View Official Referral</a>" 
        : "Not Provided";

    $proofOfBooking = !empty($appointment["proof_of_booking_referral_file"]) 
        ? "<a href='../uploads/doctors_referrals/{$appointment["proof_of_booking_referral_file"]}' target='_blank'>View Proof of Booking</a>" 
        : "Not Provided";

    echo json_encode([
        "status" => "success",
        "details" => [
            "patient_name" => htmlspecialchars($appointment["patient_firstname"] . " " . $appointment["patient_lastname"]),
            "patient_picture" => $appointment["patient_picture"] ? "../../uploads/profile_pictures/{$appointment["patient_picture"]}" : "../../uploads/profile_pictures/default.png",
            "client_name" => htmlspecialchars($appointment["client_firstname"] . " " . $appointment["client_lastname"]),
            "client_picture" => $appointment["client_picture"] ? "../../uploads/profile_pictures/{$appointment["client_picture"]}" : "../../uploads/profile_pictures/default.png",
            "date" => htmlspecialchars($appointment["date"]),
            "time" => htmlspecialchars($appointment["time"]),
            "session_type" => htmlspecialchars($appointment["session_type"]),
            "status" => htmlspecialchars($appointment["status"]),
            "doctor_referral" => $officialReferral . " | " . $proofOfBooking
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Appointment details not found."]);
}

$stmt->close();
?>

<?php
// app_data/get_appointment_details.php (CORRECT VERSION)

// --- Strict error reporting ---
ini_set('display_errors', 1); // Set to 0 in production
error_reporting(E_ALL);

require_once "../../dbconfig.php"; // Adjust path
session_start();

// --- Add execution confirmation log ---
error_log("--- EXECUTING get_appointment_details.php --- (Correct Version) for appointment_id: " . ($_GET['appointment_id'] ?? 'NOT SET'));

header('Content-Type: application/json'); // Set header early

// --- Authentication & Input Validation ---
if (!isset($_SESSION['account_ID'])) {
    error_log("Auth Error: Session account_ID not set.");
    echo json_encode(["status" => "error", "message" => "Authentication required."]);
    exit();
}
if (!isset($_GET['appointment_id']) || !filter_var($_GET['appointment_id'], FILTER_VALIDATE_INT)) {
    error_log("Input Error: Invalid or missing appointment_id.");
    echo json_encode(["status" => "error", "message" => "Invalid appointment ID specified."]);
    exit();
}

$appointmentId = filter_var($_GET['appointment_id'], FILTER_SANITIZE_NUMBER_INT);
$userId = $_SESSION['account_ID'];
$userType = strtolower($_SESSION['account_Type'] ?? ''); // Safely get user type

global $connection; // Use connection from dbconfig

// --- Authorization Check ---
// Check if user is admin/head OR the client/therapist associated with the appointment
$accessQuery = "SELECT a.account_id, a.therapist_id
                FROM appointments a
                WHERE a.appointment_id = ?";
$stmt_access = $connection->prepare($accessQuery);
$isAuthorized = false;
$appointmentExists = false;

if ($stmt_access) {
    $stmt_access->bind_param("i", $appointmentId);
    $stmt_access->execute();
    $accessResult = $stmt_access->get_result();

    if ($accessResult->num_rows === 1) {
        $appointmentExists = true;
        $appointmentAccess = $accessResult->fetch_assoc();
        // Check authorization conditions
        if (in_array($userType, ["admin", "head therapist"]) ||
            $appointmentAccess["account_id"] == $userId ||
            ($appointmentAccess["therapist_id"] !== null && $appointmentAccess["therapist_id"] == $userId)) {
            $isAuthorized = true;
        }
    }
    $stmt_access->close();
} else {
     error_log("DB Error (Prepare Access Check): " . $connection->error);
     echo json_encode(["status" => "error", "message" => "Database error during authorization check."]);
     $connection->close(); // Close connection on error
     exit();
}

if (!$appointmentExists) {
     error_log("Auth Error: Appointment ID {$appointmentId} not found.");
     echo json_encode(["status" => "error", "message" => "Appointment not found."]);
     $connection->close(); // Close connection
     exit();
}
if (!$isAuthorized) {
    error_log("Auth Error: User {$userId} ({$userType}) not authorized for appointment {$appointmentId}.");
    echo json_encode(["status" => "error", "message" => "You are not authorized to view these details."]);
    $connection->close(); // Close connection
    exit();
}

// --- Fetch Appointment Details ---
// Added aliases for clarity and ensured all necessary fields are selected
$query = "SELECT a.date, a.time, a.status,
                 a.session_type AS raw_session_type, -- Essential for logic
                 -- Construct display session type
                 IF(a.rebooked_by IS NOT NULL AND tr.account_FName IS NOT NULL,
                    CONCAT('Rebooking (', a.session_type, ') by: ', tr.account_FName, ' ', tr.account_LName),
                    a.session_type) AS display_session_type,
                 a.rebooked_by,
                 a.validation_notes, -- Added validation notes
                 p.first_name AS patient_firstname, p.last_name AS patient_lastname,
                 p.profile_picture AS patient_picture,
                 u.account_FName AS client_firstname, u.account_LName AS client_lastname,
                 u.profile_picture AS client_picture,
                 tr.account_FName AS rebooked_by_firstname, tr.account_LName AS rebooked_by_lastname,
                 dr.referral_id, -- Include referral ID
                 dr.official_referral_file, dr.proof_of_booking_referral_file
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          LEFT JOIN users tr ON a.rebooked_by = tr.account_ID -- Alias 'tr' for rebooked therapist
          LEFT JOIN doctor_referrals dr ON a.referral_id = dr.referral_id
          WHERE a.appointment_id = ?";


$stmt_details = $connection->prepare($query);
if (!$stmt_details) {
     error_log("DB Error (Prepare Details): " . $connection->error);
     echo json_encode(["status" => "error", "message" => "Database error preparing details."]);
     $connection->close();
     exit();
}

$stmt_details->bind_param("i", $appointmentId);

if (!$stmt_details->execute()) {
     error_log("DB Error (Execute Details): " . $stmt_details->error);
     echo json_encode(["status" => "error", "message" => "Database error fetching details."]);
     $stmt_details->close();
     $connection->close();
     exit();
}

$result = $stmt_details->get_result();

if ($result->num_rows === 1) {
    $appointment = $result->fetch_assoc();

    // --- Prepare Referral Links ---
    $baseUploadPath = "../../uploads/doctors_referrals/"; // Adjust if needed
    $officialReferralLink = !empty($appointment["official_referral_file"])
        ? "<a href='" . $baseUploadPath . htmlspecialchars($appointment["official_referral_file"]) . "' target='_blank' class='uk-link-reset' uk-tooltip='View Official Referral'><span uk-icon='icon: file-pdf; ratio: 1.1'></span> View Official</a>"
        : null;

    $proofOfBookingLink = !empty($appointment["proof_of_booking_referral_file"])
        ? "<a href='" . $baseUploadPath . htmlspecialchars($appointment["proof_of_booking_referral_file"]) . "' target='_blank' class='uk-link-reset' uk-tooltip='View Proof of Booking'><span uk-icon='icon: file-text; ratio: 1.1'></span> View Proof</a>"
        : null;

    // Combine referral links
    $doctor_referral_html = "N/A";
    if ($officialReferralLink && $proofOfBookingLink) {
        $doctor_referral_html = $officialReferralLink . " / " . $proofOfBookingLink;
    } elseif ($officialReferralLink) {
        $doctor_referral_html = $officialReferralLink;
    } elseif ($proofOfBookingLink) {
         $doctor_referral_html = $proofOfBookingLink;
    }

    // --- Construct Response ---
    $details_array = [
        // Key details for logic (no htmlspecialchars)
        "date" => $appointment["date"], // YYYY-MM-DD
        "time" => $appointment["time"], // HH:MM:SS
        "raw_session_type" => $appointment["raw_session_type"],
        "status" => $appointment["status"],

        // Details for display (apply htmlspecialchars)
        "display_session_type" => htmlspecialchars($appointment["display_session_type"]),
        "patient_name" => htmlspecialchars($appointment["patient_firstname"] . " " . $appointment["patient_lastname"]),
        "client_name" => htmlspecialchars($appointment["client_firstname"] . " " . $appointment["client_lastname"]),
        "rebooked_by_name" => !empty($appointment["rebooked_by_firstname"])
            ? htmlspecialchars($appointment["rebooked_by_firstname"] . " " . $appointment["rebooked_by_lastname"])
            : null,
        "validation_notes" => htmlspecialchars($appointment["validation_notes"] ?? ''),

        // Paths for images
        "patient_picture" => $appointment["patient_picture"] ? "../../uploads/profile_pictures/" . htmlspecialchars($appointment["patient_picture"]) : "../../uploads/profile_pictures/default.png",
        "client_picture" => $appointment["client_picture"] ? "../../uploads/profile_pictures/" . htmlspecialchars($appointment["client_picture"]) : "../../uploads/profile_pictures/default.png",

        // HTML for referral links
        "doctor_referral" => $doctor_referral_html,
        "referral_id" => $appointment["referral_id"]
    ];

     error_log("Successfully fetched details for appointment ID {$appointmentId}");
     echo json_encode([
        "status" => "success",
        "details" => $details_array
    ]);

} else {
    error_log("Data Error: Appointment details not found in DB for ID {$appointmentId}.");
    echo json_encode(["status" => "error", "message" => "Appointment details not found."]);
}

$stmt_details->close();
$connection->close(); // Close connection
?>
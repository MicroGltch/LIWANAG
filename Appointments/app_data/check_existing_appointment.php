<?php
require_once "../../dbconfig.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['account_ID'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit();
}

// Get parameters from the AJAX request
$patient_id = $_GET['patient_id'] ?? null;
$appointment_type = $_GET['appointment_type'] ?? null; // The type the user is TRYING to select NOW

// Validate parameters
if (!$patient_id || !$appointment_type) {
    echo json_encode(["status" => "error", "message" => "Patient ID and Appointment Type are required."]);
    exit();
}

global $connection; // Ensure connection is available

// === MODIFIED QUERY: Include all relevant statuses ===
// Fetch relevant appointments for the patient to check against rules.
// Include 'rebooking' for Rule 2 check.
// Include all waitlist statuses for Rule 1 and the new Rule 3 check.
$query = "SELECT session_type, status, date, time
          FROM appointments
          WHERE patient_id = ?
          AND status IN ('pending', 'approved', 'waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day', 'rebooking')";

$stmt = $connection->prepare($query);
if (!$stmt) {
    error_log("Prepare failed: (" . $connection->errno . ") " . $connection->error); // Log DB errors
    echo json_encode(["status" => "error", "message" => "Database error checking appointments."]);
    exit();
}

$stmt->bind_param("i", $patient_id);

if (!$stmt->execute()) {
    error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error); // Log DB errors
    echo json_encode(["status" => "error", "message" => "Database error executing check."]);
    $stmt->close();
    exit();
}

$result = $stmt->get_result();
$existing_sessions = $result->fetch_all(MYSQLI_ASSOC); // Fetch all results at once
$stmt->close();

// Define waitlist statuses for easier checking
$waitlist_statuses = ['waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day'];

// === Check Rules ===
foreach ($existing_sessions as $existing) {

    // ❌ Rule 1: Prevent duplicate Pending/Approved/Waitlisted session type
    // If an existing active (pending/approved/waitlisted) appointment has the SAME type the user is trying to book now
    $isActiveStatus = in_array($existing['status'], ['pending', 'approved', ...$waitlist_statuses]); // Combine active/waitlist statuses
    if ($isActiveStatus && $existing['session_type'] === $appointment_type) {
        echo json_encode([
            "status" => "error",
            "message" => "This patient already has a <strong>{$existing['status']}</strong> appointment/request for <strong>{$existing['session_type']}</strong>.",
            "existing_session_type" => $existing['session_type'],
            "existing_status" => $existing['status'],
            "existing_date" => $existing['date'],
            "existing_time" => $existing['time']
        ]);
        exit();
    }

    // ❌ Rule 2: If patient has *any* appointment with 'rebooking' status, they should NOT book 'Initial Evaluation' again
    // Check the status directly
    if ($existing['status'] === "rebooking" && $appointment_type === "Initial Evaluation") {
        echo json_encode([
            "status" => "error",
            "message" => "This patient is already in <strong>Rebooking</strong> status. An Initial Evaluation is no longer required.",
            "existing_session_type" => $existing['session_type'], // Show the type that triggered rebooking status
            "existing_status" => $existing['status'],
            "existing_date" => $existing['date'],
            "existing_time" => $existing['time']
        ]);
        exit();
    }

    // ❌ Rule 3 (NEW): Prevent booking 'Initial Evaluation' if already waitlisted for anything (which should only be IE anyway)
    // Check if the existing appointment status indicates they are on a waitlist
    $isExistingWaitlisted = in_array($existing['status'], $waitlist_statuses);
    // Check if the user is currently trying to select 'Initial Evaluation'
    $isTryingToBookIE = ($appointment_type === "Initial Evaluation");

    if ($isExistingWaitlisted && $isTryingToBookIE) {
        echo json_encode([
            "status" => "error",
            "message" => "This patient is already on the <strong>{$existing['status']}</strong> list. You cannot book an Initial Evaluation directly while waitlisted.",
            "existing_session_type" => $existing['session_type'], // Likely 'Initial Evaluation'
            "existing_status" => $existing['status'],
            "existing_date" => $existing['date'], // Will be NULL for 'Any Day' waitlist
            "existing_time" => $existing['time']  // Will be NULL
        ]);
        exit();
    }
} // End foreach loop

// ✅ No conflicts found after checking all existing relevant appointments
echo json_encode(["status" => "success"]);
exit();

?>
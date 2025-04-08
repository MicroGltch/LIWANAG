<?php
// app_data/check_existing_appointment.php
// Checks if a patient has a specific type of appointment with 'pending' or 'approved' status.

header('Content-Type: application/json');
require_once "../../dbconfig.php"; // Adjust path as needed

// --- Input Validation ---
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);
$appointment_type = filter_input(INPUT_GET, 'appointment_type', FILTER_SANITIZE_STRING); // Raw type (e.g., IE-OT)
$check_status_flag = filter_input(INPUT_GET, 'check_status', FILTER_SANITIZE_STRING); // Expect 'pending_approved'

$response = ['exists' => false]; // Default response

if (!$patient_id || !$appointment_type) {
    // Don't treat as error, just return exists: false if input is missing
    echo json_encode($response);
    exit;
}

// Only proceed if the specific check flag is set (optional, but good practice)
// if ($check_status_flag !== 'pending_approved') {
//     echo json_encode($response);
//     exit;
// }

global $connection;

// --- Query for conflicting appointments ---
// Use lowercase statuses to match the database enum/values
$conflicting_statuses = ['pending', 'approved'];
$status_placeholders = implode(',', array_fill(0, count($conflicting_statuses), '?')); // Should result in "?,?"

$sql = "SELECT status, session_type, date, time
        FROM appointments
        WHERE patient_id = ?
          AND session_type = ?
          AND status IN ($status_placeholders)
        LIMIT 1"; // We only need to know if at least one exists

$stmt = $connection->prepare($sql);

if ($stmt) {
    // Build parameters: patient_id (i), session_type (s), status1 (s), status2 (s)...
    $types = 'is' . str_repeat('s', count($conflicting_statuses));
    $params = array_merge([$patient_id, $appointment_type], $conflicting_statuses);

    try {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // CONFLICT FOUND!
            $response['exists'] = true;
            $response['message'] = "This patient already has a '" . htmlspecialchars(ucfirst($row['status'])) . "' request for " . htmlspecialchars($row['session_type']) . ".";
            $response['existing_status'] = $row['status']; // e.g., 'pending'
            $response['existing_session_type'] = $row['session_type']; // e.g., 'IE-OT'
            $response['existing_date'] = $row['date'];

             // Format time for display if exists
             $displayTime = null;
             if ($row['time']) {
                  try {
                      $timeObj = new DateTime($row['time']);
                      $displayTime = $timeObj->format('g:i A'); // e.g., 1:00 PM
                  } catch (Exception $e) { $displayTime = $row['time']; } // Fallback
             }
            $response['existing_time'] = $displayTime;


        } else {
            // No conflicting row found
            $response['exists'] = false;
        }
        $result->free();

    } catch (Exception $e) {
        error_log("Error in check_existing_appointment: " . $e->getMessage());
        // Return exists: false on error to avoid blocking the user unnecessarily? Or return an error status?
        // For now, let's assume no conflict if DB error occurs during check.
         $response['exists'] = false;
         $response['error'] = "Database error during check."; // Optional: for debugging
    } finally {
         $stmt->close();
    }

} else {
    error_log("Error preparing check_existing_appointment statement: " . $connection->error);
     $response['exists'] = false; // Assume no conflict if prepare fails
     $response['error'] = "Database prepare error during check."; // Optional
}

$connection->close();
echo json_encode($response);
exit();
?>

<?php
// require_once "../../dbconfig.php";
// session_start();

// // Check if user is logged in
// if (!isset($_SESSION['account_ID'])) {
//     echo json_encode(["status" => "error", "message" => "User not logged in."]);
//     exit();
// }

// // Get parameters from the AJAX request
// $patient_id = $_GET['patient_id'] ?? null;
// $appointment_type = $_GET['appointment_type'] ?? null; // The type the user is TRYING to select NOW

// // Validate parameters
// if (!$patient_id || !$appointment_type) {
//     echo json_encode(["status" => "error", "message" => "Patient ID and Appointment Type are required."]);
//     exit();
// }

// global $connection; // Ensure connection is available

// // === MODIFIED QUERY: Include all relevant statuses ===
// // Fetch relevant appointments for the patient to check against rules.
// // Include 'rebooking' for Rule 2 check.
// // Include all waitlist statuses for Rule 1 and the new Rule 3 check.
// $query = "SELECT session_type, status, date, time
//           FROM appointments
//           WHERE patient_id = ?
//           AND status IN ('pending', 'approved', 'waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day', 'rebooking')";

// $stmt = $connection->prepare($query);
// if (!$stmt) {
//     error_log("Prepare failed: (" . $connection->errno . ") " . $connection->error); // Log DB errors
//     echo json_encode(["status" => "error", "message" => "Database error checking appointments."]);
//     exit();
// }

// $stmt->bind_param("i", $patient_id);

// if (!$stmt->execute()) {
//     error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error); // Log DB errors
//     echo json_encode(["status" => "error", "message" => "Database error executing check."]);
//     $stmt->close();
//     exit();
// }

// $result = $stmt->get_result();
// $existing_sessions = $result->fetch_all(MYSQLI_ASSOC); // Fetch all results at once
// $stmt->close();

// // Define waitlist statuses for easier checking
// $waitlist_statuses = ['waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day'];

// // === Check Rules ===
// foreach ($existing_sessions as $existing) {

//     // ❌ Rule 1: Prevent duplicate Pending/Approved/Waitlisted session type
//     // If an existing active (pending/approved/waitlisted) appointment has the SAME type the user is trying to book now
//     $isActiveStatus = in_array($existing['status'], ['pending', 'approved', ...$waitlist_statuses]); // Combine active/waitlist statuses
//     if ($isActiveStatus && $existing['session_type'] === $appointment_type) {
//         echo json_encode([
//             "status" => "error",
//             "message" => "This patient already has a <strong>{$existing['status']}</strong> appointment/request for <strong>{$existing['session_type']}</strong>.",
//             "existing_session_type" => $existing['session_type'],
//             "existing_status" => $existing['status'],
//             "existing_date" => $existing['date'],
//             "existing_time" => $existing['time']
//         ]);
//         exit();
//     }

//     // ❌ Rule 2: If patient has *any* appointment with 'rebooking' status, they should NOT book 'Initial Evaluation' again
//     // Check the status directly
//     if ($existing['status'] === "rebooking" && $appointment_type === "Initial Evaluation") {
//         echo json_encode([
//             "status" => "error",
//             "message" => "This patient is already in <strong>Rebooking</strong> status. An Initial Evaluation is no longer required.",
//             "existing_session_type" => $existing['session_type'], // Show the type that triggered rebooking status
//             "existing_status" => $existing['status'],
//             "existing_date" => $existing['date'],
//             "existing_time" => $existing['time']
//         ]);
//         exit();
//     }

//     // ❌ Rule 3 (NEW): Prevent booking 'Initial Evaluation' if already waitlisted for anything (which should only be IE anyway)
//     // Check if the existing appointment status indicates they are on a waitlist
//     $isExistingWaitlisted = in_array($existing['status'], $waitlist_statuses);
//     // Check if the user is currently trying to select 'Initial Evaluation'
//     $isTryingToBookIE = ($appointment_type === "Initial Evaluation");

//     if ($isExistingWaitlisted && $isTryingToBookIE) {
//         echo json_encode([
//             "status" => "error",
//             "message" => "This patient is already on the <strong>{$existing['status']}</strong> list. You cannot book an Initial Evaluation directly while waitlisted.",
//             "existing_session_type" => $existing['session_type'], // Likely 'Initial Evaluation'
//             "existing_status" => $existing['status'],
//             "existing_date" => $existing['date'], // Will be NULL for 'Any Day' waitlist
//             "existing_time" => $existing['time']  // Will be NULL
//         ]);
//         exit();
//     }
// } // End foreach loop

// // ✅ No conflicts found after checking all existing relevant appointments
// echo json_encode(["status" => "success"]);
// exit();

?>
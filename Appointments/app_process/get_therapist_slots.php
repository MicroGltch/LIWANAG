<?php
/* THIS IS USED FOR PATIENT PROFILE SLOTS */
/* THIS IS USED FOR PATIENT PROFILE SLOTS */
/* THIS IS USED FOR PATIENT PROFILE SLOTS */
/* THIS IS USED FOR PATIENT PROFILE SLOTS */
/* THIS IS USED FOR PATIENT PROFILE SLOTS */
/* THIS IS USED FOR PATIENT PROFILE SLOTS */
/* THIS IS USED FOR PATIENT PROFILE SLOTS */

require_once "../../dbconfig.php"; // Adjust path as needed
session_start(); // Access therapist ID

header('Content-Type: application/json'); // Always return JSON

// --- Permission Check ---
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// --- Input Validation ---
if (!isset($_GET['therapist_id']) || !isset($_GET['date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit();
}

$therapist_id = filter_var($_GET['therapist_id'], FILTER_VALIDATE_INT);
$date_str = $_GET['date'];

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
     echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
     exit();
}
// Basic check if therapist ID matches session ID for security
if ($therapist_id !== $_SESSION['account_ID']) {
     echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
     exit();
}

// --- Add Logging ---
error_log("--- get_therapist_slots.php ---");
error_log("Request for TherapistID: $therapist_id, Date: $date_str");
// --- End Logging ---



try {
    $date_obj = new DateTime($date_str);
    $day_of_week = $date_obj->format('l'); // E.g., "Monday"
    error_log("Determined Day of Week: $day_of_week");


    $availability_blocks = [];

    // 1. Check for Overrides on the specific date
    $override_query = "SELECT status, start_time, end_time FROM therapist_overrides
                       WHERE therapist_id = ? AND date = ?";
    $stmt_override = $connection->prepare($override_query);

    if (!$stmt_override) throw new Exception("Prepare failed (override check): " . $connection->error);
    $stmt_override->bind_param("is", $therapist_id, $date_str);
    $stmt_override->execute();
    $result_override = $stmt_override->get_result();
    $override = $result_override->fetch_assoc();
    $stmt_override->close();

    if ($override) {
        if ($override['status'] === 'Unavailable') {
            error_log("Override Status: Unavailable. Returning empty slots.");
            echo json_encode(['status' => 'success', 'slots' => []]);
            exit();
        } elseif ($override['status'] === 'Custom' && $override['start_time'] && $override['end_time']) {
            error_log("Override Status: Custom. Using override times.");
            $availability_blocks[] = ['start' => $override['start_time'], 'end' => $override['end_time']];
        } elseif($override['status'] === 'Custom') {
             error_log("Override Status: Custom but times invalid. Returning empty slots.");
             echo json_encode(['status' => 'success', 'slots' => []]);
             exit();
        }
    } else {
        // 2. No override found, use Default Availability
        error_log("No override found. Checking Default Availability for $day_of_week.");
        $default_query = "SELECT start_time, end_time FROM therapist_default_availability WHERE therapist_id = ? AND day = ?";
        $stmt_default = $connection->prepare($default_query);
        // ... (error check prepare, bind, execute, get_result) ...
        $stmt_default->bind_param("is", $therapist_id, $day_of_week);
        $stmt_default->execute();
        $result_default = $stmt_default->get_result();
        while ($row = $result_default->fetch_assoc()) {
            $availability_blocks[] = ['start' => $row['start_time'], 'end' => $row['end_time']];
        }
        $stmt_default->close();
        error_log("Default Availability Blocks Fetched: " . var_export($availability_blocks, true));

    }

    /// 3. Generate Time Slots
    error_log("Final Availability Blocks Used for Slot Generation: " . var_export($availability_blocks, true));
    $all_slots = [];
    $intervalMinutes = 60; // TODO: Make dynamic?
    $sessionDurationSeconds = $intervalMinutes * 60; // Assuming interval IS session duration

    error_log("Using Interval: $intervalMinutes mins, Session Duration: " . ($sessionDurationSeconds/60) . " mins");

    foreach ($availability_blocks as $block) {
        // --- Use correct keys 'start' and 'end' AS DEFINED above when adding to $availability_blocks ---
        $blockStartStr = $block['start'];
        $blockEndStr = $block['end'];
        // --- End Use correct keys ---

        error_log("Generating slots for block: Start=$blockStartStr, End=$blockEndStr");

        $start = strtotime($blockStartStr);
        $end = strtotime($blockEndStr);

        if ($start === false || $end === false) {
             error_log("Skipping block due to invalid time format: Start=$blockStartStr, End=$blockEndStr");
             continue;
        }

        $current = $start;
        while ($current < $end) {
            $slotEnd = $current + $sessionDurationSeconds;
            if ($slotEnd <= $end) {
                 $slot_start_formatted = date('H:i', $current); // Use H:i (24-hour) for consistency
                 $slot_end_formatted = date('H:i', $slotEnd);
                 error_log("Adding Slot: Start=$slot_start_formatted, End=$slot_end_formatted"); // Log added slot
                 $all_slots[] = [
                    'start' => $slot_start_formatted,
                    'end'   => $slot_end_formatted
                 ];
            } else {
                  error_log("Slot ending at " . date('H:i', $slotEnd) . " exceeds block end " . date('H:i', $end) . ". Stopping generation for this block.");
            }
            $current += $intervalMinutes * 60;
        }
    }

     // Sort final slots
     usort($all_slots, function($a, $b) { return strcmp($a['start'], $b['start']); });

    error_log("Final Generated Slots: " . var_export($all_slots, true)); // Log the final array

    echo json_encode(['status' => 'success', 'slots' => $all_slots]);


} catch (Exception $e) {
    error_log("Error in get_therapist_slots.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal error occurred: ' . $e->getMessage()]);
} finally {
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}
?>
<?php
// get_available_slots_enhanced.php
// Purpose: Find available time slots for IE-OT/IE-BT considering therapist type,
//          schedules, approved appointments, patient schedules, and pending requests.

ini_set('display_errors', 1); // Set to 0 in production
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once "../../dbconfig.php"; // Adjust path as needed

// ========== Helper Functions ==========

/**
 * Fetches relevant settings from the database.
 * @param mysqli $db Database connection object.
 * @return array|null Associative array of settings or null on failure.
 */
function fetch_settings(mysqli $db): ?array {
    $query = "SELECT initial_eval_duration, service_ot_duration, service_bt_duration, playgroup_duration FROM settings LIMIT 1";
    $result = $db->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        // Provide defaults if columns are missing/null
        $row['initial_eval_duration'] = $row['initial_eval_duration'] ?? 60;
        $row['service_ot_duration'] = $row['service_ot_duration'] ?? 60;
        $row['service_bt_duration'] = $row['service_bt_duration'] ?? 60;
        $row['playgroup_duration'] = $row['playgroup_duration'] ?? 120;
        return $row;
    }
    error_log("Could not fetch settings from database.");
    return null;
}

/**
 * Determines the clinic's operating hours for a specific date, considering exceptions.
 * @param mysqli $db Database connection object.
 * @param string $date YYYY-MM-DD date string.
 * @return array [?string $startTime, ?string $endTime] - Time strings (H:i:s) or null if closed.
 */
function get_clinic_hours_for_date(mysqli $db, string $date): array {
    // Check exceptions first
    $stmt_ex = $db->prepare("SELECT start_time, end_time FROM business_hours_exceptions WHERE exception_date = ?");
    $startTime = null;
    $endTime = null;
    if ($stmt_ex) {
        $stmt_ex->bind_param("s", $date);
        $stmt_ex->execute();
        $result_ex = $stmt_ex->get_result();
        if ($override = $result_ex->fetch_assoc()) {
            $startTime = $override['start_time'];
            $endTime = $override['end_time'];
        }
        $stmt_ex->close();
        // If an override exists (even if null times), return it
        if ($result_ex->num_rows > 0) {
             return [$startTime, $endTime];
        }
    } else {
         error_log("Error preparing exception query: " . $db->error);
    }

    // Get default hours for the day name if no exception found
    $dayName = strtolower(date('l', strtotime($date))); // Get 'monday', 'tuesday' etc.
    $stmt_day = $db->prepare("SELECT start_time, end_time FROM business_hours_by_day WHERE LOWER(day_name) = ?");
    if ($stmt_day) {
        $stmt_day->bind_param("s", $dayName);
        $stmt_day->execute();
        $result_day = $stmt_day->get_result();
        if ($default = $result_day->fetch_assoc()) {
            $startTime = $default['start_time'];
            $endTime = $default['end_time'];
        }
        $stmt_day->close();
    } else {
         error_log("Error preparing day hours query: " . $db->error);
    }
    return [$startTime, $endTime];
}

/**
 * Generates potential time slots based on start/end times and an increment.
 * @param string $startTime Start time string (H:i:s).
 * @param string $endTime End time string (H:i:s).
 * @param int $incrementMinutes Increment in minutes (e.g., 15).
 * @return array List of potential start times (H:i:s format).
 */
function generate_potential_slots(string $startTime, string $endTime, int $incrementMinutes): array {
    $slots = [];
    try {
        $current = new DateTime($startTime);
        $end = new DateTime($endTime);
        $interval = new DateInterval("PT{$incrementMinutes}M");

        while ($current < $end) {
            $slots[] = $current->format('H:i:s');
            $current->add($interval);
        }
    } catch (Exception $e) {
         error_log("Error generating time slots: " . $e->getMessage());
    }
    return $slots;
}

/**
 * Fetches active therapists based on required service type.
 * @param mysqli $db Database connection object.
 * @param string $required_service_type 'occupational' or 'behavioral'.
 * @return array List of therapist IDs.
 */
function get_active_therapists_by_type(mysqli $db, string $required_service_type): array {
    $therapist_ids = [];
    // Assuming 'Active' status exists and service_Type allows 'both'
    $sql = "SELECT account_ID FROM users
            WHERE account_Type = 'therapist' AND account_Status = 'Active'
              AND (service_Type = ? OR service_Type = 'both')";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $required_service_type);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $therapist_ids[] = $row['account_ID'];
        }
        $stmt->close();
    } else {
         error_log("Error preparing therapist query: " . $db->error);
    }
    return $therapist_ids;
}

/**
 * Gets the specific availability (start/end times) for a list of therapists on a given date.
 * Considers defaults and overrides.
 * @param mysqli $db Database connection object.
 * @param array $therapist_ids List of therapist IDs.
 * @param string $date YYYY-MM-DD date string.
 * @return array therapist_id => ['is_available' => bool, 'start_time' => ?string, 'end_time' => ?string]
 */
function get_therapist_availability_for_date(mysqli $db, array $therapist_ids, string $date): array {
    $availabilities = [];
    if (empty($therapist_ids)) return $availabilities;

    $ids_placeholder = implode(',', array_fill(0, count($therapist_ids), '?'));
    $types_ids = str_repeat('i', count($therapist_ids)); // Types for therapist IDs only
    $dayName = strtolower(date('l', strtotime($date))); // lowercase day name

    // Initialize all as unavailable by default
    foreach ($therapist_ids as $id) {
        $availabilities[$id] = ['is_available' => false, 'start_time' => null, 'end_time' => null];
    }

    // 1. Get Defaults
    $sql_default = "SELECT therapist_id, start_time, end_time
                    FROM therapist_default_availability
                    WHERE therapist_id IN ($ids_placeholder) AND LOWER(day) = ?";
    $stmt_default = $db->prepare($sql_default);
    if($stmt_default) {
        // *** CORRECTED BINDING ***
        $types_combined = $types_ids . 's';           // Combine types ('iii...s')
        $params = $therapist_ids;                   // Start params array with IDs
        $params[] = $dayName;                       // Add dayName to the end
        $stmt_default->bind_param($types_combined, ...$params); // Unpack the complete params array
        // *** END CORRECTION ***

        $stmt_default->execute();
        $result_default = $stmt_default->get_result();
        while ($row = $result_default->fetch_assoc()) {
            if($row['start_time'] && $row['end_time']) {
                 $availabilities[$row['therapist_id']] = [
                    'is_available' => true,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time']
                ];
            }
        }
        $stmt_default->close();
    } else { error_log("Error preparing default avail query: " . $db->error); }


    // 2. Apply Overrides (these take precedence)
    $sql_override = "SELECT therapist_id, status, start_time, end_time
                     FROM therapist_overrides
                     WHERE therapist_id IN ($ids_placeholder) AND date = ?";
    $stmt_override = $db->prepare($sql_override);
     if($stmt_override) {
         // *** CORRECTED BINDING ***
         $types_combined = $types_ids . 's';      // Combine types ('iii...s') - date is string
         $params = $therapist_ids;              // Start params array with IDs
         $params[] = $date;                     // Add date string to the end
         $stmt_override->bind_param($types_combined, ...$params); // Unpack the complete params array
         // *** END CORRECTION ***

        $stmt_override->execute();
        $result_override = $stmt_override->get_result();
        while ($row = $result_override->fetch_assoc()) {
            // --- Logic for applying overrides (unchanged) ---
            if ($row['status'] === 'Unavailable') {
                $availabilities[$row['therapist_id']] = ['is_available' => false, 'start_time' => null, 'end_time' => null];
            } elseif ($row['status'] === 'Custom' && $row['start_time'] && $row['end_time']) {
                $availabilities[$row['therapist_id']] = [
                    'is_available' => true,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time']
                ];
            }
            elseif ($row['status'] === 'Custom') { // Handle Custom status with null times as unavailable
                 $availabilities[$row['therapist_id']] = ['is_available' => false, 'start_time' => null, 'end_time' => null];
            }
            // --- End Override Logic ---
        }
        $stmt_override->close();
    } else { error_log("Error preparing override avail query: " . $db->error); }


    return $availabilities;
}

/**
 * Fetches appointments details for a specific date and statuses.
 * Calculates end times based on settings.
 * @param mysqli $db Database connection object.
 * @param string $date YYYY-MM-DD date string.
 * @param array $statuses List of statuses (e.g., ['pending']).
 * @param array $settings Application settings including durations.
 * @param string|null $session_type Filter by specific session type if needed.
 * @return array List of appointments with calculated end times.
 */
function get_appointments_details(mysqli $db, string $date, array $statuses, array $settings, ?string $session_type = null): array {
    $appointments = [];
    if (empty($statuses)) {
        error_log("get_appointments_details: No statuses provided.");
        return $appointments; // Exit if no statuses provided
    }

    // --- Build SQL and Parameters Carefully ---
    $sql = "SELECT appointment_id, patient_id, therapist_id, date, time, status, session_type
            FROM appointments
            WHERE date = ?"; // Start with date placeholder

    $params = [$date]; // Start parameter array with date
    $types = 's';      // Start types string with 's' for date

    // Add session_type filter if provided
    if ($session_type !== null && $session_type !== '') {
        $sql .= " AND session_type = ?";
        $params[] = $session_type;
        $types .= 's'; // Add 's' for session_type
    }

    // Add status filter using IN (...)
    if (!empty($statuses)) {
        $status_placeholder = implode(',', array_fill(0, count($statuses), '?'));
        $sql .= " AND status IN ($status_placeholder)";
        // Append ALL status values to the parameter array
        $params = array_merge($params, $statuses);
        // Append an 's' for EACH status to the types string
        $types .= str_repeat('s', count($statuses));
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return $appointments;
    }

    // Bind the final constructed parameters
    if (count($params) > 0) { // Only bind if there are parameters
        if (!$stmt->bind_param($types, ...$params)) {
            $stmt->close();
            return $appointments;
        }
    }

    // --- Execute and fetch ---
    if (!$stmt->execute()) {
         $stmt->close();
         return $appointments;
    }
    $result = $stmt->get_result();
    $result_data = [];
    if ($result) {
         $result_data = $result->fetch_all(MYSQLI_ASSOC); // Fetch all results
         $result->free();
         error_log("get_appointments_details - Fetched Rows: " . count($result_data)); // Log row count
    } else {
        error_log("get_appointments_details - Failed to get result: " . $stmt->error);
    }
     $stmt->close(); // Close statement


    // --- Process results (calculating end times) ---
    foreach ($result_data as $appt) {
        // Calculate duration based on fetched session_type
        $duration = 60; // Default
        switch ($appt['session_type']) {
            case 'IE-OT':
            case 'IE-BT':
                $duration = $settings['initial_eval_duration']; break;
            case 'OT':
                $duration = $settings['service_ot_duration']; break;
            case 'BT':
                $duration = $settings['service_bt_duration']; break;
            case 'Playgroup':
                $duration = $settings['playgroup_duration']; break;
        }

        // Ensure duration is valid before proceeding
        if ($duration <= 0) {
             error_log("Invalid duration calculated for session type '{$appt['session_type']}' - Appointment ID: {$appt['appointment_id']}");
             continue; // Skip this appointment if duration is invalid
        }
        // Ensure time is valid before creating DateTime
        if (empty($appt['time']) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $appt['time'])) {
            error_log("Invalid time format '{$appt['time']}' for appointment ID: {$appt['appointment_id']}");
            continue; // Skip this appointment if time is invalid
        }


        try {
            $start = new DateTime($appt['date'] . ' ' . $appt['time']);
            $end = (clone $start)->add(new DateInterval("PT{$duration}M"));
            $appt['start_dt'] = $start;
            $appt['end_dt'] = $end;
            $appointments[] = $appt;
        } catch (Exception $e) {
            error_log("Error calculating end time for appointment ID {$appt['appointment_id']}: " . $e->getMessage());
            // Decide if you want to skip this appointment or handle differently
        }
    }

    return $appointments;
}


/**
 * Fetches patient default or makeup schedules potentially conflicting on a given day/date.
 * Requires therapist_id link and end_time calculation/storage.
 * @param mysqli $db
 * @param string $date
 * @param array $therapist_ids
 * @param string $schedule_type 'default' or 'makeup'
 * @param array $settings
 * @return array Processed schedules with DateTime objects.
 */
function get_conflicting_patient_schedules(mysqli $db, string $date, array $therapist_ids, string $schedule_type, array $settings): array {
    $processed_schedules = []; // Renamed to avoid confusion with $result_data
    $sql = '';
    $stmt = null;
    $result_data = []; // Array to hold raw results

    // --- Prepare SQL and Parameters ---
    if ($schedule_type === 'default') {
        if (empty($therapist_ids)) return $processed_schedules; // Need therapist IDs

        $ids_placeholder = implode(',', array_fill(0, count($therapist_ids), '?'));
        $id_types = str_repeat('i', count($therapist_ids));
        $dayName = strtolower(date('l', strtotime($date)));

        $sql = "SELECT patient_id, therapist_id, start_time, end_time
                FROM patient_default_schedules
                WHERE therapist_id IN ($ids_placeholder) AND LOWER(day_of_week) = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log("Error preparing default patient schedule query: " . $db->error); return $processed_schedules; }

        $types_combined = $id_types . 's';
        $params = $therapist_ids;
        $params[] = $dayName;
        $stmt->bind_param($types_combined, ...$params);

    } elseif ($schedule_type === 'makeup') {
        // Adjust SQL based on whether therapist_id exists in patient_makeup_schedules
        $sql = "SELECT patient_id, start_time, end_time -- Add therapist_id if available
                FROM patient_makeup_schedules
                WHERE date = ?"; // Modify if checking therapist_id

        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log("Error preparing makeup patient schedule query: " . $db->error); return $processed_schedules; }

        // Adjust binding if therapist_id check is added to SQL
        // Example: if (strpos($sql, 'therapist_id') !== false && !empty($therapist_ids)) { ... }
        $stmt->bind_param('s', $date); // Currently only binds date

    } else {
        return $processed_schedules; // Invalid type
    }

    // --- Execute, Fetch ALL Results, Close Statement ---
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            $result_data = $result->fetch_all(MYSQLI_ASSOC); // Fetch everything now
            $result->free(); // Free the result set memory
        } else {
             error_log("Failed to get result for patient schedule query: " . $stmt->error);
        }
    } else {
         error_log("Failed to execute patient schedule query: " . $stmt->error);
    }
    // *** Close the statement REGARDLESS of success/failure fetching results ***
    if ($stmt) {
        $stmt->close();
    }
    // --- End Immediate DB Interaction ---


    // --- Process the Fetched Results ---
    foreach ($result_data as $sched) {
        // Calculate/validate end time if necessary
        $sched_duration = $settings['service_ot_duration']; // Example default

        if (empty($sched['end_time'])) {
             if (!empty($sched['start_time'])) {
                 try {
                    $sched['end_time'] = (new DateTime($sched['start_time']))->add(new DateInterval("PT{$sched_duration}M"))->format('H:i:s');
                 } catch (Exception $e) {
                     error_log("Could not calculate end time for patient schedule: " . $e->getMessage());
                     continue; // Skip processing this schedule
                 }
             } else {
                 error_log("Missing start time for patient schedule, cannot calculate end time.");
                 continue; // Skip processing
             }
        }

        // Ensure start/end times are valid before creating DateTime
        if (empty($sched['start_time']) || empty($sched['end_time'])) {
             error_log("Invalid start or end time for patient schedule after potential calculation.");
             continue;
        }

        // Create DateTime objects
        try {
            $sched_date = ($schedule_type === 'makeup' && isset($sched['date'])) ? $sched['date'] : $date;
            $start = new DateTime($sched_date . ' ' . $sched['start_time']);
            $end = new DateTime($sched_date . ' ' . $sched['end_time']);
            $sched['start_dt'] = $start;
            $sched['end_dt'] = $end;
            $processed_schedules[] = $sched; // Add the processed schedule
        } catch (Exception $e) {
            error_log("Error processing patient schedule time ({$sched['start_time']} - {$sched['end_time']}): " . $e->getMessage());
        }
    } // End processing loop

    return $processed_schedules;
}

/**
 * Checks if two time intervals overlap.
 * Assumes inputs are DateTime objects. Start is inclusive, End is exclusive.
 * @param DateTime $start1
 * @param DateTime $end1
 * @param DateTime $start2
 * @param DateTime $end2
 * @return bool True if they overlap, false otherwise.
 */
function check_datetime_overlap(DateTime $start1, DateTime $end1, DateTime $start2, DateTime $end2): bool {
    return $start1 < $end2 && $end1 > $start2;
}


// ========== Main Script Logic ==========

global $connection; // Use the global connection from dbconfig.php

// --- Input Validation ---
$date = $_GET['date'] ?? null;
$appointment_type = $_GET['appointment_type'] ?? null; // Expecting "IE-OT", "IE-BT", "Playgroup"

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing date parameter.']);
    exit;
}
if (!$appointment_type || !in_array($appointment_type, ['IE-OT', 'IE-BT', 'Playgroup'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing appointment type parameter.']);
    exit;
}

// --- Handle Playgroup Separately ---
// Playgroups handled by selecting existing sessions, not generating slots here.
if ($appointment_type === 'playgroup') {
    echo json_encode(['status' => 'success', 'available_slots' => [], 'pending_slots' => [], 'message' => 'Playgroup availability is based on pre-defined sessions.']);
    exit;
}

// --- Fetch Settings and Clinic Hours ---
$settings = fetch_settings($connection);
if (!$settings) {
    echo json_encode(['status' => 'error', 'message' => 'Could not load system settings.']);
    exit;
}

list($clinicStartTimeStr, $clinicEndTimeStr) = get_clinic_hours_for_date($connection, $date);
if (!$clinicStartTimeStr || !$clinicEndTimeStr) {
    echo json_encode(['status' => 'closed', 'message' => 'The center is closed on this date.']);
    exit;
}

// --- Determine Session Duration ---
$sessionDurationMinutes = 0;
if ($appointment_type === 'IE-OT' || $appointment_type === 'IE-BT') {
     $sessionDurationMinutes = $settings['initial_eval_duration'];
}
// Add other types if this script handles them in the future
if ($sessionDurationMinutes <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid duration configured for this appointment type.']);
    exit;
}
$sessionDurationInterval = new DateInterval("PT{$sessionDurationMinutes}M");


// --- Determine Required Therapist Service Type ---
$required_service_type = null;
if ($appointment_type === 'IE-OT') $required_service_type = 'occupational';
elseif ($appointment_type === 'IE-BT') $required_service_type = 'behavioral';

if (!$required_service_type) {
    echo json_encode(['status' => 'error', 'message' => 'Cannot determine required therapist specialty.']);
    exit;
}

// --- Fetch Data for Checks ---
$therapist_ids = get_active_therapists_by_type($connection, $required_service_type);
if (empty($therapist_ids)) {
    echo json_encode(['status' => 'fully_booked', 'message' => 'No therapists available for this service type.']);
    exit;
}

$therapist_availabilities = get_therapist_availability_for_date($connection, $therapist_ids, $date);
$approved_appointments = get_appointments_details($connection, $date, ['approved'], $settings); // Check Approved
$pending_appointments_this_type = get_appointments_details(
    $connection,
    $date,
    ['pending'], // Is the status definitely 'Pending' (case-sensitive)?
    $settings,
    $appointment_type // Is $appointment_type EXACTLY matching 'IE-OT' or 'IE-BT' as stored in the DB?
);
// *** Add Logging Here ***
error_log("----- Checking Pending Slots -----");
error_log("Date: " . $date);
error_log("Requested Type: " . $appointment_type);
error_log("Fetched Pending Apps: " . print_r($pending_appointments_this_type, true));
error_log("----- End Checking Pending Slots -----");

// Fetch patient schedules (Default and Makeup) that might conflict
// *** These require therapist_id and end times to be available/calculable ***
$conflicting_default_schedules = get_conflicting_patient_schedules($connection, $date, $therapist_ids, 'default', $settings);
$conflicting_makeup_schedules = get_conflicting_patient_schedules($connection, $date, $therapist_ids, 'makeup', $settings);


// --- Generate and Check Potential Slots ---
$potential_start_times = generate_potential_slots($clinicStartTimeStr, $clinicEndTimeStr, 15); // Check every 15 minutes
$available_slots = [];
$pending_slots = []; // Slots available but have a pending request already

foreach ($potential_start_times as $slot_start_str) {
    try {
        $slotStartDt = new DateTime($date . ' ' . $slot_start_str);
        $slotEndDt = (clone $slotStartDt)->add($sessionDurationInterval);

        // Basic check: Ensure slot END is within clinic hours
        $clinicEndDt = new DateTime($date . ' ' . $clinicEndTimeStr);
        if ($slotEndDt > $clinicEndDt) {
            continue; // Slot extends beyond closing time
        }

        // Check 1: Is there already a pending appointment for this exact type and time?
        $hasPendingConflict = false;
        
        error_log("Checking slot: {$slot_start_str}"); // Optional: log slot being checked
        foreach ($pending_appointments_this_type as $pending_app) {
            // *** Add Logging Here ***
            $db_time = $pending_app['time'];
            error_log("Comparing Slot=[{$slot_start_str}] vs Pending=[{$db_time}]");
       
            // Perform the comparison
            if ($db_time == $slot_start_str) {
                error_log("----> MATCH FOUND for {$slot_start_str}!"); // See if this appears
                $hasPendingConflict = true;
                break; // Exit inner loop once match found
            }
        }
         error_log("Slot {$slot_start_str} - Pending Conflict Found: " . ($hasPendingConflict ? 'Yes' : 'No')); // Optional: log result for slot
       
        // ... rest of the therapist availability check ...

        // Check 2: Find if ANY suitable therapist is actually free
        $foundAvailableTherapist = false;
        foreach ($therapist_ids as $therapist_id) {
            $therapist_schedule = $therapist_availabilities[$therapist_id] ?? ['is_available' => false];

            // A) Is therapist scheduled to work during the slot?
            if (!$therapist_schedule['is_available']) continue; // Skip if therapist not working at all today
            $therapistStartDt = new DateTime($date . ' ' . $therapist_schedule['start_time']);
            $therapistEndDt = new DateTime($date . ' ' . $therapist_schedule['end_time']);
             if ($slotStartDt < $therapistStartDt || $slotEndDt > $therapistEndDt) {
                 continue; // Therapist not working during this specific slot time
             }

            // B) Does therapist have an APPROVED appointment conflict?
            $hasApprovedConflict = false;
            foreach ($approved_appointments as $app) {
                 if ($app['therapist_id'] == $therapist_id) {
                     if (check_datetime_overlap($slotStartDt, $slotEndDt, $app['start_dt'], $app['end_dt'])) {
                         $hasApprovedConflict = true;
                         break;
                     }
                 }
             }
             if ($hasApprovedConflict) continue; // Therapist busy with approved appt, try next therapist

            // C) Does therapist have a PATIENT DEFAULT schedule conflict?
             $hasDefaultSchedConflict = false;
             foreach ($conflicting_default_schedules as $sched) {
                 if (isset($sched['therapist_id']) && $sched['therapist_id'] == $therapist_id) { // Check therapist ID exists
                     if (check_datetime_overlap($slotStartDt, $slotEndDt, $sched['start_dt'], $sched['end_dt'])) {
                         $hasDefaultSchedConflict = true;
                         break;
                     }
                 }
             }
             if ($hasDefaultSchedConflict) continue; // Therapist busy with default patient, try next

            // D) Does therapist have a PATIENT MAKEUP schedule conflict?
            // *** This requires therapist_id on the makeup schedule table ***
             $hasMakeupSchedConflict = false;
             foreach ($conflicting_makeup_schedules as $sched) {
                  // Check if therapist_id is set and matches. If not set, this check cannot be performed accurately per therapist.
                 if (isset($sched['therapist_id']) && $sched['therapist_id'] == $therapist_id) {
                     if (check_datetime_overlap($slotStartDt, $slotEndDt, $sched['start_dt'], $sched['end_dt'])) {
                         $hasMakeupSchedConflict = true;
                         break;
                     }
                 }
                 // If therapist_id is not on makeup table, you might have to check if *any* makeup schedule conflicts
                 // regardless of therapist, making the slot unavailable if ANY therapist *could* be assigned. (Less precise)
             }
             if ($hasMakeupSchedConflict) continue; // Therapist busy with makeup patient, try next


            // If we reach here, this therapist IS available for this slot
            $foundAvailableTherapist = true;
            break; // Found one, no need to check others for this time slot
        } // End loop through therapists

        // --- Classify the Slot ---
        if ($foundAvailableTherapist) {
            if ($hasPendingConflict) {
                $pending_slots[] = $slot_start_str; // Available, but warn user
            } else {
                $available_slots[] = $slot_start_str; // Truly available
            }
        }
        // If no therapist found, slot is unavailable/fully booked

    } catch (Exception $e) {
        error_log("Error checking slot {$slot_start_str} on {$date}: " . $e->getMessage());
        continue; // Skip this slot if there was an error processing it
    }

} // End loop through potential slots


// --- Prepare and Return Response ---
$available_slots = array_unique($available_slots); // Remove duplicates if generation logic overlaps
$pending_slots = array_unique($pending_slots);     // Remove duplicates

sort($available_slots); // Optional: sort times
sort($pending_slots);   // Optional: sort times


if (empty($available_slots) && empty($pending_slots)) {
    echo json_encode([
        'status' => 'fully_booked',
        'available_slots' => [],
        'pending_slots' => [],
        'message' => 'No available slots found for this date and service type.'
    ]);
} else {
    echo json_encode([
        'status' => 'success',
        'available_slots' => $available_slots, // e.g., ["09:00:00", "10:00:00"]
        'pending_slots' => $pending_slots,     // e.g., ["11:00:00"]
        'message' => 'Available times loaded.'
    ]);
}

$connection->close();
?>
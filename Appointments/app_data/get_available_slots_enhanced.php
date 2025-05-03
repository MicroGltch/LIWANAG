<?php
// get_available_slots_enhanced.php - Revision with enhanced logging

ini_set('display_errors', 1); // Set to 1 for debugging, 0 in production
error_reporting(E_ALL);
// Log errors to a file instead of displaying them, safer for AJAX endpoints
ini_set('log_errors', 1);
// Optionally set a specific error log file (ensure the directory exists and is writable by the web server)
// ini_set('error_log', 'C:/xampp/php/logs/php_error_log'); // Example path for XAMPP


header('Content-Type: application/json');

// Function to safely close DB connection
function close_db_connection($connection) {
    if (isset($connection) && $connection instanceof mysqli && $connection->thread_id) {
        try {
             $connection->close();
        } catch (Exception $e) {
             // Optionally log error during close, but don't stop script
             error_log("GetSlots Warning: Error closing DB connection: " . $e->getMessage());
        }
    }
}

// Function to send JSON response and exit
function send_json_response($data, $connection = null) {
    if ($connection) {
        close_db_connection($connection);
    }
    // Prevent caching of AJAX response
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    echo json_encode($data);
    exit;
}


require_once "../../dbconfig.php"; // Adjust path as needed
// Ensure connection was successful after require_once
if (!isset($connection) || $connection->connect_error) {
     error_log("GetSlots Fatal Error: Database connection failed after require_once. Error: " . ($connection->connect_error ?? 'Unknown error'));
     // Send response without connection object as it might be invalid
     send_json_response(['status' => 'error', 'message' => 'Database connection failed.']);
}


// ========== Helper Functions ==========

/**
 * Fetches relevant settings from the database.
 * @param mysqli $db Database connection object.
 * @return array|null Associative array of settings or null on failure.
 */
function fetch_settings(mysqli $db): ?array {
    $query = "SELECT initial_eval_duration, service_ot_duration, service_bt_duration, playgroup_duration FROM settings LIMIT 1";
    $result = null; // Initialize result
    try {
         $result = $db->query($query);
         if ($result && $row = $result->fetch_assoc()) {
             // Provide defaults and ensure they are integers
             $row['initial_eval_duration'] = isset($row['initial_eval_duration']) ? (int)$row['initial_eval_duration'] : 60;
             $row['service_ot_duration'] = isset($row['service_ot_duration']) ? (int)$row['service_ot_duration'] : 60;
             $row['service_bt_duration'] = isset($row['service_bt_duration']) ? (int)$row['service_bt_duration'] : 60;
             $row['playgroup_duration'] = isset($row['playgroup_duration']) ? (int)$row['playgroup_duration'] : 120;
             $result->free();
             return $row;
         } else {
              error_log("GetSlots Error: Could not fetch settings or no settings found. DB Error: " . $db->error);
              if ($result) $result->free(); // Free result even if no rows found
              return null;
         }
    } catch (Exception $e) {
         error_log("GetSlots Error: Exception fetching settings: " . $e->getMessage());
         if ($result && $result instanceof mysqli_result) $result->free();
         return null;
    }
}


/**
 * Determines the clinic's operating hours for a specific date, considering exceptions.
 * @param mysqli $db Database connection object.
 * @param string $date YYYY-MM-DD date string.
 * @return array [?string $startTime, ?string $endTime] - Time strings (H:i:s format) or [null, null] if closed/invalid.
 */
function get_clinic_hours_for_date(mysqli $db, string $date): array {
     $startTime = null;
     $endTime = null;
     $exceptionApplied = false; // Flag to track if an exception was found

     // 1. Check exceptions first
     $sql_ex = "SELECT start_time, end_time FROM business_hours_exceptions WHERE exception_date = ?";
     $stmt_ex = $db->prepare($sql_ex);
     if ($stmt_ex) {
         $stmt_ex->bind_param("s", $date);
         if ($stmt_ex->execute()) {
             $result_ex = $stmt_ex->get_result();
             if ($override = $result_ex->fetch_assoc()) {
                 $exceptionApplied = true; // Mark that an exception record exists
                 $startTime = $override['start_time'];
                 $endTime = $override['end_time'];
                 // Log the fetched exception details
                 error_log("GetSlots Info: Found exception record for {$date}: Start='{$startTime}', End='{$endTime}'");
             }
              if ($result_ex) $result_ex->free();
         } else { error_log("GetSlots Error: Failed to execute exception query: " . $stmt_ex->error); }
         $stmt_ex->close();
     } else { error_log("GetSlots Error: Failed to prepare exception query: " . $db->error); }

     // If an exception record was found, use its times (even if null) and return
     if ($exceptionApplied) {
          // Validate format ONLY if times are not null
          if (($startTime !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) ||
              ($endTime !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime))) {
              error_log("GetSlots Error: Invalid time format in exception for date {$date}. Start: {$startTime}, End: {$endTime}. Treating as closed.");
              return [null, null];
          }
          error_log("GetSlots Info: Using exception hours for {$date}: Start='{$startTime}', End='{$endTime}'");
          // If both start and end are null from exception, it means closed for the day
          if ($startTime === null && $endTime === null) {
               error_log("GetSlots Info: Exception indicates closed for {$date}.");
          }
          return [$startTime, $endTime];
     }

     // 2. Get default hours if no exception applied
     $dayName = strtolower(date('l', strtotime($date)));
     error_log("GetSlots Info: No exception found for {$date}. Checking default hours for {$dayName}.");
     $sql_day = "SELECT start_time, end_time FROM business_hours_by_day WHERE LOWER(day_name) = ?";
     $stmt_day = $db->prepare($sql_day);
     if ($stmt_day) {
         $stmt_day->bind_param("s", $dayName);
         if ($stmt_day->execute()) {
             $result_day = $stmt_day->get_result();
             if ($default = $result_day->fetch_assoc()) {
                 $startTime = $default['start_time'];
                 $endTime = $default['end_time'];
             }
              if ($result_day) $result_day->free();
         } else { error_log("GetSlots Error: Failed to execute default hours query: " . $stmt_day->error); }
         $stmt_day->close();
     } else { error_log("GetSlots Error: Failed to prepare default hours query: " . $db->error); }

     // Validate format of default hours if times are not null
     if (($startTime !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) ||
         ($endTime !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime))) {
         error_log("GetSlots Error: Invalid time format in default hours for day {$dayName}. Start: {$startTime}, End: {$endTime}. Treating as closed.");
         return [null, null];
     }
     // Convert H:i to H:i:s if needed for consistency, assuming H:i is stored
     if ($startTime !== null && strlen($startTime) === 5) $startTime .= ':00';
     if ($endTime !== null && strlen($endTime) === 5) $endTime .= ':00';

     error_log("GetSlots Info: Using default hours for {$dayName} ({$date}): Start='{$startTime}', End='{$endTime}'");
     return [$startTime, $endTime];
}


/**
 * Generates potential time slots based on start/end times and an increment.
 * @param string $startTime Start time string (H:i:s format).
 * @param string $endTime End time string (H:i:s format).
 * @param int $incrementMinutes Increment in minutes (e.g., 15).
 * @return array List of potential start times (H:i:s format).
 */
function generate_potential_slots(string $startTime, string $endTime, int $incrementMinutes): array {
    $slots = [];
    // Require H:i:s format now
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime) || $incrementMinutes <= 0) {
         error_log("GetSlots Error: Invalid input (expecting H:i:s) to generate_potential_slots. Start='{$startTime}', End='{$endTime}', Increment={$incrementMinutes}");
         return [];
    }
    try {
        $current = new DateTime($startTime);
        $end = new DateTime($endTime);
        if ($current >= $end) {
            error_log("GetSlots Warning: Start time '{$startTime}' is not before end time '{$endTime}' in generate_potential_slots.");
            return [];
        }
        $interval = new DateInterval("PT{$incrementMinutes}M");
        while ($current < $end) {
            $slots[] = $current->format('H:i:s');
            $current->add($interval);
        }
    } catch (Exception $e) { error_log("GetSlots Error: Exception in generate_potential_slots: " . $e->getMessage()); }
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
     $sql = "SELECT account_ID FROM users WHERE LOWER(account_Type) = 'therapist' AND LOWER(account_Status) = 'active' AND (LOWER(service_Type) = ? OR LOWER(service_Type) = 'both')";
     $stmt = $db->prepare($sql);
     if ($stmt) {
         $lc_service_type = strtolower($required_service_type);
         $stmt->bind_param("s", $lc_service_type);
         if ($stmt->execute()) {
             $result = $stmt->get_result();
             while ($row = $result->fetch_assoc()) { $therapist_ids[] = $row['account_ID']; }
              if($result) $result->free();
         } else { error_log("GetSlots Error: Failed to execute therapist query: " . $stmt->error); }
         $stmt->close();
     } else { error_log("GetSlots Error: Failed to prepare therapist query: " . $db->error); }
     return $therapist_ids;
}


/**
 * Gets the specific availability (start/end times) for a list of therapists on a given date.
 * Considers defaults and overrides. Returns only validated schedule entries in H:i:s format.
 * @param mysqli $db Database connection object.
 * @param array $therapist_ids List of therapist IDs.
 * @param string $date YYYY-MM-DD date string.
 * @return array therapist_id => ['start_time' => string H:i:s, 'end_time' => string H:i:s] for available therapists.
 */
function get_therapist_availability_for_date(mysqli $db, array $therapist_ids, string $date): array {
    $availabilities = [];
    if (empty($therapist_ids)) return $availabilities;
    $raw_avail = [];
    foreach ($therapist_ids as $id) { $raw_avail[$id] = ['start_time' => null, 'end_time' => null, 'status' => 'default_unavailable']; }

    $ids_placeholder = implode(',', array_fill(0, count($therapist_ids), '?'));
    $types_ids = str_repeat('i', count($therapist_ids));
    $dayName = strtolower(date('l', strtotime($date)));

    // 1. Get Defaults (fetch logic remains same)
    $sql_default = "SELECT therapist_id, start_time, end_time FROM therapist_default_availability WHERE therapist_id IN ($ids_placeholder) AND LOWER(day) = ?";
    $stmt_default = $db->prepare($sql_default);
    if($stmt_default) {
        $types_combined = $types_ids . 's'; $params = $therapist_ids; $params[] = $dayName;
        $stmt_default->bind_param($types_combined, ...$params);
        if ($stmt_default->execute()) {
            $result_default = $stmt_default->get_result();
            while ($row = $result_default->fetch_assoc()) {
                 $therapistId = $row['therapist_id'];
                 $raw_avail[$therapistId] = ['start_time' => $row['start_time'], 'end_time' => $row['end_time'], 'status' => 'default_available'];
            }
             if($result_default) $result_default->free();
        } else { error_log("GetSlots Error: Failed executing default avail query: " . $stmt_default->error); }
        $stmt_default->close();
    } else { error_log("GetSlots Error: Failed preparing default avail query: " . $db->error); }

    // 2. Apply Overrides (fetch logic remains same)
    $sql_override = "SELECT therapist_id, status, start_time, end_time FROM therapist_overrides WHERE therapist_id IN ($ids_placeholder) AND date = ?";
    $stmt_override = $db->prepare($sql_override);
     if($stmt_override) {
         $types_combined = $types_ids . 's'; $params = $therapist_ids; $params[] = $date;
         $stmt_override->bind_param($types_combined, ...$params);
         if ($stmt_override->execute()) {
             $result_override = $stmt_override->get_result();
            while ($row = $result_override->fetch_assoc()) {
                $therapistId = $row['therapist_id'];
                $raw_avail[$therapistId] = ['start_time' => $row['start_time'], 'end_time' => $row['end_time'], 'status' => strtolower($row['status'] ?? 'unknown')];
            }
             if($result_override) $result_override->free();
        } else { error_log("GetSlots Error: Failed executing override avail query: " . $stmt_override->error); }
        $stmt_override->close();
    } else { error_log("GetSlots Error: Failed preparing override avail query: " . $db->error); }

    // 3. Validate and Finalize Availabilities
    error_log("GetSlots Debug: Raw therapist availabilities before validation for {$date}: " . print_r($raw_avail, true));
    foreach ($raw_avail as $therapistId => $data) {
        $isPotentiallyAvailable = in_array($data['status'], ['custom', 'default_available', 'available']);
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];
        $validFormatStartTime = false;
        $validFormatEndTime = false;

        // Ensure H:i:s format and basic structure using regex
        if ($startTime !== null) {
            if (preg_match('/^(\d{2}):(\d{2})$/', $startTime)) { $startTime .= ':00'; } // Convert H:i to H:i:s
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime)) { $validFormatStartTime = true; }
        }
        if ($endTime !== null) {
             if (preg_match('/^(\d{2}):(\d{2})$/', $endTime)) { $endTime .= ':00'; } // Convert H:i to H:i:s
             if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) { $validFormatEndTime = true; }
        }

        $isValidFormat = $validFormatStartTime && $validFormatEndTime;

        if ($isPotentiallyAvailable && $isValidFormat) {
             try {
                 // Use DateTime constructor for implicit value validation AND range check
                 $startDt = new DateTime($startTime); // Throws exception on invalid values like 25:00:00
                 $endDt = new DateTime($endTime);   // Throws exception on invalid values
                 if ($endDt > $startDt) { // Check logical order
                     $availabilities[$therapistId] = ['start_time' => $startTime, 'end_time' => $endTime];
                 } else { error_log("GetSlots Warning: Therapist {$therapistId} on {$date} start '{$startTime}' not before end '{$endTime}'. Marked unavailable."); }
             } catch (Exception $e) {
                 // Catch errors from new DateTime() if values are invalid (e.g., 25:70:00)
                 error_log("GetSlots Warning: Invalid time value for therapist {$therapistId} on {$date}. Times: '{$startTime}' - '{$endTime}'. Error: " . $e->getMessage() . ". Marked unavailable.");
             }
        } else {
            // Log if potentially available but format was wrong
            if ($isPotentiallyAvailable && !$isValidFormat) { error_log("GetSlots Warning: Therapist {$therapistId} potentially available (status '{$data['status']}') but time format invalid/null for {$date}. Start: {$data['start_time']}, End: {$data['end_time']}. Marked unavailable."); }
            // else { // Therapist status was unavailable or unknown, no need to log warning }
        }
    }
    error_log("GetSlots Debug: Final validated therapist availabilities (H:i:s) for {$date}: " . print_r($availabilities, true));
    return $availabilities; // Return only validated, available slots in H:i:s
}



/**
 * Fetches appointments details for a specific date and statuses. Calculates end times.
 * @param mysqli $db Database connection object.
 * @param string $date YYYY-MM-DD date string.
 * @param array $statuses List of statuses (case-insensitive).
 * @param array $settings Application settings including durations.
 * @param string|null $session_type Filter by specific session type (case-sensitive).
 * @return array List of appointments with start_dt and end_dt DateTime objects.
 */
function get_appointments_details(mysqli $db, string $date, array $statuses, array $settings, ?string $session_type = null): array {
    // ... (code from previous version - seems okay) ...
     $appointments = [];
    if (empty($statuses)) { error_log("GetSlots Warning: get_appointments_details called with no statuses."); return $appointments; }
    $sql = "SELECT appointment_id, patient_id, therapist_id, date, time, status, session_type FROM appointments WHERE date = ?";
    $params = [$date]; $types = 's';
    if ($session_type !== null && $session_type !== '') { $sql .= " AND session_type = ?"; $params[] = $session_type; $types .= 's'; }
    $status_placeholder = implode(',', array_fill(0, count($statuses), '?'));
    $sql .= " AND LOWER(status) IN ($status_placeholder)"; foreach ($statuses as $status) { $params[] = strtolower($status); } $types .= str_repeat('s', count($statuses));
    $stmt = $db->prepare($sql);
    if (!$stmt) { error_log("GetSlots Error: Failed preparing appointment details query: " . $db->error); return $appointments; }
    if (!$stmt->bind_param($types, ...$params)) { error_log("GetSlots Error: Failed binding params for appointment details query: " . $stmt->error); $stmt->close(); return $appointments; }
    if (!$stmt->execute()) { error_log("GetSlots Error: Failed executing appointment details query: " . $stmt->error); $stmt->close(); return $appointments; }
    $result = $stmt->get_result(); $result_data = $result ? $result->fetch_all(MYSQLI_ASSOC) : []; if($result) $result->free(); $stmt->close();
    foreach ($result_data as $appt) {
        $apptId = $appt['appointment_id'] ?? 'Unknown'; $duration = null;
        switch ($appt['session_type']) { /* ... duration assignment ... */
             case 'IE-OT': case 'IE-BT': $duration = $settings['initial_eval_duration']; break;
             case 'OT': $duration = $settings['service_ot_duration']; break;
             case 'BT': $duration = $settings['service_bt_duration']; break;
             case 'Playgroup': $duration = $settings['playgroup_duration']; break;
             default: error_log("GetSlots Warning: Unknown session type '{$appt['session_type']}' for Appt ID {$apptId}. Using default 60."); $duration = 60;
        }
        if ($duration === null || $duration <= 0) { error_log("GetSlots Warning: Invalid duration ($duration mins) for Appt ID {$apptId}. Skipping."); continue; }
        if (empty($appt['time']) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $appt['time'])) { error_log("GetSlots Warning: Invalid time format '{$appt['time']}' for Appt ID {$apptId}. Skipping."); continue; }
        try {
            $start = new DateTime($appt['date'] . ' ' . $appt['time']); $end = (clone $start)->add(new DateInterval("PT{$duration}M"));
            if ($end <= $start) { error_log("GetSlots Warning: End time not after start for Appt ID {$apptId}. Skipping."); continue; }
            $appt['start_dt'] = $start; $appt['end_dt'] = $end; $appointments[] = $appt;
        } catch (Exception $e) { error_log("GetSlots Error: Exception creating DateTime for Appt ID {$apptId}: " . $e->getMessage()); }
    } return $appointments;
}


/**
 * Fetches patient default or makeup schedules. Calculates end times.
 * @param mysqli $db
 * @param string $date
 * @param array $therapist_ids Used only for default schedules.
 * @param string $schedule_type 'default' or 'makeup'
 * @param array $settings Application settings including durations.
 * @return array Processed schedules with start_dt and end_dt DateTime objects.
 */
function get_conflicting_patient_schedules(mysqli $db, string $date, array $therapist_ids, string $schedule_type, array $settings): array {
    $processed_schedules = []; $sql = ''; $stmt = null; $result_data = [];
    // --- SQL Preparation (remains same) ---
    if ($schedule_type === 'default') {
        if (empty($therapist_ids)) { error_log("GetSlots Info: get_conflicting_patient_schedules (default) called with no therapist IDs."); return []; }
        $ids_placeholder = implode(',', array_fill(0, count($therapist_ids), '?')); $id_types = str_repeat('i', count($therapist_ids)); $dayName = strtolower(date('l', strtotime($date)));
        $sql = "SELECT id, patient_id, therapist_id, start_time, end_time, day_of_week FROM patient_default_schedules WHERE therapist_id IN ($ids_placeholder) AND LOWER(day_of_week) = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log("GetSlots Error: Failed preparing default schedule query: " . $db->error); return []; }
        $types_combined = $id_types . 's'; $params = $therapist_ids; $params[] = $dayName;
        if (!$stmt->bind_param($types_combined, ...$params)) { error_log("GetSlots Error: Failed binding params for default schedule query: " . $stmt->error); $stmt->close(); return []; }
    } elseif ($schedule_type === 'makeup') {
        $sql = "SELECT id, patient_id, therapist_id, start_time, end_time, date FROM patient_makeup_schedules WHERE date = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) { error_log("GetSlots Error: Failed preparing makeup schedule query: " . $db->error); return []; }
        if (!$stmt->bind_param('s', $date)) { error_log("GetSlots Error: Failed binding params for makeup schedule query: " . $stmt->error); $stmt->close(); return []; }
    } else { error_log("GetSlots Error: Invalid schedule_type '{$schedule_type}'."); return []; }
    // --- Execute & Fetch (remains same) ---
    if (!$stmt->execute()) { error_log("GetSlots Error: Failed executing patient schedule query ({$schedule_type}): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result(); $result_data = $result ? $result->fetch_all(MYSQLI_ASSOC) : []; if($result) $result->free(); $stmt->close();

    // --- Process Results ---
    foreach ($result_data as $sched) {
        $scheduleId = $sched['id'] ?? 'Unknown'; $patientId = $sched['patient_id'] ?? 'Unknown';
        $sched_duration = $settings['service_ot_duration'] ?? 60; // Default duration
        if ($sched_duration <= 0) { error_log("GetSlots Warning: Invalid default duration ($sched_duration mins) for patient schedule ID {$scheduleId}. Skipping."); continue; }

        $startTime = $sched['start_time']; $endTime = $sched['end_time'];
        $validFormatStartTime = false; $validFormatEndTime = false;

         // Convert H:i to H:i:s if needed and validate format
        if ($startTime !== null) {
            if (preg_match('/^(\d{2}):(\d{2})$/', $startTime)) { $startTime .= ':00'; }
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime)) { $validFormatStartTime = true; }
        }
        // Calculate end time if missing and start is valid format
        if ($endTime === null && $validFormatStartTime) {
             try { $startDt = new DateTime($startTime); $endTime = $startDt->add(new DateInterval("PT{$sched_duration}M"))->format('H:i:s'); }
             catch (Exception $e) { error_log("GetSlots Error: Could not calculate end time for patient schedule ID {$scheduleId}: " . $e->getMessage()); $endTime = null; }
        }
         // Validate format of potentially calculated end time
        if ($endTime !== null) {
            if (preg_match('/^(\d{2}):(\d{2})$/', $endTime)) { $endTime .= ':00'; }
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) { $validFormatEndTime = true; }
        }

        // Skip if format is invalid
        if (!($validFormatStartTime && $validFormatEndTime)) { error_log("GetSlots Warning: Invalid time format for patient schedule ID {$scheduleId} (Start='{$sched['start_time']}', End='{$sched['end_time']}'). Skipping."); continue; }

        // Create DateTime objects using validated H:i:s times
        try {
            $start = new DateTime($date . ' ' . $startTime); // Throws on invalid value
            $end = new DateTime($date . ' ' . $endTime);     // Throws on invalid value
            if ($end <= $start) { error_log("GetSlots Warning: Patient schedule ID {$scheduleId} end time not after start time ('{$startTime}' vs '{$endTime}'). Skipping."); continue; }
            $sched['start_dt'] = $start;
            $sched['end_dt'] = $end;
            $processed_schedules[] = $sched;
        } catch (Exception $e) {
            // Catch errors from new DateTime() if values are invalid (e.g., 25:70:00)
            error_log("GetSlots Warning: Invalid time value for patient schedule ID {$scheduleId}. Times: '{$startTime}' - '{$endTime}'. Error: " . $e->getMessage() . ". Skipping.");
        }
    }
    return $processed_schedules;
}

/**
 * Checks if two time intervals overlap. Start inclusive, End exclusive.
 * @param DateTime $start1
 * @param DateTime $end1
 * @param DateTime $start2
 * @param DateTime $end2
 * @return bool True if they overlap, false otherwise.
 */
function check_datetime_overlap(DateTime $start1, DateTime $end1, DateTime $start2, DateTime $end2): bool {
     if ($end1 <= $start1 || $end2 <= $start2) { return false; }
    return $start1 < $end2 && $end1 > $start2;
}

// ========== NEW / MODIFIED HELPER FUNCTIONS FOR OPTIMIZATION ==========

/**
 * Fetches ALL appointments (approved, pending) and patient schedules (default, makeup)
 * for a given date and relevant therapists, calculating DateTime intervals.
 * @param mysqli $db
 * @param string $date
 * @param array $therapist_ids Only fetch schedules/appts linked to these therapists.
 * @param array $settings
 * @return array ['approved' => [...], 'pending_exact' => [...], 'pending_pattern_candidates' => [...], 'schedules' => [...]]
 */
function get_all_relevant_bookings(mysqli $db, string $date, array $therapist_ids, array $settings, string $appointment_type_requested): array {
    $bookings = [
        'approved' => [], // Approved appts for the date
        'pending_exact' => [], // Pending appts for exact date AND type
        'pending_pattern_candidates' => [], // Pending appts on SAME DAY OF WEEK (any date >= today) for SAME TYPE
        'schedules' => [], // Default + Makeup schedules for the date
    ];
    if (empty($therapist_ids)) return $bookings; // Need therapists to check against

    $ids_placeholder = implode(',', array_fill(0, count($therapist_ids), '?'));
    $id_types = str_repeat('i', count($therapist_ids));
    $dayName = strtolower(date('l', strtotime($date)));
    $today = date('Y-m-d');

    // 1. Fetch Appointments (Approved and Pending for the specific date)
    $sql_appts = "SELECT appointment_id, patient_id, therapist_id, date, time, status, session_type
                  FROM appointments
                  WHERE date = ? AND status IN ('approved', 'pending')"; // Fetch both statuses for the specific date
    $stmt_appts = $db->prepare($sql_appts);
    if ($stmt_appts) {
        $stmt_appts->bind_param("s", $date);
        if ($stmt_appts->execute()) {
            $result_appts = $stmt_appts->get_result();
            while ($appt = $result_appts->fetch_assoc()) {
                $duration = null; /* Determine duration based on session_type and settings */
                switch ($appt['session_type']) { case 'IE-OT': case 'IE-BT': $duration = $settings['initial_eval_duration']; break; case 'OT': $duration = $settings['service_ot_duration']; break; case 'BT': $duration = $settings['service_bt_duration']; break; default: $duration = 60; }
                if ($duration > 0 && !empty($appt['time']) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $appt['time'])) {
                    try {
                        $start = new DateTime($appt['date'] . ' ' . $appt['time']); $end = (clone $start)->add(new DateInterval("PT{$duration}M"));
                        if ($end > $start) {
                            $appt['start_dt'] = $start; $appt['end_dt'] = $end;
                            if (strtolower($appt['status']) === 'approved') { $bookings['approved'][] = $appt; }
                            elseif (strtolower($appt['status']) === 'pending' && $appt['session_type'] === $appointment_type_requested) { $bookings['pending_exact'][] = $appt; }
                            // Store other pending types too? Not strictly needed for current logic, but maybe useful later.
                        }
                    } catch (Exception $e) { error_log("GetSlots Error: Exception processing appointment ID {$appt['appointment_id']}: " . $e->getMessage()); }
                }
            }
            if ($result_appts) $result_appts->free();
        } else { error_log("GetSlots Error: Failed exec appointment query: " . $stmt_appts->error); }
        $stmt_appts->close();
    } else { error_log("GetSlots Error: Failed prep appointment query: " . $db->error); }


    // 2. Fetch Pending Pattern Candidates (Same DayOfWeek, Same Type, Future/Today)
    // Ensures COLLATE is used for comparison
    $sql_pending_pattern = "SELECT time FROM appointments
                            WHERE date != ? AND date >= ? AND LOWER(DAYNAME(date)) COLLATE utf8mb4_unicode_ci = ?
                            AND session_type = ? COLLATE utf8mb4_unicode_ci AND LOWER(status) = 'pending'";
    $stmt_pending_pattern = $db->prepare($sql_pending_pattern);
    if ($stmt_pending_pattern) {
        $stmt_pending_pattern->bind_param("ssss", $date, $today, $dayName, $appointment_type_requested);
        if ($stmt_pending_pattern->execute()) {
            $result_pp = $stmt_pending_pattern->get_result();
            while ($pp_appt = $result_pp->fetch_assoc()) {
                if (!empty($pp_appt['time']) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $pp_appt['time'])) {
                    $bookings['pending_pattern_candidates'][$pp_appt['time']] = true; // Use time as key for quick lookup
                }
            }
             if ($result_pp) $result_pp->free();
        } else { error_log("GetSlots Error: Failed exec pending pattern query: " . $stmt_pending_pattern->error); }
        $stmt_pending_pattern->close();
    } else { error_log("GetSlots Error: Failed prep pending pattern query: " . $db->error); }


    // 3. Fetch Patient Default Schedules for relevant therapists & day
    $sql_def_sched = "SELECT id, patient_id, therapist_id, start_time, end_time
                      FROM patient_default_schedules WHERE therapist_id IN ($ids_placeholder) AND LOWER(day_of_week) = ?";
    $stmt_def_sched = $db->prepare($sql_def_sched);
    if ($stmt_def_sched) { /* ... (bind, execute, fetch - similar to previous version) ... */
        $types_combined = $id_types . 's'; $params = $therapist_ids; $params[] = $dayName;
        $stmt_def_sched->bind_param($types_combined, ...$params);
        if ($stmt_def_sched->execute()) {
             $result_ds = $stmt_def_sched->get_result();
             while ($sched = $result_ds->fetch_assoc()) { /* Process and add to $bookings['schedules'] */
                 $sched_duration = $settings['service_ot_duration'] ?? 60; $startTime = $sched['start_time']; $endTime = $sched['end_time']; $validFormatStartTime = false; $validFormatEndTime = false;
                 if ($startTime !== null) { if (preg_match('/^(\d{2}):(\d{2})$/', $startTime)) $startTime .= ':00'; if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime)) $validFormatStartTime = true; }
                 if ($endTime === null && $validFormatStartTime) { try { $startDt = new DateTime($startTime); $endTime = $startDt->add(new DateInterval("PT{$sched_duration}M"))->format('H:i:s'); } catch (Exception $e) { $endTime = null; } }
                 if ($endTime !== null) { if (preg_match('/^(\d{2}):(\d{2})$/', $endTime)) $endTime .= ':00'; if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) $validFormatEndTime = true; }
                 if ($validFormatStartTime && $validFormatEndTime) { try { $start = new DateTime($date.' '.$startTime); $end = new DateTime($date.' '.$endTime); if ($end > $start) { $sched['start_dt'] = $start; $sched['end_dt'] = $end; $bookings['schedules'][] = $sched; } } catch (Exception $e) {} }
             }
              if ($result_ds) $result_ds->free();
        } else { error_log("GetSlots Error: Failed exec default schedule query: " . $stmt_def_sched->error); }
        $stmt_def_sched->close();
    } else { error_log("GetSlots Error: Failed prep default schedule query: " . $db->error); }


    // 4. Fetch Patient Makeup Schedules for the date
    $sql_mk_sched = "SELECT id, patient_id, therapist_id, start_time, end_time FROM patient_makeup_schedules WHERE date = ?";
    $stmt_mk_sched = $db->prepare($sql_mk_sched);
    if ($stmt_mk_sched) { /* ... (bind, execute, fetch - similar to previous version) ... */
        $stmt_mk_sched->bind_param("s", $date);
         if ($stmt_mk_sched->execute()) {
             $result_ms = $stmt_mk_sched->get_result();
             while ($sched = $result_ms->fetch_assoc()) { /* Process and add to $bookings['schedules'] */
                 $sched_duration = $settings['service_ot_duration'] ?? 60; $startTime = $sched['start_time']; $endTime = $sched['end_time']; $validFormatStartTime = false; $validFormatEndTime = false;
                 if ($startTime !== null) { if (preg_match('/^(\d{2}):(\d{2})$/', $startTime)) $startTime .= ':00'; if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime)) $validFormatStartTime = true; }
                 if ($endTime === null && $validFormatStartTime) { try { $startDt = new DateTime($startTime); $endTime = $startDt->add(new DateInterval("PT{$sched_duration}M"))->format('H:i:s'); } catch (Exception $e) { $endTime = null; } }
                 if ($endTime !== null) { if (preg_match('/^(\d{2}):(\d{2})$/', $endTime)) $endTime .= ':00'; if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) $validFormatEndTime = true; }
                 if ($validFormatStartTime && $validFormatEndTime) { try { $start = new DateTime($date.' '.$startTime); $end = new DateTime($date.' '.$endTime); if ($end > $start) { $sched['start_dt'] = $start; $sched['end_dt'] = $end; $bookings['schedules'][] = $sched; } } catch (Exception $e) {} }
             }
             if ($result_ms) $result_ms->free();
         } else { error_log("GetSlots Error: Failed exec makeup schedule query: " . $stmt_mk_sched->error); }
         $stmt_mk_sched->close();
    } else { error_log("GetSlots Error: Failed prep makeup schedule query: " . $db->error); }

    // Log counts of fetched items
    error_log("GetSlots Info: Fetched Bookings for {$date} - Approved: ".count($bookings['approved']).", PendingExact: ".count($bookings['pending_exact']).", PendingPatternTimes: ".count($bookings['pending_pattern_candidates']).", Schedules: ".count($bookings['schedules']));

    return $bookings;
}

/**
 * Pre-processes bookings to create a lookup structure for therapist busy intervals.
 * @param array $bookings Output from get_all_relevant_bookings.
 * @return array therapist_id => [ ['start' => DateTime, 'end' => DateTime], ... ]
 */
function build_therapist_busy_intervals(array $bookings): array {
    $busy_intervals = [];

    // Add approved appointments
    foreach ($bookings['approved'] as $appt) {
        if (isset($appt['therapist_id'], $appt['start_dt'], $appt['end_dt'])) {
            $tid = $appt['therapist_id'];
            if (!isset($busy_intervals[$tid])) $busy_intervals[$tid] = [];
            $busy_intervals[$tid][] = ['start' => $appt['start_dt'], 'end' => $appt['end_dt']];
        }
    }
    // Add patient schedules (default + makeup)
    foreach ($bookings['schedules'] as $sched) {
         if (isset($sched['therapist_id'], $sched['start_dt'], $sched['end_dt'])) {
            $tid = $sched['therapist_id'];
            if (!isset($busy_intervals[$tid])) $busy_intervals[$tid] = [];
            $busy_intervals[$tid][] = ['start' => $sched['start_dt'], 'end' => $sched['end_dt']];
        }
        // Consider adding schedules with null therapist_id to a general busy list if needed
    }
    // Optional: Sort intervals per therapist? Not strictly necessary for overlap check.
    return $busy_intervals;
}

/**
 * Checks if a given slot interval overlaps with any busy intervals for a specific therapist.
 * @param DateTime $slotStartDt
 * @param DateTime $slotEndDt
 * @param int $therapist_id
 * @param array $busy_intervals_by_therapist Output from build_therapist_busy_intervals.
 * @return bool True if there is an overlap, false otherwise.
 */
function is_therapist_busy_during_slot(DateTime $slotStartDt, DateTime $slotEndDt, int $therapist_id, array $busy_intervals_by_therapist): bool {
    if (!isset($busy_intervals_by_therapist[$therapist_id])) {
        return false; // Therapist has no recorded busy intervals
    }
    foreach ($busy_intervals_by_therapist[$therapist_id] as $busy_interval) {
        if (check_datetime_overlap($slotStartDt, $slotEndDt, $busy_interval['start'], $busy_interval['end'])) {
            // error_log("GetSlots Debug:    -> Therapist {$therapist_id}: Conflict with busy interval {$busy_interval['start']->format('H:i:s')}-{$busy_interval['end']->format('H:i:s')}");
            return true; // Found an overlap
        }
    }
    return false; // No overlap found for this therapist
}

// ========== Main Script Logic ==========

global $connection;

error_log("========================================================");
error_log("GetSlots OPTIMIZED Request Start: Date=" . ($_GET['date'] ?? 'N/A') . ", Type=" . ($_GET['appointment_type'] ?? 'N/A'));

// --- Input Validation ---
$date = $_GET['date'] ?? null;
$appointment_type = $_GET['appointment_type'] ?? null;
// (Validation logic unchanged)
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { send_json_response(['status' => 'error', 'message' => 'Invalid date.'], $connection); }
$valid_types = ['IE-OT', 'IE-BT']; if (!$appointment_type || !in_array($appointment_type, $valid_types)) { if ($appointment_type === 'Playgroup') { send_json_response(['status' => 'success', 'available_slots' => [], 'pending_slots' => [], 'message' => 'Playgroup uses predefined sessions.'], $connection); } else { send_json_response(['status' => 'error', 'message' => 'Invalid appointment type.'], $connection); } }

// --- Fetch Settings and Clinic Hours ---
$settings = fetch_settings($connection); if (!$settings) { send_json_response(['status' => 'error', 'message' => 'Could not load settings.'], $connection); }
list($clinicStartTimeStr, $clinicEndTimeStr) = get_clinic_hours_for_date($connection, $date); if ($clinicStartTimeStr === null || $clinicEndTimeStr === null) { error_log("GetSlots Exit: Clinic closed/invalid hours {$date}."); send_json_response(['status' => 'closed', 'message' => 'The center is closed.'], $connection); }
error_log("GetSlots Info: Clinic Hours {$date}: {$clinicStartTimeStr} - {$clinicEndTimeStr}");

// --- Determine Session Duration ---
$sessionDurationMinutes = $settings['initial_eval_duration'] ?? 0; if ($sessionDurationMinutes <= 0) { send_json_response(['status' => 'error', 'message' => 'Invalid duration.'], $connection); } try { $sessionDurationInterval = new DateInterval("PT{$sessionDurationMinutes}M"); } catch (Exception $e) { send_json_response(['status' => 'error', 'message' => 'Invalid duration format.'], $connection); }

// --- Determine Required Therapist Specialty ---
$required_service_type = ($appointment_type === 'IE-OT') ? 'occupational' : 'behavioral';

// --- Fetch and Pre-process Data ---
$all_active_therapist_ids = get_active_therapists_by_type($connection, $required_service_type); if (empty($all_active_therapist_ids)) { error_log("GetSlots Exit: No active therapists '{$required_service_type}'."); send_json_response(['status' => 'fully_booked', 'message' => 'No therapists available.'], $connection); } error_log("GetSlots Info: Active therapists: " . implode(', ', $all_active_therapist_ids));
$therapist_availabilities = get_therapist_availability_for_date($connection, $all_active_therapist_ids, $date); if (empty($therapist_availabilities)) { error_log("GetSlots Exit: No therapists available on {$date}."); send_json_response(['status' => 'fully_booked', 'message' => 'No therapists scheduled.'], $connection); } $available_therapist_ids_on_date = array_keys($therapist_availabilities); error_log("GetSlots Info: Therapists available {$date}: " . implode(', ', $available_therapist_ids_on_date));

// Fetch all bookings (approved, pending, schedules) relevant to available therapists
$all_bookings = get_all_relevant_bookings($connection, $date, $available_therapist_ids_on_date, $settings, $appointment_type);

// Build lookup for therapist busy intervals
$busy_intervals_by_therapist = build_therapist_busy_intervals($all_bookings);
// error_log("GetSlots Debug: Busy Intervals By Therapist: " . print_r($busy_intervals_by_therapist, true)); // Can be very verbose

// Build lookup for pending times (exact date)
$pending_times_exact_date = [];
foreach ($all_bookings['pending_exact'] as $app) { if (isset($app['time'])) $pending_times_exact_date[$app['time']] = true; }
// Pending pattern times are already a lookup map from get_all_relevant_bookings
$pending_pattern_times = $all_bookings['pending_pattern_candidates'];


// --- Generate and Check Potential Slots ---
$slotIncrementMinutes = 60;
$potential_start_times = generate_potential_slots($clinicStartTimeStr, $clinicEndTimeStr, $slotIncrementMinutes); error_log("GetSlots Info: Generated " . count($potential_start_times) . " potential slots.");
$available_slots = []; $pending_slots = [];

foreach ($potential_start_times as $slot_start_str) { // Expecting H:i:s
    //error_log("GetSlots Debug: === Checking slot {$slot_start_str} ===");
    try {
        $slotStartDt = new DateTime($date . ' ' . $slot_start_str);
        $slotEndDt = (clone $slotStartDt)->add($sessionDurationInterval);
        $clinicEndDt = new DateTime($date . ' ' . $clinicEndTimeStr);

        // 1. Check Clinic Hours
        if ($slotEndDt > $clinicEndDt) { continue; }

        // 2. Find if ANY available therapist is free during this slot
        $foundAvailableTherapist = false;
        foreach ($available_therapist_ids_on_date as $therapist_id) {
            $therapist_schedule = $therapist_availabilities[$therapist_id]; // Validated H:i:s times
            // Check if slot is within therapist's working hours (string comparison)
            if ($slot_start_str < $therapist_schedule['start_time'] || $slotEndDt->format('H:i:s') > $therapist_schedule['end_time']) {
                continue; // Slot outside working hours
            }
            // Check if therapist has conflict using pre-built busy intervals
            if (is_therapist_busy_during_slot($slotStartDt, $slotEndDt, $therapist_id, $busy_intervals_by_therapist)) {
                //error_log("GetSlots Debug: -> Therapist {$therapist_id} busy during {$slot_start_str}.");
                continue; // Therapist is busy, try next one
            }

            // If we reach here, this therapist is FREE
            $foundAvailableTherapist = true;
            //error_log("GetSlots Debug: -> Therapist {$therapist_id} FOUND AVAILABLE for slot {$slot_start_str}.");
            break; // Found a free therapist, no need to check others for this slot
        }

        // 3. Classify Slot based on therapist availability and pending status
        if ($foundAvailableTherapist) {
            $isPendingExact = isset($pending_times_exact_date[$slot_start_str]);
            $isPendingPattern = isset($pending_pattern_times[$slot_start_str]); // Check pre-fetched pattern times

            // error_log("GetSlots Debug: Slot {$slot_start_str} - Pending Check: Exact={$isPendingExact}, Pattern={$isPendingPattern}");
            if ($isPendingExact || $isPendingPattern) {
                $pending_slots[] = $slot_start_str;
                // error_log("GetSlots Debug: Slot {$slot_start_str} classified as PENDING.");
            } else {
                $available_slots[] = $slot_start_str;
                 //error_log("GetSlots Debug: Slot {$slot_start_str} classified as AVAILABLE.");
            }
        }
        // else { // No therapist was free for this slot }

    } catch (Exception $e) { error_log("GetSlots Error: Exception processing slot {$slot_start_str} on {$date}: " . $e->getMessage()); }
} // End foreach potential_start_time


// --- Prepare and Return Response ---
// (Final filtering, sorting, response unchanged)
$available_slots = array_diff($available_slots, $pending_slots); $available_slots = array_values(array_unique($available_slots)); $pending_slots = array_values(array_unique($pending_slots)); sort($available_slots); sort($pending_slots);
$response = []; if (empty($available_slots) && empty($pending_slots)) { error_log("GetSlots Result: Final result for {$date}/{$appointment_type} - Fully Booked."); $response = ['status' => 'fully_booked', 'available_slots' => [], 'pending_slots' => [], 'message' => 'No available slots found for this date and service type.']; } else { error_log("GetSlots Result: Final result for {$date}/{$appointment_type} - Available: " . count($available_slots) . ", Pending: " . count($pending_slots)); $response = ['status' => 'success', 'available_slots' => $available_slots, 'pending_slots' => $pending_slots, 'message' => 'Available times loaded.']; } error_log("========================================================");
send_json_response($response, $connection);
?>
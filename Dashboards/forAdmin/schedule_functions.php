<?php

/**
 * Fetches and processes schedule data for a given therapist and date range.
 *
 * @param mysqli $db Database connection object.
 * @param int $therapist_id The ID of the therapist.
 * @param DateTime $startDate The start date of the range.
 * @param DateTime $endDate The end date of the range.
 * @return array|null An array structured for the schedule view, or null on critical error.
 */
function getTherapistScheduleData(mysqli $db, int $therapist_id, DateTime $startDate, DateTime $endDate): ?array {

    // 1. Fetch Settings (Session Durations)
    $settings = fetchScheduleSettings($db);
    if (!$settings) {
        error_log("Critical error: Could not fetch settings for therapist $therapist_id schedule.");
        return null;
    }

    // 2. Fetch All Relevant Data for the *Entire Range*
    $therapistAvailabilities = fetchTherapistAvailabilityRange($db, $therapist_id, $startDate, $endDate);
    $approvedAppointments = fetchApprovedAppointmentsRange($db, $therapist_id, $startDate, $endDate);
    $patientDefaultSchedules = fetchPatientDefaultSchedulesRange($db, $therapist_id); // Fetch all defaults for this therapist

    // 3. Process Data Day by Day
    $finalSchedule = [];
    $interval = new DateInterval('P1D'); // 1 day interval
    $period = new DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day')); // Include end date

    $timeSlotDurationMinutes = 60; // Generate hourly slots for display

    foreach ($period as $currentDate) {
        $dateStr = $currentDate->format('Y-m-d');
        $dayOfWeek = $currentDate->format('l'); // e.g., 'Monday'

        $dayData = [
            'date' => $dateStr,
            'slots' => [] // Initialize slots for the day
        ];

        // Determine therapist's working hours for *this specific* $currentDate
        $workingHours = determineWorkingHoursForDate($therapistAvailabilities, $currentDate); // Returns ['start' => DateTime|null, 'end' => DateTime|null]

        if ($workingHours['start'] && $workingHours['end']) {
            // Therapist is available this day, generate slots

            // Filter appointments for the current day
            $todaysAppointments = array_filter($approvedAppointments, function($appt) use ($dateStr) {
                return $appt['date'] === $dateStr;
            });

            // Filter default schedules relevant for this day of the week
             $todaysDefaultSchedules = array_filter($patientDefaultSchedules, function($sched) use ($dayOfWeek) {
                 return $sched['day_of_week'] === $dayOfWeek;
             });


            // Generate potential slots (e.g., hourly) within working hours
            $slotStart = clone $workingHours['start'];
            while ($slotStart < $workingHours['end']) {
                $slotEnd = clone $slotStart;
                $slotEnd->modify("+{$timeSlotDurationMinutes} minutes");

                 // Ensure slot doesn't exceed working hours end time
                 if ($slotEnd > $workingHours['end']) {
                      $slotEnd = clone $workingHours['end'];
                      // Optional: skip if the resulting slot is now zero or too small
                      if ($slotStart >= $slotEnd) break;
                 }

                $currentSlotInterval = ['start' => $slotStart, 'end' => $slotEnd];
                $slotStatus = 'free';
                $patientName = null;

                // Check against approved appointments
                foreach ($todaysAppointments as $appt) {
                     $apptInterval = getAppointmentInterval($appt, $settings, $currentDate);
                    if ($apptInterval && checkOverlap($currentSlotInterval, $apptInterval)) {
                        $slotStatus = 'occupied';
                        $patientName = $appt['patient_name'];
                        break; // Slot is occupied by an appointment
                    }
                }

                 // If still free, check against default schedules for this day
                if ($slotStatus === 'free') {
                     foreach ($todaysDefaultSchedules as $defaultSched) {
                         $defaultInterval = getDefaultScheduleInterval($defaultSched, $currentDate);
                         if ($defaultInterval && checkOverlap($currentSlotInterval, $defaultInterval)) {
                             $slotStatus = 'occupied';
                             $patientName = $defaultSched['patient_name'];
                             break; // Slot is occupied by a default schedule
                         }
                     }
                }

                // Add the processed slot
                $dayData['slots'][] = [
                    'startTime' => $slotStart->format('H:i:s'), // Use H:i:s for consistency maybe? Or H:i for display?
                    'endTime' => $slotEnd->format('H:i:s'),
                    'status' => $slotStatus, // 'free' or 'occupied'
                    'patientName' => $patientName,
                ];

                // Move to the next slot start time
                 $slotStart->modify("+{$timeSlotDurationMinutes} minutes");
                 // Safety break if something goes wrong with time modification
                  if ($slotStart >= $workingHours['end']) break;
            }

        } else {
            // Therapist is unavailable this day (override or no default)
            // Add a representation of unavailability, or leave slots empty
             $dayData['slots'] = []; // Or: $dayData['status'] = 'unavailable';
        }

        $finalSchedule[] = $dayData;
    } // End foreach date in period

    return $finalSchedule;
}

// --- Helper Functions ---

/** Fetches relevant settings */
function fetchScheduleSettings(mysqli $db): ?array {
    $query = "SELECT initial_eval_duration, service_ot_duration, service_bt_duration FROM settings LIMIT 1";
    $result = $db->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $result->free();
        // Provide defaults if null, ensure integers
        return [
             'IE' => (int)($row['initial_eval_duration'] ?? 60),
             'OT' => (int)($row['service_ot_duration'] ?? 60),
             'BT' => (int)($row['service_bt_duration'] ?? 60),
         ];
    }
    error_log("Failed to fetch settings: " . $db->error);
    return null;
}

/** Fetches default and override availability for the date range */
function fetchTherapistAvailabilityRange(mysqli $db, int $therapist_id, DateTime $startDate, DateTime $endDate): array {
    $availabilities = ['defaults' => [], 'overrides' => []];

    // Fetch Defaults
    $sql_def = "SELECT day, start_time, end_time FROM therapist_default_availability WHERE therapist_id = ?";
    if ($stmt_def = $db->prepare($sql_def)) {
        $stmt_def->bind_param("i", $therapist_id);
        if ($stmt_def->execute()) {
            $res_def = $stmt_def->get_result();
            while ($row = $res_def->fetch_assoc()) {
                $availabilities['defaults'][strtolower($row['day'])] = ['start_time' => $row['start_time'], 'end_time' => $row['end_time']];
            }
             $res_def->free();
        } else { error_log("Error executing default avail query: ".$stmt_def->error); }
        $stmt_def->close();
    } else { error_log("Error preparing default avail query: ".$db->error); }

    // Fetch Overrides for the specific date range
    $sql_ovr = "SELECT date, status, start_time, end_time FROM therapist_overrides WHERE therapist_id = ? AND date BETWEEN ? AND ?";
    if ($stmt_ovr = $db->prepare($sql_ovr)) {
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');
        $stmt_ovr->bind_param("iss", $therapist_id, $startStr, $endStr);
         if ($stmt_ovr->execute()) {
            $res_ovr = $stmt_ovr->get_result();
            while ($row = $res_ovr->fetch_assoc()) {
                 $availabilities['overrides'][$row['date']] = [
                     'status' => strtolower($row['status']),
                     'start_time' => $row['start_time'],
                     'end_time' => $row['end_time']
                 ];
            }
             $res_ovr->free();
        } else { error_log("Error executing override query: ".$stmt_ovr->error); }
        $stmt_ovr->close();
    } else { error_log("Error preparing override query: ".$db->error); }

    return $availabilities;
}

/** Determines actual working hours for a specific date, considering defaults and overrides */
function determineWorkingHoursForDate(array $availabilities, DateTime $currentDate): array {
     $dateStr = $currentDate->format('Y-m-d');
     $dayOfWeek = strtolower($currentDate->format('l')); // lowercase day name

     $startTimeStr = null;
     $endTimeStr = null;
     $isAvailable = true;

     // 1. Check Overrides first
     if (isset($availabilities['overrides'][$dateStr])) {
         $override = $availabilities['overrides'][$dateStr];
         if ($override['status'] === 'unavailable') {
             $isAvailable = false;
         } elseif ($override['status'] === 'custom') {
             $startTimeStr = $override['start_time'];
             $endTimeStr = $override['end_time'];
         }
         // If status is custom but times are null, treat as unavailable? Your business rule.
         if ($override['status'] === 'custom' && (!$startTimeStr || !$endTimeStr)) {
              error_log("Warning: Custom override for therapist on $dateStr has null times. Treating as unavailable.");
              $isAvailable = false;
         }

     } else {
         // 2. No override, check Defaults
         if (isset($availabilities['defaults'][$dayOfWeek])) {
             $default = $availabilities['defaults'][$dayOfWeek];
             $startTimeStr = $default['start_time'];
             $endTimeStr = $default['end_time'];
             if (!$startTimeStr || !$endTimeStr) {
                 error_log("Warning: Default availability for therapist on $dayOfWeek has null times. Treating as unavailable.");
                 $isAvailable = false; // Treat invalid default as unavailable
             }
         } else {
              // No override and no default for this day
              $isAvailable = false;
         }
     }


     // Convert valid time strings to DateTime objects for the specific date
     $startDateTime = null;
     $endDateTime = null;
     if ($isAvailable && $startTimeStr && $endTimeStr) {
         try {
             // Combine date with time string for accurate DateTime object
             $startDateTime = new DateTime($dateStr . ' ' . $startTimeStr);
             $endDateTime = new DateTime($dateStr . ' ' . $endTimeStr);

             // Sanity check: End time must be after start time
             if ($endDateTime <= $startDateTime) {
                 error_log("Warning: Invalid time range for therapist on $dateStr ($startTimeStr - $endTimeStr). End not after start. Treating as unavailable.");
                  $isAvailable = false;
                  $startDateTime = null;
                  $endDateTime = null;
             }
         } catch (Exception $e) {
             error_log("Error creating DateTime for therapist availability on $dateStr: " . $e->getMessage());
             $isAvailable = false; // Error parsing times
             $startDateTime = null;
             $endDateTime = null;
         }
     } else {
          $isAvailable = false; // Ensure consistency if times were null or invalid
     }


     return [
         'start' => $isAvailable ? $startDateTime : null,
         'end' => $isAvailable ? $endDateTime : null
     ];
}


/** Fetches approved appointments within the date range */
function fetchApprovedAppointmentsRange(mysqli $db, int $therapist_id, DateTime $startDate, DateTime $endDate): array {
    $appointments = [];
    $sql = "SELECT a.date, a.time, a.session_type, p.first_name, p.last_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.therapist_id = ?
              AND a.status = 'approved'
              AND a.date BETWEEN ? AND ?";

     if ($stmt = $db->prepare($sql)) {
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');
        $stmt->bind_param("iss", $therapist_id, $startStr, $endStr);
        if ($stmt->execute()) {
             $result = $stmt->get_result();
             while ($row = $result->fetch_assoc()) {
                 $appointments[] = [
                     'date' => $row['date'],
                     'time' => $row['time'], // Expecting HH:MM:SS
                     'session_type' => $row['session_type'],
                     'patient_name' => trim($row['first_name'] . ' ' . $row['last_name'])
                 ];
             }
             $result->free();
        } else { error_log("Error executing approved appt query: ".$stmt->error); }
        $stmt->close();
    } else { error_log("Error preparing approved appt query: ".$db->error); }
    return $appointments;
}

/** Fetches all default schedules for a therapist */
function fetchPatientDefaultSchedulesRange(mysqli $db, int $therapist_id): array {
    $schedules = [];
     $sql = "SELECT ps.day_of_week, ps.start_time, ps.end_time, p.first_name, p.last_name
             FROM patient_default_schedules ps
             JOIN patients p ON ps.patient_id = p.patient_id
             WHERE ps.therapist_id = ?"; // therapist_id is indexed

    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param("i", $therapist_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $schedules[] = [
                    'day_of_week' => $row['day_of_week'], // e.g., 'Monday'
                    'start_time' => $row['start_time'], // Expecting HH:MM:SS
                    'end_time' => $row['end_time'],     // Expecting HH:MM:SS
                    'patient_name' => trim($row['first_name'] . ' ' . $row['last_name'])
                ];
            }
             $result->free();
        } else { error_log("Error executing default schedule query: ".$stmt->error); }
        $stmt->close();
    } else { error_log("Error preparing default schedule query: ".$db->error); }
    return $schedules;
}

/** Calculates the DateTime interval for an appointment */
function getAppointmentInterval(array $appointment, array $settings, DateTime $currentDate): ?array {
    $duration = 60; // Default fallback
    switch ($appointment['session_type']) {
        case 'IE-OT': case 'IE-BT': $duration = $settings['IE']; break;
        case 'OT': $duration = $settings['OT']; break;
        case 'BT': $duration = $settings['BT']; break;
        // Add other types if needed (e.g., Playgroup, though likely handled differently)
    }
    if ($duration <= 0) $duration = 60; // Ensure positive duration

    try {
         $dateStr = $currentDate->format('Y-m-d'); // Use the specific date
         $start = new DateTime($dateStr . ' ' . $appointment['time']);
         $end = clone $start;
         $end->modify("+$duration minutes");
         return ['start' => $start, 'end' => $end];
    } catch (Exception $e) {
        error_log("Error creating interval for appointment time {$appointment['time']} on $dateStr: " . $e->getMessage());
        return null;
    }
}

/** Creates the DateTime interval for a default schedule on a specific date */
function getDefaultScheduleInterval(array $schedule, DateTime $currentDate): ?array {
     try {
         $dateStr = $currentDate->format('Y-m-d');
         $start = new DateTime($dateStr . ' ' . $schedule['start_time']);
         $end = new DateTime($dateStr . ' ' . $schedule['end_time']);
         // Basic validation
         if ($end > $start) {
            return ['start' => $start, 'end' => $end];
         } else {
             error_log("Warning: Default schedule end time not after start for {$schedule['patient_name']} on {$schedule['day_of_week']}");
             return null;
         }
     } catch (Exception $e) {
         error_log("Error creating interval for default schedule time {$schedule['start_time']} for {$schedule['patient_name']}: " . $e->getMessage());
         return null;
     }
}


/** Checks if two time intervals overlap. Start inclusive, End exclusive. */
function checkOverlap(array $interval1, array $interval2): bool {
    // Ensure intervals are valid DateTime objects
    if (!($interval1['start'] instanceof DateTime) || !($interval1['end'] instanceof DateTime) ||
        !($interval2['start'] instanceof DateTime) || !($interval2['end'] instanceof DateTime)) {
        return false;
    }
     // Ensure end is after start within each interval
     if ($interval1['end'] <= $interval1['start'] || $interval2['end'] <= $interval2['start']) {
         return false;
     }

    // Overlap condition: StartA < EndB AND EndA > StartB
    return $interval1['start'] < $interval2['end'] && $interval1['end'] > $interval2['start'];
}

?>
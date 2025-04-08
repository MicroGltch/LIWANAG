<?php
require_once "../../dbconfig.php";

// Helper function to check overlap (Needs careful implementation based on your exact time storage)
function checkOverlap($slotStart, $slotEnd, $bookedStart, $bookedEnd) {
    // Ensure these are comparable objects (e.g., DateTime)
    // Return true if the ranges [$slotStart, $slotEnd) and [$bookedStart, $bookedEnd) overlap
    // Example: return $slotStart < $bookedEnd && $slotEnd > $bookedStart;
    // Needs refinement based on how you define the end time (inclusive/exclusive)
    return $slotStart < $bookedEnd && $slotEnd > $bookedStart;
}


header('Content-Type: application/json');
session_start(); // Keep if needed for any session-based logic, though not strictly for slots

if (!isset($_GET['date']) || !isset($_GET['appointment_type'])) {
    echo json_encode(["status" => "error", "message" => "Date and Appointment Type required."]);
    exit();
}

$dateStr = $_GET['date'];
$appointmentType = $_GET['appointment_type'];
$dayOfWeek = date('l', strtotime($dateStr)); // e.g., 'Monday'

global $connection;

// --- 1. Fetch Settings (Durations) ---
$settingsQuery = "SELECT initial_eval_duration, playgroup_duration FROM settings LIMIT 1";
$settingsResult = $connection->query($settingsQuery);
if (!$settingsResult || $settingsResult->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Could not fetch system settings."]);
    exit();
}
$settings = $settingsResult->fetch_assoc();
$durationMinutes = ($appointmentType === 'Initial Evaluation')
    ? ($settings['initial_eval_duration'] ?? 60)
    : ($settings['playgroup_duration'] ?? 120);

if ($durationMinutes <= 0) {
     echo json_encode(["status" => "error", "message" => "Invalid appointment duration configured."]);
    exit();
}
$slotDurationInterval = new DateInterval("PT{$durationMinutes}M");

// --- 2. Determine Center Hours for the Day (Using logic similar to your form) ---
$centerStartTimeStr = null;
$centerEndTimeStr = null;

// Check exceptions first
$overrideStmt = $connection->prepare("SELECT start_time, end_time FROM business_hours_exceptions WHERE exception_date = ?");
$overrideStmt->bind_param("s", $dateStr);
$overrideStmt->execute();
$overrideResult = $overrideStmt->get_result();
if ($overrideResult->num_rows > 0) {
    $override = $overrideResult->fetch_assoc();
    $centerStartTimeStr = $override['start_time'];
    $centerEndTimeStr = $override['end_time'];
} else {
    // Fallback to default day hours
    $dayStmt = $connection->prepare("SELECT start_time, end_time FROM business_hours_by_day WHERE day_name = ?");
    $dayStmt->bind_param("s", $dayOfWeek);
    $dayStmt->execute();
    $dayResult = $dayStmt->get_result();
    if ($dayResult->num_rows > 0) {
        $dayHours = $dayResult->fetch_assoc();
        $centerStartTimeStr = $dayHours['start_time'];
        $centerEndTimeStr = $dayHours['end_time'];
    }
}
$overrideStmt->close();
if (isset($dayStmt)) $dayStmt->close();

if (empty($centerStartTimeStr) || empty($centerEndTimeStr)) {
    echo json_encode(["status" => "closed", "message" => "Center is closed on this date."]);
    exit();
}

$centerStartDateTime = new DateTime($dateStr . ' ' . $centerStartTimeStr);
$centerEndDateTime = new DateTime($dateStr . ' ' . $centerEndTimeStr);

// --- 3. Fetch Active Therapists ---
$therapists = [];
$therapistQuery = "SELECT account_ID FROM users WHERE account_Type = 'therapist'"; // Add AND status='active' if applicable
$therapistResult = $connection->query($therapistQuery);
while ($row = $therapistResult->fetch_assoc()) {
    $therapists[$row['account_ID']] = ['id' => $row['account_ID'], 'schedule' => [], 'booked' => []];
}

if (empty($therapists)) {
     echo json_encode(["status" => "error", "message" => "No therapists configured."]);
     exit();
}
$therapistIds = array_keys($therapists);
$therapistIdPlaceholders = implode(',', array_fill(0, count($therapistIds), '?'));
$therapistIdTypes = str_repeat('i', count($therapistIds));


// --- 4. Fetch Approved Appointments for the Date & Therapists ---
// We need session_type to determine the duration of the booked slot for overlap check
$approvedApptQuery = "SELECT therapist_id, time, session_type
                      FROM appointments
                      WHERE date = ? AND status = 'Approved' AND therapist_id IS NOT NULL";
$stmt = $connection->prepare($approvedApptQuery);
$stmt->bind_param("s", $dateStr);
$stmt->execute();
$approvedResult = $stmt->get_result();

while ($appt = $approvedResult->fetch_assoc()) {
    $therapistId = $appt['therapist_id'];
    if (!isset($therapists[$therapistId])) continue; // Skip if therapist isn't active/found

    $bookedDurationMinutes = ($appt['session_type'] === 'Initial Evaluation')
        ? ($settings['initial_eval_duration'] ?? 60)
        : ($settings['playgroup_duration'] ?? 120); // Assuming playgroup is the other type booked

    if ($bookedDurationMinutes <= 0) continue; // Skip invalid booked slots

    try {
        $bookedStartTime = new DateTime($dateStr . ' ' . $appt['time']);
        $bookedEndTime = (clone $bookedStartTime)->add(new DateInterval("PT{$bookedDurationMinutes}M"));
        $therapists[$therapistId]['booked'][] = ['start' => $bookedStartTime, 'end' => $bookedEndTime];
    } catch (Exception $e) {
        // Log error: Could not parse booked appointment time
        error_log("Error parsing booked time {$appt['time']} for date {$dateStr}: " . $e->getMessage());
        continue;
    }
}
$stmt->close();


// --- 5. Fetch Therapist Schedules (Defaults + Overrides) ---
$schedules = []; // therapist_id => [ ['start'=>DateTime, 'end'=>DateTime], ... ]

// Get Defaults
$defaultSchedQuery = "SELECT therapist_id, start_time, end_time
                      FROM therapist_default_availability
                      WHERE day = ? AND therapist_id IN ($therapistIdPlaceholders)";
$stmt = $connection->prepare($defaultSchedQuery);
$stmt->bind_param("s" . $therapistIdTypes, $dayOfWeek, ...$therapistIds);
$stmt->execute();
$defaultResult = $stmt->get_result();
while($row = $defaultResult->fetch_assoc()) {
    try {
        $start = new DateTime($dateStr . ' ' . $row['start_time']);
        $end = new DateTime($dateStr . ' ' . $row['end_time']);
        if (!isset($schedules[$row['therapist_id']])) $schedules[$row['therapist_id']] = [];
        $schedules[$row['therapist_id']][] = ['start' => $start, 'end' => $end];
    } catch (Exception $e) { continue; } // Ignore invalid schedule times
}
$stmt->close();

// Apply Overrides
$overrideQuery = "SELECT therapist_id, status, start_time, end_time
                  FROM therapist_overrides
                  WHERE date = ? AND therapist_id IN ($therapistIdPlaceholders)";
$stmt = $connection->prepare($overrideQuery);
$stmt->bind_param("s" . $therapistIdTypes, $dateStr, ...$therapistIds);
$stmt->execute();
$overrideResult = $stmt->get_result();
while ($row = $overrideResult->fetch_assoc()) {
    $tId = $row['therapist_id'];
    // Clear default schedule for this day if overridden
    $schedules[$tId] = [];

    if ($row['status'] === 'Available' || $row['status'] === 'Custom') {
         try {
            $start = new DateTime($dateStr . ' ' . $row['start_time']);
            $end = new DateTime($dateStr . ' ' . $row['end_time']);
            $schedules[$tId][] = ['start' => $start, 'end' => $end];
         } catch (Exception $e) { continue; } // Ignore invalid override times
    }
    // If status is 'Unavailable', schedule remains empty for this therapist
}
$stmt->close();

// Populate the main $therapists array with final schedules
foreach ($schedules as $tId => $scheduleBlocks) {
    if (isset($therapists[$tId])) {
        $therapists[$tId]['schedule'] = $scheduleBlocks;
    }
}


// --- 6. Iterate Through Potential Slots and Check Availability ---
$availableSlots = [];
$currentTime = clone $centerStartDateTime;

while ($currentTime < $centerEndDateTime) {
    $slotStartTime = clone $currentTime;
    $slotEndTime = (clone $currentTime)->add($slotDurationInterval);

    // Ensure the slot doesn't exceed the center's end time
    if ($slotEndTime > $centerEndDateTime) {
        break;
    }

    $isSlotPossible = false;
    foreach ($therapists as $therapistId => $therapistData) {
        $therapistWorks = false;
        // Check if therapist is scheduled during the ENTIRE slot
        foreach ($therapistData['schedule'] as $workBlock) {
            if ($slotStartTime >= $workBlock['start'] && $slotEndTime <= $workBlock['end']) {
                $therapistWorks = true;
                break;
            }
        }

        if (!$therapistWorks) continue; // Try next therapist

        // Check if therapist has conflicting approved appointments
        $hasConflict = false;
        foreach ($therapistData['booked'] as $bookedBlock) {
             // Use the helper function for overlap check
            if (checkOverlap($slotStartTime, $slotEndTime, $bookedBlock['start'], $bookedBlock['end'])) {
                $hasConflict = true;
                break;
            }
        }

        if (!$hasConflict) {
            $isSlotPossible = true; // Found at least one available therapist
            break; // No need to check other therapists for this slot
        }
    }

    if ($isSlotPossible) {
        $availableSlots[] = $slotStartTime->format('H:i'); // Store in 24hr format HH:MM
    }

    // Increment for the next potential slot (e.g., every 15 mins) - Adjust as needed
    // Smaller increments like 15/30 mins are usually better than jumping by duration
    $currentTime->add($slotDurationInterval); 
}

// --- 7. Return Result ---
if (!empty($availableSlots)) {
    echo json_encode(["status" => "success", "available_slots" => $availableSlots]);
} else {
    echo json_encode(["status" => "fully_booked"]);
}

$connection->close();

?>
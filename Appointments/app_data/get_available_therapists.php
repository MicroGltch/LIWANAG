<?php
// app_data/get_available_therapists.php (REVISED AGAIN + LOGGING ADDED)

require_once "../../dbconfig.php"; // Adjust path
session_start();
error_log("--- Executing get_available_therapists.php ---"); // Log script execution

// --- Authentication ---
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type'] ?? ''), ['admin', 'head therapist'])) { // Added null check
    error_log("Authentication failed: Session invalid or user type not allowed.");
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit();
}

// --- Input Validation ---
if (!isset($_GET['date']) || !isset($_GET['time']) || !isset($_GET['session_type'])) {
    error_log("Missing GET params: " . print_r($_GET, true)); // Log received params
    echo json_encode(["status" => "error", "message" => "Date, Time, and Session Type parameters are required."]);
    exit();
}

$date = $_GET['date'];
$time = $_GET['time'];
$session_type = $_GET['session_type'];
error_log("Params Received: Date=$date, Time=$time, SessionType=$session_type"); // Log values used

$dayOfWeek = date('l', strtotime($date));
$qualifiedTherapists = []; // Holds therapists who match the specialty

// --- Determine Required Specialty ---
$required_specialty = null;
if (in_array($session_type, ['IE-OT', 'OT'])) {
    $required_specialty = 'occupational';
} elseif (in_array($session_type, ['IE-BT', 'BT'])) {
    $required_specialty = 'behavioral';
}

if ($required_specialty === null) {
    error_log("Cannot determine specialty or invalid type for therapist check: " . $session_type);
    echo json_encode(["status" => "success", "therapists" => []]); // Return empty list for non-specialty types
    exit();
}
error_log("Required Specialty Determined: " . $required_specialty); // Log determined specialty

global $connection; // Ensure connection is available

// 1️⃣ Fetch QUALIFIED and ACTIVE therapists
$query = "SELECT account_ID, account_FName, account_LName, service_Type -- Select service_Type for logging
          FROM users
          WHERE account_Type = 'therapist'
            AND account_Status = 'Active'
            AND (service_Type = ? OR service_Type = 'both')";

$stmt = $connection->prepare($query);
if (!$stmt) {
    error_log("Prepare Error (Fetch Qualified): " . $connection->error);
    echo json_encode(["status" => "error", "message" => "DB error preparing therapist query."]); // Generic error
    exit();
}

$stmt->bind_param("s", $required_specialty);
if (!$stmt->execute()) {
    error_log("Execute Error (Fetch Qualified): " . $stmt->error);
    echo json_encode(["status" => "error", "message" => "DB error executing therapist query."]);
    $stmt->close();
    exit();
}
$result = $stmt->get_result();

// --- Log Fetched Qualified Therapists ---
$log_fetched = [];
while ($row = $result->fetch_assoc()) {
    $log_fetched[] = $row; // Store raw data for logging
    $qualifiedTherapists[$row['account_ID']] = [
        "id" => $row['account_ID'],
        "name" => $row['account_FName'] . " " . $row['account_LName'],
        "available" => false, // Assume unavailable until checks pass
        "schedule" => "N/A",
        "status" => "Unavailable (No Schedule)" // Default status
    ];
}
$stmt->close(); // Close statement AFTER fetching all rows
error_log("Step 1 - Qualified Therapists Fetched (" . count($log_fetched) . "): " . print_r($log_fetched, true));
// --- End Logging ---

if (empty($qualifiedTherapists)) {
    error_log("No qualified and active therapists found for specialty: " . $required_specialty);
    echo json_encode(["status" => "success", "therapists" => []]); // No qualified therapists
    exit();
}
$qualifiedTherapistIds = array_keys($qualifiedTherapists);

// --- Run Availability Checks ONLY on Qualified Therapists ---

// 2️⃣ Check Default Availability
if (!empty($qualifiedTherapistIds)) { // Check if array is not empty before implode
    $ids_placeholder = implode(',', array_fill(0, count($qualifiedTherapistIds), '?'));
    $types_ids = str_repeat('i', count($qualifiedTherapistIds));
    $query_default = "SELECT therapist_id, start_time, end_time FROM therapist_default_availability WHERE day = ? AND therapist_id IN ($ids_placeholder)";
    $stmt_default = $connection->prepare($query_default);
    if ($stmt_default) {
        $types_combined = 's' . $types_ids;
        $params = [$dayOfWeek]; $params = array_merge($params, $qualifiedTherapistIds);
        if ($stmt_default->bind_param($types_combined, ...$params)) {
             if ($stmt_default->execute()) {
                  $result_default = $stmt_default->get_result();
                  while ($row = $result_default->fetch_assoc()) {
                      if (isset($qualifiedTherapists[$row['therapist_id']])) {
                          $startTimeStr = $row['start_time']; $endTimeStr = $row['end_time']; $appointmentTime = strtotime($time);
                          if ($startTimeStr && $endTimeStr) {
                              try {
                                  $startTime = strtotime($startTimeStr); $endTime = strtotime($endTimeStr);
                                  $qualifiedTherapists[$row['therapist_id']]['schedule'] = date("g:i A", $startTime) . " - " . date("g:i A", $endTime);
                                  if ($appointmentTime >= $startTime && $appointmentTime < $endTime) {
                                      $qualifiedTherapists[$row['therapist_id']]['available'] = true;
                                      $qualifiedTherapists[$row['therapist_id']]['status'] = "Available";
                                  } else {
                                      $qualifiedTherapists[$row['therapist_id']]['available'] = false; // Still false or set explicitly
                                      $qualifiedTherapists[$row['therapist_id']]['status'] = "Time Conflict (Default Schedule)";
                                  }
                              } catch (Exception $e) { error_log("Error processing default schedule for {$row['therapist_id']}: " . $e->getMessage()); $qualifiedTherapists[$row['therapist_id']]['status'] = "Err"; $qualifiedTherapists[$row['therapist_id']]['available'] = false; }
                          } else { $qualifiedTherapists[$row['therapist_id']]['status'] = "Unavailable (Incomplete Schedule)"; $qualifiedTherapists[$row['therapist_id']]['available'] = false; }
                      }
                  }
                  $result_default->free(); // Free result set
             } else { error_log("Execute Error (Default Avail): " . $stmt_default->error); }
        } else { error_log("Bind Param Error (Default Avail): " . $stmt_default->error); }
        $stmt_default->close();
    } else { error_log("Prepare Error (Default Avail): " . $connection->error); }
} else { error_log("Skipping Default Avail check - no qualified IDs."); }


// 3️⃣ Apply Overrides (for qualified therapists)
if (!empty($qualifiedTherapistIds)) { // Check again
    // Use same $ids_placeholder and $types_ids from above
    $query_override = "SELECT therapist_id, status, start_time, end_time FROM therapist_overrides WHERE date = ? AND therapist_id IN ($ids_placeholder)";
    $stmt_override = $connection->prepare($query_override);
    if ($stmt_override) {
        $types_combined = 's' . $types_ids; $params = [$date]; $params = array_merge($params, $qualifiedTherapistIds);
        if ($stmt_override->bind_param($types_combined, ...$params)) {
             if ($stmt_override->execute()) {
                  $result_override = $stmt_override->get_result();
                  while ($row = $result_override->fetch_assoc()) {
                      if (isset($qualifiedTherapists[$row['therapist_id']])) {
                          // Override logic applies HERE, updating status/availability
                          if ($row['status'] === 'Unavailable') {
                              $qualifiedTherapists[$row['therapist_id']]['available'] = false; $qualifiedTherapists[$row['therapist_id']]['schedule'] = "Unavailable (Override)"; $qualifiedTherapists[$row['therapist_id']]['status'] = "Unavailable";
                          } elseif ($row['status'] === 'Custom') {
                              $startTimeStr = $row['start_time']; $endTimeStr = $row['end_time']; $appointmentTime = strtotime($time);
                              if ($startTimeStr && $endTimeStr) {
                                  try {
                                      $startTime = strtotime($startTimeStr); $endTime = strtotime($endTimeStr);
                                      $qualifiedTherapists[$row['therapist_id']]['schedule'] = date("g:i A", $startTime) . " - " . date("g:i A", $endTime) . " (Custom)";
                                      if ($appointmentTime >= $startTime && $appointmentTime < $endTime) {
                                          $qualifiedTherapists[$row['therapist_id']]['available'] = true; $qualifiedTherapists[$row['therapist_id']]['status'] = "Available"; // Override makes available
                                      } else {
                                          $qualifiedTherapists[$row['therapist_id']]['available'] = false; $qualifiedTherapists[$row['therapist_id']]['status'] = "Time Conflict (Custom Schedule)";
                                      }
                                  } catch (Exception $e) { error_log("Error processing custom schedule for {$row['therapist_id']}: " . $e->getMessage()); $qualifiedTherapists[$row['therapist_id']]['status'] = "Err"; $qualifiedTherapists[$row['therapist_id']]['available'] = false; }
                              } else { $qualifiedTherapists[$row['therapist_id']]['status'] = "Unavailable (Incomplete Custom Schedule)"; $qualifiedTherapists[$row['therapist_id']]['available'] = false; $qualifiedTherapists[$row['therapist_id']]['schedule'] = "Custom (Incomplete)"; }
                          }
                      }
                  }
                  $result_override->free(); // Free result set
             } else { error_log("Execute Error (Override): " . $stmt_override->error); }
        } else { error_log("Bind Param Error (Override): " . $stmt_override->error); }
        $stmt_override->close();
    } else { error_log("Prepare Error (Override): " . $connection->error); }
} else { error_log("Skipping Override check - no qualified IDs."); }


// 4️⃣ Check for Approved Appointments Conflict
// Create list of IDs STILL considered available after schedule/override checks
$potentiallyAvailableIds = [];
foreach ($qualifiedTherapists as $id => $data) {
    // Use the 'available' boolean flag set by previous steps
    if ($data['available'] === true) {
        $potentiallyAvailableIds[] = $id;
    }
}
error_log("Step 4 - Potentially Available IDs after Sched/Override: " . print_r($potentiallyAvailableIds, true)); // Log IDs being checked for conflict

if (!empty($potentiallyAvailableIds)) {
    $ids_placeholder_avail = implode(',', array_fill(0, count($potentiallyAvailableIds), '?'));
    $types_ids_avail = str_repeat('i', count($potentiallyAvailableIds));

    $query_conflict = "SELECT therapist_id FROM appointments
                       WHERE date = ? AND time = ? AND status = 'approved'
                         AND therapist_id IS NOT NULL -- Ensure therapist_id is not null
                         AND therapist_id IN ($ids_placeholder_avail)";
    $stmt_conflict = $connection->prepare($query_conflict);
    if ($stmt_conflict) {
        $types_combined = 'ss' . $types_ids_avail;
        $params = [$date, $time]; $params = array_merge($params, $potentiallyAvailableIds);
        if ($stmt_conflict->bind_param($types_combined, ...$params)) {
             if ($stmt_conflict->execute()) {
                  $result_conflict = $stmt_conflict->get_result();
                  $conflicting_ids = []; // Track conflicting IDs
                  while ($row = $result_conflict->fetch_assoc()) {
                      $tid = $row['therapist_id'];
                      $conflicting_ids[] = $tid; // Add to list
                      if (isset($qualifiedTherapists[$tid])) {
                          // Mark as unavailable due to conflict
                          $qualifiedTherapists[$tid]['available'] = false;
                          $qualifiedTherapists[$tid]['status'] = "Unavailable (Booked)";
                      }
                  }
                  $result_conflict->free(); // Free result
                  error_log("Step 4 - Conflicting Appointment IDs found: " . print_r($conflicting_ids, true)); // Log conflicting IDs
             } else { error_log("Execute Error (Conflict Check): " . $stmt_conflict->error); }
        } else { error_log("Bind Param Error (Conflict Check): " . $stmt_conflict->error); }
        $stmt_conflict->close();
    } else { error_log("Prepare Error (Conflict Check): " . $connection->error); }
} else { error_log("Step 4 - No potentially available therapists to check for conflicts."); }


// --- Log Status BEFORE Final Filtering ---
error_log("Step 4.5 - Status Before Final Filter: " . print_r($qualifiedTherapists, true));
// --- End Logging ---


// 5️⃣ *** FILTER THE LIST ***
$finalAvailableTherapists = [];
foreach ($qualifiedTherapists as $id => $data) {
    // Check the FINAL status string calculated through all steps
    if ($data['status'] === 'Available') {
        $finalAvailableTherapists[] = $data;
    }
}

// 6️⃣ Sort the FINAL available list by name
usort($finalAvailableTherapists, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// --- Log Final List ---
error_log("Step 7 - Final AVAILABLE Therapists Sent (" . count($finalAvailableTherapists) . "): " . print_r($finalAvailableTherapists, true));
// --- End Logging ---

// 7️⃣ Send JSON Response
echo json_encode(["status" => "success", "therapists" => $finalAvailableTherapists]); // Send only available ones
$connection->close();
?>
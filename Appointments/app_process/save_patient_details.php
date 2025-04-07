<?php
require_once "../../dbconfig.php"; // Adjust path as needed
session_start(); // Ensure session is started AT THE VERY TOP

// Enable error logging during development/debugging
// ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Set a writable path
// error_reporting(E_ALL);

// Check if connection is established
if (!isset($connection)) {
    // Log this error server-side if possible
    die("Database connection error.");
}

// --- Permission Check ---
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
     $_SESSION['error_message'] = "Unauthorized access.";
     // Redirect back to a safe page, maybe dashboard or the form itself
     header("Location: ../app_manage/update_patient_details.php");
     exit();
}
$therapistID = $_SESSION['account_ID']; // Get logged-in therapist ID

// Define allowed patient statuses
$allowedPatientStatuses = ['pending', 'enrolled', 'declined_enrollment', 'completed', 'cancelled'];

// --- Helper Function: Check if a time slot is valid for the therapist's AVAILABILITY ---
function isTimeSlotValidForTherapist($connection, $therapistId, $dateOrDay, $startTime, $endTime, $isMakeup = false) {
    $isValid = false;
    if (empty($startTime) || empty($endTime)) return false; // Need both times
    $checkTimeStart = strtotime($startTime);
    $checkTimeEnd = strtotime($endTime);

    // Basic check: end time must be after start time
    if ($checkTimeEnd === false || $checkTimeStart === false || $checkTimeEnd <= $checkTimeStart) {
        error_log("[isTimeSlotValidForTherapist] Validation Fail: Invalid start/end time format or end not after start.");
        return false;
    }

     $availability_blocks = []; // Array to hold ['start_time' => HH:MM:SS, 'end_time' => HH:MM:SS]

    // Determine relevant availability blocks (Check override first for makeup)
    if ($isMakeup) {
        $date_str = $dateOrDay;
         try { $date_obj = new DateTime($date_str); $day_of_week = $date_obj->format('l'); } catch (Exception $e) { return false; /* Invalid date */ }
         // 1. Check Override
         $override_query = "SELECT status, start_time, end_time FROM therapist_overrides WHERE therapist_id = ? AND date = ?";
         $stmt_o = $connection->prepare($override_query);
         if(!$stmt_o) { error_log("DB Prepare Error (Makeup Override): " . $connection->error); return false; }
         $stmt_o->bind_param("is", $therapistId, $date_str);
         $stmt_o->execute();
         $override = $stmt_o->get_result()->fetch_assoc();
         $stmt_o->close();
        if ($override) {
            if ($override['status'] === 'Unavailable') return false;
            if ($override['status'] === 'Custom' && $override['start_time'] && $override['end_time']) { $availability_blocks[] = ['start_time' => $override['start_time'], 'end_time' => $override['end_time']]; }
            elseif ($override['status'] === 'Custom') { return false; } // Custom but no times = unavailable
        } else { // 2. No Override, check Default
             $default_query = "SELECT start_time, end_time FROM therapist_default_availability WHERE therapist_id = ? AND day = ?";
             $stmt_d = $connection->prepare($default_query);
             if(!$stmt_d) { error_log("DB Prepare Error (Makeup Default): " . $connection->error); return false; }
             $stmt_d->bind_param("is", $therapistId, $day_of_week);
             $stmt_d->execute();
             $result_d = $stmt_d->get_result();
             while ($row = $result_d->fetch_assoc()) { $availability_blocks[] = $row; } // Fetches start_time, end_time
             $stmt_d->close();
        }
    } else { // Default schedule: Just check default availability
         $day_of_week = $dateOrDay;
          $default_query = "SELECT start_time, end_time FROM therapist_default_availability WHERE therapist_id = ? AND day = ?";
         $stmt_d = $connection->prepare($default_query);
         if(!$stmt_d) { error_log("DB Prepare Error (Default Avail Check): " . $connection->error); return false; }
         $stmt_d->bind_param("is", $therapistId, $day_of_week);
         if(!$stmt_d->execute()){ error_log("DB Execute Error (Default Avail Check): " . $stmt_d->error); $stmt_d->close(); return false; }
         $result_d = $stmt_d->get_result();
          if (!$result_d) { error_log("DB Get Result Error (Default Avail Check): " . $stmt_d->error); $stmt_d->close(); return false; }
         while ($row = $result_d->fetch_assoc()) { $availability_blocks[] = $row; } // Fetches start_time, end_time
         $stmt_d->close();
    }

    // Check if the submitted slot fits within *any* fetched block
     if (empty($availability_blocks)) { error_log("[isTimeSlotValidForTherapist] Validation Fail: No availability blocks found."); return false; }
     foreach ($availability_blocks as $block) {
         $availStart = strtotime($block['start_time']);
         $availEnd = strtotime($block['end_time']);
         if ($availStart === false || $availEnd === false) continue; // Skip invalid blocks
         if ($checkTimeStart >= $availStart && $checkTimeEnd <= $availEnd) {
             $isValid = true; // Found a valid block
             break;
         }
     }
    error_log("[isTimeSlotValidForTherapist] Check for $dateOrDay $startTime-$endTime. Valid: " . ($isValid?'Yes':'No'));
    return $isValid;
}

// --- Helper Function: Check for Default Schedule Conflicts with OTHER patients ---
function hasDefaultScheduleConflict($connection, $therapistId, $patientId, $day, $startTime, $scheduleIdToExclude = null) {
    $conflictQuery = "SELECT COUNT(*) as count
                      FROM patient_default_schedules
                      WHERE therapist_id = ?    -- Same therapist
                        AND day_of_week = ?     -- Same day
                        AND start_time = ?      -- Same start time (primary check for overlap)
                        AND patient_id != ?";   // Different patient

    $params = [$therapistId, $day, $startTime, $patientId];
    $types = "issi"; // Assuming therapist_id=int, day=string, start_time=string, patient_id=int

    if ($scheduleIdToExclude !== null && filter_var($scheduleIdToExclude, FILTER_VALIDATE_INT)) {
        $conflictQuery .= " AND id != ?"; // Exclude own ID if updating
        $params[] = (int)$scheduleIdToExclude;
        $types .= "i";
    }

    $stmt = $connection->prepare($conflictQuery);
    if (!$stmt) { error_log("Prepare failed (hasDefaultScheduleConflict): " . $connection->error); return true; } // Assume conflict on error
    if (!$stmt->bind_param($types, ...$params)) { error_log("Bind Param Failed (hasDefaultScheduleConflict): " . $stmt->error); $stmt->close(); return true; }
    if (!$stmt->execute()) { error_log("Execute Failed (hasDefaultScheduleConflict): " . $stmt->error); $stmt->close(); return true; }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $conflictExists = ($row && $row['count'] > 0);
    error_log("Conflict Check: Therapist=$therapistId, Patient=$patientId, Day=$day, Start=$startTime, ExcludeID=" . ($scheduleIdToExclude ?? 'N/A') . ", Conflict Found: " . ($conflictExists ? 'YES' : 'NO'));
    return $conflictExists;
}
// --- End Helper Function ---


// --- Form Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id'])) {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    if (!$patient_id) {
         $_SESSION['error_message'] = "Invalid Patient ID."; header("Location: ../app_manage/update_patient_details.php"); exit();
    }

    // Validate Status Input
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING); // Sanitize first
    if (!in_array($status, $allowedPatientStatuses)) {
        $_SESSION['error_message'] = "Invalid status value provided.";
        header("Location: ../app_manage/update_patient_details.php?patient_id=" . urlencode($patient_id)); exit();
    }

    $service_type = filter_input(INPUT_POST, 'service_type', FILTER_SANITIZE_STRING);
    // Optional: Validate service_type against enum values if needed

    error_log("--- Saving Patient Details ---");
    error_log("Patient ID: $patient_id, Status: $status, Service: $service_type, Therapist: $therapistID");

    $connection->begin_transaction();

    try {
        // --- Update Patient Information ---
        $updatePatientQuery = "UPDATE patients SET service_type=?, status=? WHERE patient_id=?";
        $stmtPat = $connection->prepare($updatePatientQuery);
        if (!$stmtPat) throw new Exception("Prepare failed (update patients): " . $connection->error);
        $stmtPat->bind_param("ssi", $service_type, $status, $patient_id);
        if (!$stmtPat->execute()) throw new Exception("Execute failed (update patients): " . $stmtPat->error);
        error_log("Patient update affected rows: " . $stmtPat->affected_rows);
        $stmtPat->close();


        // --- Process Schedules ONLY IF Status is 'enrolled' ---
        if ($status === 'enrolled') {
            error_log("Status is 'enrolled'. Processing schedules...");

            // --- Process Default Schedules ---
            $submitted_schedule_ids = []; // Holds IDs of ALL schedules processed in this request

            if (!empty($_POST['default_day'])) {
                error_log("Processing Submitted Default Schedules...");
                $insertDefault = "INSERT INTO patient_default_schedules (patient_id, therapist_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?)";
                $updateDefault = "UPDATE patient_default_schedules SET day_of_week = ?, start_time = ?, end_time = ?, therapist_id = ? WHERE id = ? AND patient_id = ?";

                $stmtInsert = $connection->prepare($insertDefault);
                if (!$stmtInsert) throw new Exception("Prepare failed (insert default): " . $connection->error);
                $stmtUpdate = $connection->prepare($updateDefault);
                if (!$stmtUpdate) throw new Exception("Prepare failed (update default): " . $connection->error);

                foreach ($_POST['default_day'] as $index => $day) {
                    // Validate and sanitize inputs for this row
                     if (empty($_POST['default_start_time'][$index]) || empty($_POST['default_end_time'][$index])) {
                         error_log("Skipping default schedule index $index due to missing start/end time.");
                         continue; // Skip if essential time data is missing
                     }
                    $start_time = filter_var($_POST['default_start_time'][$index], FILTER_SANITIZE_STRING); // Basic sanitize
                    $end_time = filter_var($_POST['default_end_time'][$index], FILTER_SANITIZE_STRING);   // Basic sanitize
                    $schedule_id = isset($_POST['default_schedule_id'][$index]) && !empty($_POST['default_schedule_id'][$index])
                                    ? filter_var($_POST['default_schedule_id'][$index], FILTER_VALIDATE_INT) : null;
                    error_log("Processing Default Item [$index]: Day=$day, Start=$start_time, End=$end_time, ID=$schedule_id");

                    // Validate Therapist Availability
                    if (!isTimeSlotValidForTherapist($connection, $therapistID, $day, $start_time, $end_time, false)) {
                        throw new Exception("Invalid time slot selected for default schedule on $day ($start_time - $end_time). It falls outside your available hours.");
                    }
                    // Validate Conflict with Other Patients
                    if (hasDefaultScheduleConflict($connection, $therapistID, $patient_id, $day, $start_time, $schedule_id)) {
                         throw new Exception("Schedule conflict detected for $day at $start_time. This time slot is already assigned to another patient by you.");
                    }

                    // Perform Update or Insert
                    if ($schedule_id) { // Update Existing
                        if(!$stmtUpdate->bind_param("sssiii", $day, $start_time, $end_time, $therapistID, $schedule_id, $patient_id)) { throw new Exception("Bind Param Failed (UPDATE default): " . $stmtUpdate->error); }
                        if (!$stmtUpdate->execute()) { throw new Exception("Execute failed (UPDATE default loop): " . $stmtUpdate->error); }
                        error_log("UPDATE Default Schedule ID: $schedule_id - Affected Rows: " . $stmtUpdate->affected_rows);
                        $submitted_schedule_ids[] = $schedule_id; // Add existing ID to keep list
                    } else { // Insert New
                         if(!$stmtInsert->bind_param("iisss", $patient_id, $therapistID, $day, $start_time, $end_time)) { throw new Exception("Bind Param Failed (INSERT default): " . $stmtInsert->error); }
                        if (!$stmtInsert->execute()) { throw new Exception("Execute failed (INSERT default loop): " . $stmtInsert->error); }
                        $newId = $stmtInsert->insert_id;
                        error_log("INSERT New Default Schedule - Success. New ID: " . $newId);
                        if ($newId > 0) {
                           $submitted_schedule_ids[] = $newId; // Add NEWLY INSERTED ID to keep list
                        } else { error_log("WARNING: Insert succeeded but insert_id was not positive ($newId)."); }
                    }
                } // End foreach loop
                $stmtInsert->close();
                $stmtUpdate->close();
                error_log("Finished processing submitted default schedules loop.");
            } else {
                 error_log("No default schedules submitted in POST data.");
            }

            // --- Delete default schedules NOT processed in this request ---
            $deleteDefaultQuery = "DELETE FROM patient_default_schedules WHERE patient_id = ?";
            $params = [$patient_id];
            $types = "i";

            // Filter out any invalid IDs just in case before building NOT IN clause
            $ids_to_keep = array_unique(array_filter($submitted_schedule_ids, 'is_int'));

            if (!empty($ids_to_keep)) {
                $placeholders = implode(',', array_fill(0, count($ids_to_keep), '?'));
                $deleteDefaultQuery .= " AND id NOT IN ($placeholders)";
                $params = array_merge($params, $ids_to_keep);
                $types .= str_repeat('i', count($ids_to_keep));
                error_log("Default Schedule IDs processed/submitted (To Keep): " . implode(', ', $ids_to_keep));
            } else {
                // No valid schedules were submitted/processed, delete ALL for this patient
                 error_log("No valid default schedule IDs processed/submitted, deleting ALL default schedules for patient $patient_id.");
            }

            error_log("Preparing DELETE query for unsubmitted default schedules: $deleteDefaultQuery");
            $stmtDelete = $connection->prepare($deleteDefaultQuery);
            if (!$stmtDelete) throw new Exception("Prepare failed (delete default): " . $connection->error);
            if (!$stmtDelete->bind_param($types, ...$params)) { throw new Exception("Bind Param Failed (DELETE default): " . $stmtDelete->error); }
            if (!$stmtDelete->execute()) { throw new Exception("Execute failed (delete default): " . $stmtDelete->error); }
            error_log("DELETE unsubmitted default schedules - Affected Rows: " . $stmtDelete->affected_rows);
            $stmtDelete->close();
            // --- End Default Delete Logic Modification ---


            // --- Process Makeup Schedules (Apply similar logic) ---
            $submitted_makeup_ids = []; // Holds ALL makeup IDs processed

            if (!empty($_POST['makeup_date'])) {
                 error_log("Processing Submitted Makeup Schedules...");
                $insertMakeup = "INSERT INTO patient_makeup_schedules (patient_id, date, start_time, end_time, notes) VALUES (?, ?, ?, ?, ?)";
                $updateMakeup = "UPDATE patient_makeup_schedules SET date = ?, start_time = ?, end_time = ?, notes = ? WHERE id = ? AND patient_id = ?";
                // Prepare statements... error check...
                 $stmtInsertMkp = $connection->prepare($insertMakeup); if (!$stmtInsertMkp) throw new Exception("Prepare failed (insert makeup): " . $connection->error);
                 $stmtUpdateMkp = $connection->prepare($updateMakeup); if (!$stmtUpdateMkp) throw new Exception("Prepare failed (update makeup): " . $connection->error);


                foreach ($_POST['makeup_date'] as $index => $date) {
                    // Validate and sanitize...
                     if (empty($_POST['makeup_start_time'][$index]) || empty($_POST['makeup_end_time'][$index])) {
                         error_log("Skipping makeup schedule index $index due to missing start/end time.");
                         continue;
                     }
                    $start_time = filter_var($_POST['makeup_start_time'][$index], FILTER_SANITIZE_STRING);
                    $end_time = filter_var($_POST['makeup_end_time'][$index], FILTER_SANITIZE_STRING);
                    $notes = filter_var($_POST['makeup_notes'][$index] ?? '', FILTER_SANITIZE_STRING);
                    $schedule_id = isset($_POST['makeup_schedule_id'][$index]) && !empty($_POST['makeup_schedule_id'][$index])
                                    ? filter_var($_POST['makeup_schedule_id'][$index], FILTER_VALIDATE_INT) : null;
                    error_log("Processing Makeup Item [$index]: Date=$date, Start=$start_time, End=$end_time, ID=$schedule_id");

                    // Validate Availability
                    if (!isTimeSlotValidForTherapist($connection, $therapistID, $date, $start_time, $end_time, true)) {
                        throw new Exception("Invalid time slot selected for makeup schedule on $date ($start_time - $end_time).");
                    }
                    // Note: Typically no DB conflict check needed for one-off makeup slots, but add if required.

                    if ($schedule_id) { // Update
                         if(!$stmtUpdateMkp->bind_param("ssssii", $date, $start_time, $end_time, $notes, $schedule_id, $patient_id)){ throw new Exception("Bind Param Failed (UPDATE makeup): " . $stmtUpdateMkp->error); }
                         if(!$stmtUpdateMkp->execute()){ throw new Exception("Execute Failed (UPDATE makeup): " . $stmtUpdateMkp->error); }
                         error_log("UPDATE Makeup Schedule ID: $schedule_id - Affected Rows: " . $stmtUpdateMkp->affected_rows);
                         $submitted_makeup_ids[] = $schedule_id;
                    } else { // Insert
                         if(!$stmtInsertMkp->bind_param("issss", $patient_id, $date, $start_time, $end_time, $notes)) { throw new Exception("Bind Param Failed (INSERT makeup): " . $stmtInsertMkp->error); }
                         if(!$stmtInsertMkp->execute()) { throw new Exception("Execute Failed (INSERT makeup): " . $stmtInsertMkp->error); }
                         $newId = $stmtInsertMkp->insert_id;
                         error_log("INSERT New Makeup Schedule - Success. New ID: " . $newId);
                         if($newId > 0) $submitted_makeup_ids[] = $newId;
                    }
                }
                 $stmtInsertMkp->close();
                 $stmtUpdateMkp->close();
                 error_log("Finished processing submitted makeup schedules loop.");
            } else {
                 error_log("No makeup schedules submitted in POST data.");
            }

            // --- Delete makeup schedules NOT processed ---
            $deleteMakeupQuery = "DELETE FROM patient_makeup_schedules WHERE patient_id = ?";
            $mkp_params = [$patient_id];
            $mkp_types = "i";
            $mkp_ids_to_keep = array_unique(array_filter($submitted_makeup_ids, 'is_int'));

            if (!empty($mkp_ids_to_keep)) {
                 $mkp_placeholders = implode(',', array_fill(0, count($mkp_ids_to_keep), '?'));
                 $deleteMakeupQuery .= " AND id NOT IN ($mkp_placeholders)";
                 $mkp_params = array_merge($mkp_params, $mkp_ids_to_keep);
                 $mkp_types .= str_repeat('i', count($mkp_ids_to_keep));
                 error_log("Makeup Schedule IDs processed/submitted (To Keep): " . implode(', ', $mkp_ids_to_keep));
            } else {
                 error_log("No valid makeup schedule IDs processed/submitted, deleting ALL makeup schedules for patient $patient_id.");
            }

            error_log("Preparing DELETE query for unsubmitted makeup schedules: $deleteMakeupQuery");
            $stmtDeleteMkp = $connection->prepare($deleteMakeupQuery);
             if (!$stmtDeleteMkp) { throw new Exception("Prepare failed (delete makeup): " . $connection->error); }
             if (!$stmtDeleteMkp->bind_param($mkp_types, ...$mkp_params)) { throw new Exception("Bind Param Failed (DELETE makeup): " . $stmtDeleteMkp->error); }
             if (!$stmtDeleteMkp->execute()) { throw new Exception("Execute failed (delete makeup): " . $stmtDeleteMkp->error); }
             error_log("DELETE unsubmitted makeup schedules - Affected Rows: " . $stmtDeleteMkp->affected_rows);
             $stmtDeleteMkp->close();
            // --- End Makeup Delete Logic ---

        } else { // Status is NOT 'enrolled'
            error_log("Status is NOT 'enrolled' ($status). Deleting ALL default and makeup schedules...");
            // Delete ALL Default Schedules
            $deleteAllDefault = "DELETE FROM patient_default_schedules WHERE patient_id = ?";
            $stmtDelAllDef = $connection->prepare($deleteAllDefault);
            if ($stmtDelAllDef) { $stmtDelAllDef->bind_param("i", $patient_id); $stmtDelAllDef->execute(); $stmtDelAllDef->close(); }
            else { error_log("Prepare failed (delete all default): " . $connection->error); /* Consider throwing exception */ }
            // Delete ALL Makeup Schedules
            $deleteAllMakeup = "DELETE FROM patient_makeup_schedules WHERE patient_id = ?";
            $stmtDelAllMkp = $connection->prepare($deleteAllMakeup);
            if ($stmtDelAllMkp) { $stmtDelAllMkp->bind_param("i", $patient_id); $stmtDelAllMkp->execute(); $stmtDelAllMkp->close(); }
            else { error_log("Prepare failed (delete all makeup): " . $connection->error); /* Consider throwing exception */ }
        }

        // If all operations were successful, commit the transaction
        $connection->commit();
        $_SESSION['success_message'] = "Patient details and schedules updated successfully!";

    } catch (Exception $e) {
        // An error occurred, roll back changes
        $connection->rollback();
        error_log("!!! EXCEPTION CAUGHT - Rolling back transaction !!!");
        error_log("Error saving patient details for ID $patient_id by Therapist $therapistID: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update patient details: " . $e->getMessage(); // Show specific error
    } finally {
        error_log("--- End Saving Patient Details ---");
        // Close connection if it's open
         if (isset($connection) && $connection instanceof mysqli && $connection->thread_id) {
            $connection->close();
         }
    }

    // Redirect back to the update page
    header("Location: ../app_manage/update_patient_details.php?patient_id=" . urlencode($patient_id));
    exit();

} else {
    // If accessed directly or without required POST data
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: ../app_manage/update_patient_details.php"); // Redirect to the main page or list
    exit();
}
?>
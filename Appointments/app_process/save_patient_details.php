<?php
// --- Start Output Buffering ---
ob_start();

// --- Includes and Setup ---
// Adjust path for Composer's autoload if you installed PHPMailer via Composer
require_once "../../Accounts/signupverify/vendor/autoload.php";
require_once "../../dbconfig.php"; // Adjust path if needed

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start(); // Start session AT THE VERY TOP

// Minimal error logging (adjust as needed for production/debugging)
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/php-error.log'); // IMPORTANT: Set a real, writable path
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); // Log errors, but maybe hide notices/warnings

// Check DB connection
if (!isset($connection)) {
    die("Database connection error.");
}

// --- Permission Check ---
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    $_SESSION['error_message'] = "Unauthorized access.";
    header("Location: ../app_manage/update_patient_details.php");
    if (ob_get_level() > 0)
        ob_end_flush();
    exit();
}
$therapistID = $_SESSION['account_ID'];

$allowedPatientStatuses = ['pending', 'enrolled', 'declined_enrollment', 'completed', 'cancelled'];

// --- Email Sending Function ---
function sendNotificationEmail($recipientEmail, $recipientName, $subject, $htmlBody)
{
    // --- SMTP Configuration ---
    $smtpHost = 'smtp.hostinger.com';
    $smtpUsername = 'no-reply@myliwanag.com';
    $smtpPassword = '[l/+1V/B4'; // *** IMPORTANT: Replace/Use Secure Config ***
    $smtpPort = 465;
    $smtpSecure = PHPMailer::ENCRYPTION_SMTPS;
    $fromEmail = 'no-reply@myliwanag.com';
    $fromName = "Little Wanderer's Therapy Center";
    // --- End Config ---
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $recipientName ?? '');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        $mail->send();
        error_log("[Email Sent] To: $recipientEmail");
        return true;
    } catch (Exception $e) {
        error_log("[Email FAILED] To: $recipientEmail Error: {$mail->ErrorInfo}");
        return false;
    }
}

// --- Helper Function: Check Therapist Availability (Minimal Logging) ---
function isTimeSlotValidForTherapist($connection, $therapistId, $dateOrDay, $startTime, $endTime, $isMakeup = false)
{
    $isValid = false;
    if (empty($startTime) || empty($endTime))
        return false;
    $checkTimeStart = strtotime($startTime);
    $checkTimeEnd = strtotime($endTime);
    if ($checkTimeEnd === false || $checkTimeStart === false || $checkTimeEnd <= $checkTimeStart)
        return false;
    $availability_blocks = [];
    if ($isMakeup) { /* ... Fetch overrides/defaults for makeup ... */
        $date_str = $dateOrDay;
        try {
            $date_obj = new DateTime($date_str);
            $day_of_week = $date_obj->format('l');
        } catch (Exception $e) {
            return false;
        }
        $stmt_o = $connection->prepare("SELECT status, start_time, end_time FROM therapist_overrides WHERE therapist_id = ? AND date = ?");
        if (!$stmt_o) {
            return false;
        }
        $stmt_o->bind_param("is", $therapistId, $date_str);
        $stmt_o->execute();
        $override = $stmt_o->get_result()->fetch_assoc();
        $stmt_o->close();
        if ($override) {
            if ($override['status'] === 'Unavailable')
                return false;
            if ($override['status'] === 'Custom' && $override['start_time'] && $override['end_time']) {
                $availability_blocks[] = ['start_time' => $override['start_time'], 'end_time' => $override['end_time']];
            } elseif ($override['status'] === 'Custom') {
                return false;
            }
        } else {
            $stmt_d = $connection->prepare("SELECT start_time, end_time FROM therapist_default_availability WHERE therapist_id = ? AND day = ?");
            if (!$stmt_d) {
                return false;
            }
            $stmt_d->bind_param("is", $therapistId, $day_of_week);
            $stmt_d->execute();
            $result_d = $stmt_d->get_result();
            while ($row = $result_d->fetch_assoc()) {
                $availability_blocks[] = $row;
            }
            $stmt_d->close();
        }
    } else { /* Default schedule */
        $day_of_week = $dateOrDay;
        $stmt_d = $connection->prepare("SELECT start_time, end_time FROM therapist_default_availability WHERE therapist_id = ? AND day = ?");
        if (!$stmt_d) {
            return false;
        }
        $stmt_d->bind_param("is", $therapistId, $day_of_week);
        if (!$stmt_d->execute()) {
            $stmt_d->close();
            return false;
        }
        $result_d = $stmt_d->get_result();
        if (!$result_d) {
            $stmt_d->close();
            return false;
        }
        while ($row = $result_d->fetch_assoc()) {
            $availability_blocks[] = $row;
        }
        $stmt_d->close();
    }
    if (empty($availability_blocks))
        return false;
    foreach ($availability_blocks as $block) {
        $availStart = strtotime($block['start_time']);
        $availEnd = strtotime($block['end_time']);
        if ($availStart === false || $availEnd === false)
            continue;
        if ($checkTimeStart >= $availStart && $checkTimeEnd <= $availEnd) {
            $isValid = true;
            break;
        }
    }
    return $isValid;
}

// --- Helper Function: Check Default Conflict with Other Patients (Keep as is) ---
function hasDefaultScheduleConflict($connection, $therapistId, $patientId, $day, $startTime, $scheduleIdToExclude = null)
{
    $conflictQuery = "SELECT COUNT(*) as count FROM patient_default_schedules WHERE therapist_id = ? AND day_of_week = ? AND start_time = ? AND patient_id != ?";
    $params = [$therapistId, $day, $startTime, $patientId];
    $types = "issi";
    if ($scheduleIdToExclude !== null && filter_var($scheduleIdToExclude, FILTER_VALIDATE_INT)) {
        $conflictQuery .= " AND id != ?";
        $params[] = (int) $scheduleIdToExclude;
        $types .= "i";
    }
    $stmt = $connection->prepare($conflictQuery);
    if (!$stmt) {
        error_log("Prepare Fail Conflict Check");
        return true;
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return true;
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return ($row && $row['count'] > 0);
}

// --- Form Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id'])) {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    if (!$patient_id) {
        $_SESSION['error_message'] = "Invalid Patient ID.";
        header("Location: ../app_manage/update_patient_details.php");
        ob_end_flush();
        exit();
    }
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    if (!in_array($status, $allowedPatientStatuses)) {
        $_SESSION['error_message'] = "Invalid status value.";
        header("Location: ../app_manage/update_patient_details.php?patient_id=" . urlencode($patient_id));
        ob_end_flush();
        exit();
    }
    $service_type = filter_input(INPUT_POST, 'service_type', FILTER_SANITIZE_STRING);

    error_log("--- Saving Patient Details (PHP Start) --- P:$patient_id, S:$status, T:$therapistID");

    $commitSuccess = false;
    $anyChangeDetected = false;
    $connection->begin_transaction();

    try {
        // 1. Update Patient Info
        $stmtPat = $connection->prepare("UPDATE patients SET service_type=?, status=? WHERE patient_id=?");
        if (!$stmtPat)
            throw new Exception("Prepare failed (patients)");
        $stmtPat->bind_param("ssi", $service_type, $status, $patient_id);
        if (!$stmtPat->execute())
            throw new Exception("Execute failed (patients)");
        if ($stmtPat->affected_rows > 0)
            $anyChangeDetected = true;
        $stmtPat->close();

        // 2. Process Schedules
        $defaultScheduleChangesMade = false;
        $makeupScheduleChangesMade = false;
        $submitted_schedule_ids = [];
        $submitted_makeup_ids = [];

        if ($status === 'enrolled') {
            // --- Process Default ---
            if (!empty($_POST['default_day'])) {
                $stmtInsert = $connection->prepare("INSERT INTO patient_default_schedules (patient_id, therapist_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
                $stmtUpdate = $connection->prepare("UPDATE patient_default_schedules SET day_of_week = ?, start_time = ?, end_time = ?, therapist_id = ? WHERE id = ? AND patient_id = ?");
                if (!$stmtInsert || !$stmtUpdate)
                    throw new Exception("Prepare failed (default)");
                foreach ($_POST['default_day'] as $index => $day) {
                    if (empty($_POST['default_start_time'][$index]) || empty($_POST['default_end_time'][$index]))
                        continue;
                    $start_time = $_POST['default_start_time'][$index];
                    $end_time = $_POST['default_end_time'][$index];
                    $schedule_id = isset($_POST['default_schedule_id'][$index]) ? filter_var($_POST['default_schedule_id'][$index], FILTER_VALIDATE_INT) : null;


  


                    if (!isTimeSlotValidForTherapist($connection, $therapistID, $day, $start_time, $end_time, false)) {
                        throw new Exception("Invalid slot: $day $start_time");
                    }
                    if (hasDefaultScheduleConflict($connection, $therapistID, $patient_id, $day, $start_time, $schedule_id)) {
                        throw new Exception("Conflict: $day $start_time");
                    }
                    if ($schedule_id) {
                        $stmtUpdate->bind_param("sssiii", $day, $start_time, $end_time, $therapistID, $schedule_id, $patient_id);
                        if (!$stmtUpdate->execute()) {
                            throw new Exception("Exec Update Fail (default)");
                        }
                        if ($stmtUpdate->affected_rows > 0)
                            $defaultScheduleChangesMade = true;
                        $submitted_schedule_ids[] = $schedule_id;
                    } else {
                        $stmtInsert->bind_param("iisss", $patient_id, $therapistID, $day, $start_time, $end_time);
                        if (!$stmtInsert->execute()) {
                            throw new Exception("Exec Insert Fail (default)");
                        }
                        $newId = $stmtInsert->insert_id;
                        if ($newId > 0)
                            $submitted_schedule_ids[] = $newId;
                        $defaultScheduleChangesMade = true;
                    }
                }
                $stmtInsert->close();
                $stmtUpdate->close();
            }
            // Delete unsubmitted defaults
            $deleteDefaultQuery = "DELETE FROM patient_default_schedules WHERE patient_id = ?";
            $params = [$patient_id];
            $types = "i";
            $ids_to_keep = array_unique(array_filter($submitted_schedule_ids, 'is_int'));
            if (!empty($ids_to_keep)) {
                $placeholders = implode(',', array_fill(0, count($ids_to_keep), '?'));
                $deleteDefaultQuery .= " AND id NOT IN ($placeholders)";
                $params = array_merge($params, $ids_to_keep);
                $types .= str_repeat('i', count($ids_to_keep));
            }
            $stmtDelete = $connection->prepare($deleteDefaultQuery);
            if (!$stmtDelete)
                throw new Exception("Prepare failed (delete default)");
            $stmtDelete->bind_param($types, ...$params);
            if (!$stmtDelete->execute()) {
                throw new Exception("Exec Failed (DELETE default)");
            }
            if ($stmtDelete->affected_rows > 0)
                $defaultScheduleChangesMade = true;
            $stmtDelete->close();

            // --- Process Makeup ---
            if (!empty($_POST['makeup_date'])) {
                $stmtInsertMkp = $connection->prepare("INSERT INTO patient_makeup_schedules (patient_id, date, start_time, end_time, notes) VALUES (?, ?, ?, ?, ?)");
                $stmtUpdateMkp = $connection->prepare("UPDATE patient_makeup_schedules SET date = ?, start_time = ?, end_time = ?, notes = ? WHERE id = ? AND patient_id = ?");
                if (!$stmtInsertMkp || !$stmtUpdateMkp)
                    throw new Exception("Prepare failed (makeup)");
                foreach ($_POST['makeup_date'] as $index => $date) {
                    if (empty($_POST['makeup_start_time'][$index]) || empty($_POST['makeup_end_time'][$index]))
                        continue;
                    $start_time = $_POST['makeup_start_time'][$index];
                    $end_time = $_POST['makeup_end_time'][$index];
                    $notes = filter_var($_POST['makeup_notes'][$index] ?? '', FILTER_SANITIZE_STRING);
                    $schedule_id = isset($_POST['makeup_schedule_id'][$index]) ? filter_var($_POST['makeup_schedule_id'][$index], FILTER_VALIDATE_INT) : null;
                    if (!isTimeSlotValidForTherapist($connection, $therapistID, $date, $start_time, $end_time, true)) {
                        throw new Exception("Invalid makeup slot: $date $start_time");
                    }
                    if ($schedule_id) {
                        $stmtUpdateMkp->bind_param("ssssii", $date, $start_time, $end_time, $notes, $schedule_id, $patient_id);
                        if (!$stmtUpdateMkp->execute()) {
                            throw new Exception("Exec Failed (UPDATE makeup)");
                        }
                        if ($stmtUpdateMkp->affected_rows > 0)
                            $makeupScheduleChangesMade = true;
                        $submitted_makeup_ids[] = $schedule_id;
                    } else {
                        $stmtInsertMkp->bind_param("issss", $patient_id, $date, $start_time, $end_time, $notes);
                        if (!$stmtInsertMkp->execute()) {
                            throw new Exception("Exec Failed (INSERT makeup)");
                        }
                        $newId = $stmtInsertMkp->insert_id;
                        if ($newId > 0)
                            $submitted_makeup_ids[] = $newId;
                        $makeupScheduleChangesMade = true;
                    }
                }
                $stmtInsertMkp->close();
                $stmtUpdateMkp->close();
            }
            // Delete unsubmitted makeups
            $deleteMakeupQuery = "DELETE FROM patient_makeup_schedules WHERE patient_id = ?";
            $mkp_params = [$patient_id];
            $mkp_types = "i";
            $mkp_ids_to_keep = array_unique(array_filter($submitted_makeup_ids, 'is_int'));
            if (!empty($mkp_ids_to_keep)) {
                $mkp_placeholders = implode(',', array_fill(0, count($mkp_ids_to_keep), '?'));
                $deleteMakeupQuery .= " AND id NOT IN ($mkp_placeholders)";
                $mkp_params = array_merge($mkp_params, $mkp_ids_to_keep);
                $mkp_types .= str_repeat('i', count($mkp_ids_to_keep));
            }
            $stmtDeleteMkp = $connection->prepare($deleteMakeupQuery);
            if (!$stmtDeleteMkp) {
                throw new Exception("Prepare failed (delete makeup)");
            }
            $stmtDeleteMkp->bind_param($mkp_types, ...$mkp_params);
            if (!$stmtDeleteMkp->execute()) {
                throw new Exception("Exec Failed (DELETE makeup)");
            }
            if ($stmtDeleteMkp->affected_rows > 0)
                $makeupScheduleChangesMade = true;
            $stmtDeleteMkp->close();

        } else { // Status NOT 'enrolled' -> Delete all schedules
            $deleteDefaultCount = 0;
            $deleteMakeupCount = 0;
            $stmtDelAllDef = $connection->prepare("DELETE FROM patient_default_schedules WHERE patient_id = ?");
            if ($stmtDelAllDef) {
                $stmtDelAllDef->bind_param("i", $patient_id);
                $stmtDelAllDef->execute();
                $deleteDefaultCount = $stmtDelAllDef->affected_rows;
                $stmtDelAllDef->close();
            }
            $stmtDelAllMkp = $connection->prepare("DELETE FROM patient_makeup_schedules WHERE patient_id = ?");
            if ($stmtDelAllMkp) {
                $stmtDelAllMkp->bind_param("i", $patient_id);
                $stmtDelAllMkp->execute();
                $deleteMakeupCount = $stmtDelAllMkp->affected_rows;
                $stmtDelAllMkp->close();
            }
            if ($deleteDefaultCount > 0)
                $defaultScheduleChangesMade = true;
            if ($deleteMakeupCount > 0)
                $makeupScheduleChangesMade = true;
        }

        $anyChangeDetected = ($patientUpdateAffected > 0 || $defaultScheduleChangesMade || $makeupScheduleChangesMade);

        $connection->commit();
        $commitSuccess = true;
        error_log("Transaction committed.");
        $_SESSION['success_message'] = "Patient details updated successfully!";

    } catch (Exception $e) {
        $connection->rollback();
        error_log("!!! EXCEPTION CAUGHT - Rolling back !!! Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Failed to update patient details: " . $e->getMessage();
        header("Location: ../app_manage/update_patient_details.php?patient_id=" . urlencode($patient_id));
        if (ob_get_level() > 0)
            ob_end_flush();
        exit();
    }

    // --- Send Response and Finish Request ---
    if ($commitSuccess) {
        session_write_close(); // Close session lock
        header("Location: ../app_manage/update_patient_details.php?patient_id=" . urlencode($patient_id)); // Send Redirect
        if (ob_get_level() > 0)
            ob_end_flush(); // Send output buffer
        flush(); // Force output buffer flush

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } // Disconnect client

        // --- Background Processing: Email Notifications ---
        if ($anyChangeDetected) {
            error_log("Starting background notifications...");
            // set_time_limit(120); // Optional

            $bg_connection = $connection;
            $close_bg_connection = false;
            if (!isset($bg_connection) || !($bg_connection instanceof mysqli) || !$bg_connection->thread_id) {
                require "../../dbconfig.php";
                $bg_connection = $connection;
                $close_bg_connection = true;
                error_log("Background task re-established DB connection.");
            }

            if (isset($bg_connection) && $bg_connection instanceof mysqli && $bg_connection->thread_id) {
                try {
                    // 1. Get Therapist Name
                    $therapistName = "Therapist";
                    $stmtTName = $bg_connection->prepare("SELECT account_FName, account_LName FROM users WHERE account_ID = ?");
                    if ($stmtTName) {
                        $stmtTName->bind_param("i", $therapistID);
                        $stmtTName->execute();
                        $tNameResult = $stmtTName->get_result()->fetch_assoc();
                        if ($tNameResult) {
                            $therapistName = $tNameResult['account_FName'] . ' ' . $tNameResult['account_LName'];
                        }
                        $stmtTName->close();
                    }
                    // 2. Get Patient/Client Info
                    $patientFName = "Patient";
                    $patientLName = "";
                    $clientEmail = null;
                    $clientName = null;
                    $stmtPInfo = $bg_connection->prepare("SELECT p.first_name, p.last_name, u.account_Email, u.account_FName as userFName, u.account_LName as userLName FROM patients p LEFT JOIN users u ON p.account_id = u.account_ID WHERE p.patient_id = ?");
                    if ($stmtPInfo) {
                        $stmtPInfo->bind_param("i", $patient_id);
                        $stmtPInfo->execute();
                        $patientResult = $stmtPInfo->get_result()->fetch_assoc();
                        if ($patientResult) {
                            $patientFName = $patientResult['first_name'];
                            $patientLName = $patientResult['last_name'];
                            $clientEmail = $patientResult['account_Email'];
                            $clientName = $patientResult['userFName'] . ' ' . $patientResult['userLName'];
                        }
                        $stmtPInfo->close();
                    }
                    // 3. Get Head Therapists
                    $htRecipients = [];
                    $stmtHT = $bg_connection->prepare("SELECT account_Email, account_FName, account_LName FROM users WHERE account_Type = 'head therapist' AND account_Status = 'active'");
                    if ($stmtHT) {
                        $stmtHT->execute();
                        $resultHT = $stmtHT->get_result();
                        while ($htRow = $resultHT->fetch_assoc()) {
                            $htRecipients[] = ['email' => $htRow['account_Email'], 'name' => $htRow['account_FName'] . ' ' . $htRow['account_LName']];
                        }
                        $stmtHT->close();
                    }
                    // 4. Get Final Schedules
                    $finalDefaultSchedules = [];
                    $finalMakeupSchedules = [];
                    $stmtFinalDS = $bg_connection->prepare("SELECT day_of_week, start_time, end_time FROM patient_default_schedules WHERE patient_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time");
                    if ($stmtFinalDS) {
                        $stmtFinalDS->bind_param("i", $patient_id);
                        $stmtFinalDS->execute();
                        $finalDefaultSchedules = $stmtFinalDS->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmtFinalDS->close();
                    }
                    $stmtFinalMS = $bg_connection->prepare("SELECT date, start_time, end_time, notes FROM patient_makeup_schedules WHERE patient_id = ? ORDER BY date, start_time");
                    if ($stmtFinalMS) {
                        $stmtFinalMS->bind_param("i", $patient_id);
                        $stmtFinalMS->execute();
                        $finalMakeupSchedules = $stmtFinalMS->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmtFinalMS->close();
                    }
                    // 5. Construct Email Body
                    $scheduleDetailsHTML = "<h4>Default Schedule:</h4>";
                    if (!empty($finalDefaultSchedules)) {
                        $scheduleDetailsHTML .= "<ul>";
                        foreach ($finalDefaultSchedules as $sched) {
                            $scheduleDetailsHTML .= "<li>" . htmlspecialchars($sched['day_of_week']) . ": " . date('g:i A', strtotime($sched['start_time'])) . " - " . date('g:i A', strtotime($sched['end_time'])) . "</li>";
                        }
                        $scheduleDetailsHTML .= "</ul>";
                    } else {
                        $scheduleDetailsHTML .= "<p>None.</p>";
                    }
                    $scheduleDetailsHTML .= "<h4>Makeup Schedule:</h4>";
                    if (!empty($finalMakeupSchedules)) {
                        $scheduleDetailsHTML .= "<ul>";
                        foreach ($finalMakeupSchedules as $sched) {
                            $scheduleDetailsHTML .= "<li>" . htmlspecialchars($sched['date']) . ": " . date('g:i A', strtotime($sched['start_time'])) . " - " . date('g:i A', strtotime($sched['end_time'])) . ($sched['notes'] ? " (" . htmlspecialchars($sched['notes']) . ")" : "") . "</li>";
                        }
                        $scheduleDetailsHTML .= "</ul>";
                    } else {
                        $scheduleDetailsHTML .= "<p>None.</p>";
                    }
                    $subject = "Update: Schedule for $patientFName $patientLName";
                    $body = "<p>Patient <strong>" . htmlspecialchars($patientFName) . " " . htmlspecialchars($patientLName) . "</strong> details have been updated by therapist " . htmlspecialchars($therapistName) . ".</p>";
                    $body .= "<hr><p><strong>Status:</strong> " . htmlspecialchars(ucfirst($status)) . "<br>";
                    $body .= "<strong>Service:</strong> " . htmlspecialchars($service_type) . "</p>";
                    $body .= $scheduleDetailsHTML;
                    $body .= "<hr><p><i>This is an automated notification. Please log in to the system for full details.</i></p>";
                    // 6. Send Emails
                    if ($clientEmail) {
                        sendNotificationEmail($clientEmail, $clientName, $subject, $body);
                    }
                    if (!empty($htRecipients)) {
                        foreach ($htRecipients as $ht) {
                            sendNotificationEmail($ht['email'], $ht['name'], $subject, $body);
                        }
                    }
                    error_log("Finished background notifications process.");
                } catch (Exception $emailEx) {
                    error_log("Error during background notifications: " . $emailEx->getMessage());
                } finally {
                    if ($close_bg_connection && isset($bg_connection) && $bg_connection instanceof mysqli && $bg_connection->thread_id) {
                        $bg_connection->close();
                        error_log("Background task DB connection closed.");
                    }
                }
            } else {
                error_log("Background task: DB connection unavailable.");
            }
        } else {
            error_log("Commit successful but NO changes detected. Skipping notifications.");
            if (isset($connection) && $connection instanceof mysqli && $connection->thread_id) {
                $connection->close();
            }
        }
        exit(); // End background script
    } // End if($commitSuccess)

} else { $_SESSION['error_message'] = "Invalid request."; header("Location: ../app_manage/update_patient_details.php"); if(ob_get_level() > 0) ob_end_flush(); exit(); }
if(ob_get_level() > 0) ob_end_flush();

?>
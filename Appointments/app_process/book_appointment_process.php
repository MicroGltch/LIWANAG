<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set default timezone (Optional but good practice if dealing with times across zones)
// date_default_timezone_set('UTC');

require_once "../../dbconfig.php";
require_once "../../Accounts/signupverify/vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();

// Function to Send Email Notification (No changes needed here based on column drop)
function send_email_notification($email, $client_name, $patient_name, $session_type, $appointment_date, $appointment_time, $status) {
     $mail = new PHPMailer(true);
     $isWaitlist = strpos(strtolower($status), 'waitlisted') !== false;
     $subject = $isWaitlist ? "Waitlist Request Confirmation - Therapy Center" : "Appointment Confirmation - Therapy Center";
     $statusText = ucfirst($status); // Make status look nicer

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@myliwanag.com';
        $mail->Password = '[l/+1V/B4';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
        $mail->addAddress($email, $client_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $emailBody = "
            <h3>" . ($isWaitlist ? "Waitlist Request Confirmation" : "Appointment Confirmation") . "</h3>
            <p>Dear <strong>$client_name</strong>,</p>";

        if ($isWaitlist) {
            // Display date if specific, otherwise mention 'any day'
            $waitlistDateText = $appointment_date ? "for the specific date <strong>$appointment_date</strong>" : "for <strong>any available day</strong>";
            $emailBody .= "<p>Your request to be added to the waitlist for <strong>$patient_name</strong> for the session type <strong>$session_type</strong> has been received.</p>";
            $emailBody .= "<p>You requested to be waitlisted $waitlistDateText.</p>";
            $emailBody .= "<p>We will notify you if a slot becomes available.</p>";
            $emailBody .= "<p><strong>Status:</strong> $statusText</p>";

        } else {
             $emailBody .= "<p>Your appointment for <strong>$patient_name</strong> has been successfully booked with the following details:</p>
            <ul>
                <li><strong>Session Type:</strong> $session_type</li>
                <li><strong>Date:</strong> $appointment_date</li>
                <li><strong>Time:</strong> $appointment_time</li>
                <li><strong>Status:</strong> $statusText</li>
            </ul>
            <p>We will notify you once your appointment is reviewed and confirmed by our team.</p>";
        }

        $emailBody .= "<p>Thank you for choosing our therapy center.</p>";

        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo); // Log email errors
        return false;
    }
}


// --- Main Process Logic ---

if (!isset($_SESSION['account_ID'])) {
    echo json_encode(["status" => "error", "message" => "You must be logged in."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    global $connection;
    $account_id = $_SESSION['account_ID'];
    $action = $_POST['action'] ?? 'book_appointment';

    // --- Shared Data Retrieval ---
    $patient_id = $_POST['patient_id'] ?? null;
    $appointment_type = $_POST['appointment_type'] ?? null; // e.g., "Initial Evaluation", "Playgroup"

    if (!$patient_id || !$appointment_type) {
         echo json_encode(["status" => "error", "message" => "Patient and Appointment Type are required."]);
         exit();
    }

    // Fetch Patient Name (Used in both flows)
    $patient_name = '';
    // ... (Patient name fetch code - unchanged) ...
    $patientQuery = "SELECT first_name, last_name FROM patients WHERE patient_id = ?";
    $stmt_p = $connection->prepare($patientQuery);
    if ($stmt_p) {
        $stmt_p->bind_param("i", $patient_id);
        $stmt_p->execute();
        $patientResult = $stmt_p->get_result();
        if ($patientResult && $patientResult->num_rows > 0) {
            $patientRow = $patientResult->fetch_assoc();
            $patient_name = $patientRow['first_name'] . ' ' . $patientRow['last_name'];
        }
        $stmt_p->close();
    }


    // Fetch Client Email & Name (Used in both flows)
    $client_email = null;
    $client_name = '';
    // ... (Client email fetch code - unchanged) ...
    $emailQuery = "SELECT account_Email, account_FName, account_LName FROM users WHERE account_ID = ?";
    $stmt_e = $connection->prepare($emailQuery);
    if ($stmt_e) {
        $stmt_e->bind_param("i", $account_id);
        $stmt_e->execute();
        $result_e = $stmt_e->get_result();
        if ($result_e && $result_e->num_rows > 0) {
            $user = $result_e->fetch_assoc();
            $client_email = $user['account_Email'];
            $client_name = trim($user['account_FName'] . " " . $user['account_LName']);
        }
        $stmt_e->close();
    }


    if (!$client_email) {
        echo json_encode(["status" => "error", "message" => "Error: Unable to retrieve client email."]);
        exit();
    }

    // --- Handle Referral Upload (Common for IE booking and IE waitlist) ---
     $uploadDir = "../../uploads/doctors_referrals/";
     $officialFileName = null;
     $proofFileName = null;
     $referralType = null;
     $referral_id = null; // Default: No referral


    // Process upload only if files are actually sent AND appointment type is Initial Evaluation
    if ($appointment_type === 'Initial Evaluation') {
        if (isset($_FILES['official_referral']) && $_FILES['official_referral']['error'] == UPLOAD_ERR_OK) {
            // ... (file processing logic for official_referral) ...
            $safeBaseName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($_FILES['official_referral']['name']));
            $officialFileName = time() . "_official_" . $safeBaseName;
            $officialFilePath = $uploadDir . $officialFileName;
            if (move_uploaded_file($_FILES['official_referral']['tmp_name'], $officialFilePath)) {
                $referralType = 'official';
            } else {
                 error_log("Failed to move official referral file: " . $_FILES['official_referral']['error']);
                 $officialFileName = null; // Failed upload
            }

        } elseif (isset($_FILES['proof_of_booking']) && $_FILES['proof_of_booking']['error'] == UPLOAD_ERR_OK) {
             // ... (file processing logic for proof_of_booking) ...
             $safeBaseName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($_FILES['proof_of_booking']['name']));
             $proofFileName = time() . "_proof_" . $safeBaseName;
             $proofFilePath = $uploadDir . $proofFileName;
             if (move_uploaded_file($_FILES['proof_of_booking']['tmp_name'], $proofFilePath)) {
                $referralType = 'proof_of_booking';
            } else {
                 error_log("Failed to move proof of booking file: " . $_FILES['proof_of_booking']['error']);
                 $proofFileName = null; // Failed upload
            }
        }
        // It's okay if neither file was uploaded IF the action isn't booking/waitlisting IE yet,
        // but we will check $referral_id later before inserting.

        // Insert into `doctor_referrals` table if a referral was successfully uploaded
        if ($officialFileName || $proofFileName) {
            $insertReferralSQL = "INSERT INTO doctor_referrals (patient_id, official_referral_file, proof_of_booking_referral_file, referral_type)
                          VALUES (?, ?, ?, ?)";
            $stmt_ref = $connection->prepare($insertReferralSQL);
            if ($stmt_ref) {
                $stmt_ref->bind_param("isss", $patient_id, $officialFileName, $proofFileName, $referralType);
                if ($stmt_ref->execute()) {
                    $referral_id = $stmt_ref->insert_id; // Get the new referral ID
                } else {
                     error_log("Failed to insert referral: " . $stmt_ref->error);
                     // Keep referral_id as null
                }
                $stmt_ref->close();
            } else {
                 error_log("Failed to prepare referral statement: " . $connection->error);
            }
        }
    } // End check if Initial Evaluation for upload

    // --- Check existing appointment rules (should apply to both booking and waitlist attempts) ---
     // Use prepared statements to prevent SQL injection
     $check_existing = "SELECT session_type, status FROM appointments WHERE patient_id = ? AND status IN ('pending', 'approved', 'waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day', 'rebooking')";
     $stmt_check = $connection->prepare($check_existing);
     if ($stmt_check) {
         $stmt_check->bind_param("i", $patient_id);
         $stmt_check->execute();
         $result_check = $stmt_check->get_result();

         while ($row_check = $result_check->fetch_assoc()) {
             // Rule 1: Prevent duplicate Pending/Approved/Waitlisted session type
             if ($row_check['session_type'] === $appointment_type && in_array($row_check['status'], ['pending', 'approved', 'waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day'])) {
                 echo json_encode(["status" => "error", "message" => "This patient already has a {$row_check['status']} request or appointment for this session type."]);
                 $stmt_check->close(); // Close statement before exiting
                 exit();
             }
              // Rule 2: If 'rebooking' status exists for *any* session, prevent new 'Initial Evaluation'
              // Case sensitive comparison with DB value ('Initial Evaluation')
              if ($row_check['status'] === "rebooking" && $appointment_type === "Initial Evaluation") {
                  echo json_encode(["status" => "error", "message" => "This patient is in Rebooking status. An Initial Evaluation is not required."]);
                  $stmt_check->close(); // Close statement before exiting
                 exit();
              }
         }
         $stmt_check->close();
     } else {
         error_log("Failed to prepare check existing appointment statement: " . $connection->error);
         // Decide if this is fatal or if you should proceed with caution
         // echo json_encode(["status" => "error", "message" => "Database error checking existing appointments."]);
         // exit();
     }


    // =============================================
    // --- BRANCH LOGIC: BOOKING vs WAITLIST ---
    // =============================================

    // Prepare variables needed for INSERT
    $therapist_id_null = null;
    $rebooked_by_null = null;
    $appointment_date_val = null;
    $appointment_time_val = null;
    $status_val = null;
    $session_type_val = $appointment_type; // Set session type from input
    $validation_notes_null = null;
    $edit_count_default = 0;
    $auto_cancel_deadline_null = null;
    $referral_id_val = $referral_id; // Use the value obtained from upload/insert (could be null)
    $pg_session_id_val = null;
    $account_id_val = $account_id;
    $patient_id_val = $patient_id;


    if ($action === 'request_waitlist') {
        // --- WAITLIST REQUEST PROCESSING ---

        // Waitlisting is only for Initial Evaluation
        if ($appointment_type !== 'Initial Evaluation') {
            echo json_encode(["status" => "error", "message" => "Waitlisting is only available for Initial Evaluation appointments."]);
            exit();
        }

        // Check if referral was successfully processed for this IE request
        if (!$referral_id_val) {
             echo json_encode(["status" => "error", "message" => "A doctor's referral or proof of booking is required to join the waitlist for Initial Evaluation."]);
             exit();
        }

        $waitlist_type = $_POST['waitlist_type'] ?? null; // 'specific_date' or 'any_day'
        $specific_date = ($waitlist_type === 'specific_date') ? ($_POST['specific_date'] ?? null) : null;

        if (!$waitlist_type || ($waitlist_type === 'specific_date' && !$specific_date)) {
             echo json_encode(["status" => "error", "message" => "Invalid waitlist request data."]);
             exit();
        }

        // Set status and date/time for waitlist insert
        $status_val = ($waitlist_type === 'specific_date') ? 'Waitlisted - Specific Date' : 'Waitlisted - Any Day';
        $appointment_date_val = ($status_val === 'Waitlisted - Specific Date') ? $specific_date : null; // Date or NULL
        $appointment_time_val = null; // Time is always NULL for waitlist
        $pg_session_id_val = null;    // PG Session is always NULL for waitlist

        // Session type is already set to 'Initial Evaluation' in $session_type_val

    } else { // --- NORMAL APPOINTMENT BOOKING ('book_appointment' action) ---

        $pg_session_id_val = ($appointment_type === 'Playgroup') ? ($_POST['pg_session_id'] ?? null) : null;

        if ($appointment_type === "Playgroup") {
            // Playgroup: Get date/time from session, referral not needed
            $referral_id_val = null; // Ensure referral ID is null for Playgroup

            if (empty($pg_session_id_val)) {
                echo json_encode(["status" => "error", "message" => "No Playgroup session selected."]);
                exit();
            }
            // Fetch date/time from the selected session
            $pgQuery = "SELECT date, time FROM playgroup_sessions WHERE pg_session_id = ?";
            $stmt_pg = $connection->prepare($pgQuery);
            if ($stmt_pg) {
                $stmt_pg->bind_param("i", $pg_session_id_val);
                $stmt_pg->execute();
                $pgResult = $stmt_pg->get_result();
                if ($pgResult->num_rows > 0) {
                    $pgData = $pgResult->fetch_assoc();
                    $appointment_date_val = $pgData['date']; // Use date from session
                    $appointment_time_val = $pgData['time']; // Use time from session
                } else {
                    echo json_encode(["status" => "error", "message" => "Selected Playgroup session not found."]);
                    exit();
                }
                $stmt_pg->close();
            } else {
                 echo json_encode(["status" => "error", "message" => "Database error fetching Playgroup session."]);
                 exit();
            }

            // Check Playgroup Capacity (Re-check on submission as a safeguard)
            // ... (Playgroup capacity check logic - uses $appointment_date_val and $appointment_time_val) ...
            $check_capacity = "SELECT COUNT(*) as count FROM appointments WHERE date = ? AND time = ? AND session_type = 'Playgroup' AND status IN ('Pending', 'Approved')";
            $stmt_cap = $connection->prepare($check_capacity);
             if ($stmt_cap) {
                $stmt_cap->bind_param("ss", $appointment_date_val, $appointment_time_val);
                $stmt_cap->execute();
                $capacity_result = $stmt_cap->get_result();
                $capacity_row = $capacity_result->fetch_assoc();
                $stmt_cap->close(); // Close capacity check statement

                // Fetch max capacity for this specific session
                $maxCapStmt = $connection->prepare("SELECT max_capacity FROM playgroup_sessions WHERE pg_session_id = ?");
                if ($maxCapStmt) {
                     $maxCapStmt->bind_param("i", $pg_session_id_val);
                     $maxCapStmt->execute();
                     $maxCapResult = $maxCapStmt->get_result();
                     $maxCapacityRow = $maxCapResult->fetch_assoc();
                     $maxCapacity = $maxCapacityRow ? $maxCapacityRow['max_capacity'] : 6; // Default if not found
                     $maxCapStmt->close();

                     if ($capacity_row['count'] >= $maxCapacity) {
                        echo json_encode(["status" => "error", "message" => "This playgroup session just filled up! Please choose another session."]);
                        exit();
                     }
                } else {
                    error_log("Failed to prepare max capacity statement: " . $connection->error);
                    // Handle error - maybe proceed with default capacity check or fail?
                }
            } else {
                 error_log("Failed to prepare capacity check statement: " . $connection->error);
                 // Handle error
            }

        } else { // --- Initial Evaluation Booking ---
            // IE needs date/time from form and a referral
             $appointment_date_val = $_POST['appointment_date'] ?? null;
             $appointment_time_val = $_POST['appointment_time'] ?? null; // Expecting 'HH:MM:SS' format now
             $pg_session_id_val = null; // Ensure PG session is null for IE

            if (empty($appointment_date_val) || empty($appointment_time_val)) {
                echo json_encode(["status" => "error", "message" => "Date and Time are required for this appointment type."]);
                exit();
            }

            // Ensure IE has referral processed
            if ($appointment_type === "Initial Evaluation" && !$referral_id_val) {
                echo json_encode(["status" => "error", "message" => "A doctor's referral or proof of booking upload is required for Initial Evaluation."]);
                exit();
            }

            // Validate IE Date Range
            // ... (Date range validation logic - uses $appointment_date_val) ...
            $settingsQueryV = "SELECT max_days_advance, min_days_advance FROM settings LIMIT 1";
            $settingsResultV = $connection->query($settingsQueryV);
            $settingsV = $settingsResultV->fetch_assoc();
            $minDaysAdvance = $settingsV["min_days_advance"] ?? 3;
            $maxDaysAdvance = $settingsV["max_days_advance"] ?? 30;

             try {
                // Use current server time zone for comparison base unless explicitly set otherwise
                $minDate = new DateTime();
                $minDate->setTime(0,0,0)->add(new DateInterval("P{$minDaysAdvance}D"));
                $maxDate = new DateTime();
                $maxDate->setTime(0,0,0)->add(new DateInterval("P{$maxDaysAdvance}D"));
                $selectedDate = new DateTime($appointment_date_val);
                $selectedDate->setTime(0,0,0);

                if ($selectedDate < $minDate) {
                    echo json_encode(["status" => "error", "message" => "Initial Evaluation must be booked at least {$minDaysAdvance} days in advance."]);
                    exit();
                }
                if ($selectedDate > $maxDate) {
                    echo json_encode(["status" => "error", "message" => "Initial Evaluation can only be booked up to {$maxDaysAdvance} days in advance."]);
                    exit();
                }
             } catch (Exception $e) {
                  echo json_encode(["status" => "error", "message" => "Invalid date format provided."]);
                 exit();
             }
        }

        // Set status for normal booking
        $status_val = "Pending";

    } // End if/else for $action

    // --- Perform the Database Insert ---

    // REMOVED initial_evaluation from column list and VALUES
    $insertSQL = "INSERT INTO appointments (
        account_id, therapist_id, rebooked_by, patient_id, date, time,
        status, session_type, validation_notes, edit_count,
        auto_cancel_deadline, referral_id, pg_session_id, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"; // 13 placeholders

    $stmt_insert = $connection->prepare($insertSQL);

    if ($stmt_insert === false) {
        error_log("Insert Prepare Error: " . $connection->error . " | SQL: " . $insertSQL);
        echo json_encode(["status" => "error", "message" => "Database error preparing request."]);
        exit();
    }

    // REMOVED the type for initial_evaluation (was 7th 'i')
    // Example old: "iiiiississisii" -> Example new: "iiiiis ssisii"
    $bind_types = "iiiisssssisii"; // Double check against your final 13 column types! date=s, time=s, status=s, session_type=s, validation_notes=s, auto_cancel_deadline=s

    $stmt_insert->bind_param(
        $bind_types,
        $account_id_val,            // 1 (i)
        $therapist_id_null,         // 2 (i)
        $rebooked_by_null,          // 3 (i)
        $patient_id_val,            // 4 (i)
        $appointment_date_val,      // 5 (s) - NULL OK for Waitlist Any Day
        $appointment_time_val,      // 6 (s) - NULL OK for Waitlist
        $status_val,                // 7 (s) - 'Pending' or 'Waitlisted - ...'
        $session_type_val,          // 8 (s)
        $validation_notes_null,     // 9 (s)
        $edit_count_default,        // 10 (i)
        $auto_cancel_deadline_null, // 11 (s)
        $referral_id_val,           // 12 (i) - NULL OK
        $pg_session_id_val          // 13 (i) - NULL OK
    );

    if ($stmt_insert->execute()) {
        // Send Confirmation Email
        // Pass the correct date/time values used for insert/logic
        if (send_email_notification($client_email, $client_name, $patient_name, $session_type_val, $appointment_date_val, $appointment_time_val, $status_val)) {
             echo json_encode([
                "status" => "success",
                "message" => ($action === 'request_waitlist' ? "Successfully added to the waitlist." : "Appointment requested successfully.") . " A confirmation email has been sent.",
                "swal" => [ "title" => ($action === 'request_waitlist' ? "Waitlist Joined!" : "Request Submitted!"), "text" => ($action === 'request_waitlist' ? "Added to waitlist. We'll notify you." : "Appointment requested. Wait for approval."), "icon" => "success" ],
                "reload" => true
            ]);
        } else {
             echo json_encode([ // Still success saving data, email failed
                "status" => "success",
                "message" => ($action === 'request_waitlist' ? "Successfully added to the waitlist" : "Appointment requested") . ", but the confirmation email failed.",
                "swal" => [ "title" => ($action === 'request_waitlist' ? "Waitlist Joined!" : "Request Submitted!"), "text" => ($action === 'request_waitlist' ? "Added to waitlist (email failed)." : "Appointment requested (email failed)."), "icon" => "warning" ],
                 "reload" => true
            ]);
        }

    } else {
         // Log detailed error
         error_log("Insert Execute Error: " . $stmt_insert->error . " | SQL: " . $insertSQL . " | Types: " . $bind_types . " | Data: " . json_encode(array_slice(func_get_args(), 1))); // Log relevant data passed
         echo json_encode([
            "status" => "error",
            "message" => "Error saving your request.",
             "swal" => [ "title" => "Error!", "text" => "Could not save the request.", "icon" => "error" ]
        ]);
    }
    $stmt_insert->close();

    $connection->close();
} else {
      echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>
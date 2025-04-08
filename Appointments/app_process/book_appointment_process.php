<?php
// book_appointment_process.php
// Handles submission from Book_appointment_form.php for both
// normal booking requests and waitlist requests.

ini_set('display_errors', 1); // Set to 0 in production
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// date_default_timezone_set('Asia/Manila'); // Set your timezone if needed

require_once "../../dbconfig.php"; // Adjust path
require_once "../../Accounts/signupverify/vendor/autoload.php"; // Adjust path for PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// --- Email Notification Function (Mostly Unchanged) ---
function send_email_notification($email, $client_name, $patient_name, $session_type, $appointment_date, $appointment_time, $status) {
     $mail = new PHPMailer(true);
     $isWaitlist = stripos($status, 'waitlisted') !== false;
     $subject = $isWaitlist ? "Waitlist Request Confirmation - Little Wanderer's" : "Appointment Request Received - Little Wanderer's";
     $statusText = str_replace('-', ' ', $status); // Improve display
     $statusText = ucwords($statusText); // Capitalize words

     // Format time for email if available
     $displayTime = 'N/A';
     if ($appointment_time) {
          try {
              $timeObj = new DateTime($appointment_time);
              $displayTime = $timeObj->format('g:i A'); // e.g., 9:00 AM
          } catch (Exception $e) { $displayTime = $appointment_time; /* fallback */ }
     }

    try {
        // --- SMTP Configuration (Replace with your actual credentials) ---
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com'; // Your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@myliwanag.com'; // Your SMTP username
        $mail->Password = '[l/+1V/B4'; // Your SMTP password - Use environment variables or config file in production!
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Or ENCRYPTION_STARTTLS
        $mail->Port = 465; // 465 for SMTPS, 587 for STARTTLS

        // --- Email Headers ---
        $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center"); // Your sender email and name
        $mail->addAddress($email, $client_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // --- Email Body ---
        $emailBody = "
            <p>Dear <strong>" . htmlspecialchars($client_name) . "</strong>,</p>";

        if ($isWaitlist) {
            $waitlistDateText = $appointment_date ? "for the specific date <strong>" . htmlspecialchars($appointment_date) . "</strong>" : "for <strong>any available day</strong>";
            $emailBody .= "<p>Your request to be added to the waitlist for <strong>" . htmlspecialchars($patient_name) . "</strong> for the session type <strong>" . htmlspecialchars($session_type) . "</strong> has been received.</p>";
            $emailBody .= "<p>You requested to be waitlisted $waitlistDateText.</p>";
            $emailBody .= "<p>We will notify you if a suitable slot becomes available.</p>";
            $emailBody .= "<p><strong>Request Status:</strong> " . htmlspecialchars($statusText) . "</p>";
        } else {
             $emailBody .= "<p>Your appointment request for <strong>" . htmlspecialchars($patient_name) . "</strong> has been received with the following details:</p>
            <ul>
                <li><strong>Session Type:</strong> " . htmlspecialchars($session_type) . "</li>
                <li><strong>Date:</strong> " . htmlspecialchars($appointment_date) . "</li>
                <li><strong>Time:</strong> " . htmlspecialchars($displayTime) . "</li>
                <li><strong>Status:</strong> " . htmlspecialchars($statusText) . " (Awaiting Approval)</li>
            </ul>
            <p>Our team will review your request. You will receive another email once the appointment is approved or if further information is required.</p>";
            // Add cancellation policy link or info if applicable
             $emailBody .= "<p>Please review our cancellation policy: [Link]</p>";
        }

        $emailBody .= "<p>Thank you for choosing Little Wanderer's Therapy Center.</p>";
        // Add contact information
        $emailBody .= "<p>--<br>Little Wanderer's Therapy Center<br>[Your Phone Number]<br>[Your Website/Address]</p>";

        $mail->Body = $emailBody;
        $mail->send();
        error_log("Email sent successfully to $email for status $status");
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for $email: " . $mail->ErrorInfo);
        return false; // Email failed
    }
}


// --- Main Process Logic ---

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
     // Redirect or show error for invalid method
     header('HTTP/1.1 405 Method Not Allowed');
     echo json_encode(["status" => "error", "message" => "Invalid request method."]);
     exit();
}

// Check Login Status
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== 'client') {
    echo json_encode(["status" => "error", "message" => "Authentication required.", "action" => "redirect", "url" => "../Accounts/loginpage.php"]); // Adjust path
    exit();
}

global $connection; // Use the connection from dbconfig.php
$account_id = $_SESSION['account_ID'];
$action = $_POST['action'] ?? 'book_appointment'; // Default action is booking

// Start Transaction
$connection->begin_transaction();

try {
    // --- Shared Data Retrieval & Validation ---
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $appointment_type = $_POST['appointment_type'] ?? null; // e.g., "IE-OT", "IE-BT", "Playgroup"
    $valid_types = ['IE-OT', 'IE-BT', 'Playgroup'];

    if (!$patient_id || !$appointment_type || !in_array($appointment_type, $valid_types)) {
         throw new Exception("Patient and a valid Appointment Type are required.");
    }

    // Fetch Patient Name (Used in email)
    $patient_name = '';
    $stmt_p = $connection->prepare("SELECT first_name, last_name FROM patients WHERE patient_id = ? AND account_id = ?");
    if ($stmt_p) {
        $stmt_p->bind_param("ii", $patient_id, $account_id); // Ensure patient belongs to this client
        $stmt_p->execute();
        $patientResult = $stmt_p->get_result();
        if ($patientRow = $patientResult->fetch_assoc()) {
            $patient_name = trim($patientRow['first_name'] . ' ' . $patientRow['last_name']);
        } else {
             throw new Exception("Invalid patient selected or patient does not belong to this account.");
        }
        $stmt_p->close();
    } else { throw new Exception("Database error fetching patient details."); }


    // Fetch Client Email & Name (Used in email)
    $client_email = null;
    $client_name = '';
    $stmt_e = $connection->prepare("SELECT account_Email, account_FName, account_LName FROM users WHERE account_ID = ?");
     if ($stmt_e) {
        $stmt_e->bind_param("i", $account_id);
        $stmt_e->execute();
        $result_e = $stmt_e->get_result();
        if ($user = $result_e->fetch_assoc()) {
            $client_email = $user['account_Email'];
            $client_name = trim($user['account_FName'] . " " . $user['account_LName']);
        } else {
             throw new Exception("Could not retrieve client details.");
        }
        $stmt_e->close();
    } else { throw new Exception("Database error fetching client details."); }


    // --- Handle Referral Upload (Common for IE booking and IE waitlist) ---
    $referral_id = null; // Default: No referral record created yet
    // Process upload ONLY if type is IE-OT or IE-BT AND a file was submitted
    if (($appointment_type === 'IE-OT' || $appointment_type === 'IE-BT') && isset($_FILES['referral_file']) && $_FILES['referral_file']['error'] == UPLOAD_ERR_OK) {

        $uploadDir = "../../uploads/doctors_referrals/"; // Adjust path
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true); // Create if not exists

        $fileInfo = $_FILES['referral_file'];
        $fileName = $fileInfo['name'];
        $fileTmpName = $fileInfo['tmp_name'];
        $fileSize = $fileInfo['size'];
        $fileError = $fileInfo['error'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($fileExt, $allowedExt)) {
            throw new Exception("Invalid file type. Only JPG, PNG, PDF allowed.");
        }
        if ($fileSize > 5000000) { // 5MB limit example
             throw new Exception("File size exceeds the limit (5MB).");
        }

        // Determine referral type based on hidden input from form
        $referralUploadType = $_POST['referral_upload_type'] ?? null; // 'official' or 'proof_of_booking'
         if ($referralUploadType !== 'official' && $referralUploadType !== 'proof_of_booking') {
             throw new Exception("Invalid referral document type specified.");
         }

        // Create unique filename
        $safeBaseName = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", pathinfo($fileName, PATHINFO_FILENAME));
        $newFileName = time() . "_" . $referralUploadType . "_" . $safeBaseName . "." . $fileExt;
        $destination = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmpName, $destination)) {
            throw new Exception("Failed to upload the referral document. Error code: " . $fileError);
        }

        // File uploaded, now insert into `doctor_referrals` table
        $officialFileCol = ($referralUploadType === 'official') ? $newFileName : null;
        $proofFileCol = ($referralUploadType === 'proof_of_booking') ? $newFileName : null;

        $insertReferralSQL = "INSERT INTO doctor_referrals (patient_id, referral_type, official_referral_file, proof_of_booking_referral_file, created_at, updated_at)
                              VALUES (?, ?, ?, ?, NOW(), NOW())";
        $stmt_ref = $connection->prepare($insertReferralSQL);
        if ($stmt_ref) {
            $stmt_ref->bind_param("isss", $patient_id, $referralUploadType, $officialFileCol, $proofFileCol);
            if ($stmt_ref->execute()) {
                $referral_id = $stmt_ref->insert_id; // Get the ID of the new referral record
                error_log("Referral record created with ID: $referral_id for patient $patient_id");
            } else {
                 error_log("Failed to insert referral record: " . $stmt_ref->error);
                 // Optionally delete the uploaded file if DB insert fails?
                 unlink($destination); // Attempt cleanup
                 throw new Exception("Failed to save referral information.");
            }
            $stmt_ref->close();
        } else {
             error_log("Failed to prepare referral insert statement: " . $connection->error);
             unlink($destination); // Attempt cleanup
             throw new Exception("Database error saving referral.");
        }
    } // End referral upload processing


    // --- Check Referral Requirement for IE Types (Before proceeding further) ---
    if (($appointment_type === 'IE-OT' || $appointment_type === 'IE-BT') && $referral_id === null) {
        // If it's an IE type but no referral record was created (either no file uploaded or upload failed)
         throw new Exception("A doctor's referral or proof of booking document is required for Initial Evaluation appointments.");
    }


    // --- Check for Existing Conflicting Appointments (Server-side safety check) ---
    $conflicting_statuses = ['pending', 'approved', 'waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day', 'rebooking']; // Statuses to check against
    $status_placeholders = implode(',', array_fill(0, count($conflicting_statuses), '?'));

    $check_existing_sql = "SELECT session_type, status FROM appointments
                           WHERE patient_id = ? AND status IN ($status_placeholders)";
    $stmt_check = $connection->prepare($check_existing_sql);
    if ($stmt_check) {
         $types = 'i' . str_repeat('s', count($conflicting_statuses));
         $params = array_merge([$patient_id], $conflicting_statuses);
         $stmt_check->bind_param($types, ...$params);
         $stmt_check->execute();
         $result_check = $stmt_check->get_result();

         while ($row_check = $result_check->fetch_assoc()) {
             // Rule 1: Prevent duplicate Pending/Approved/Waitlisted for the *exact same* session type
             if ($row_check['session_type'] === $appointment_type && in_array($row_check['status'], ['pending', 'approved', 'waitlisted', 'Waitlisted - Specific Date', 'Waitlisted - Any Day'])) {
                 throw new Exception("This patient already has a {$row_check['status']} request or appointment for {$appointment_type}.");
             }
             // Rule 2: Prevent new IE-OT or IE-BT if patient is in 'rebooking' status
             if ($row_check['status'] === "rebooking" && ($appointment_type === "IE-OT" || $appointment_type === "IE-BT")) {
                 throw new Exception("This patient is currently in Rebooking status. An Initial Evaluation (OT/BT) is not required at this time.");
             }
             // Add more rules if needed (e.g., limit total pending requests)
         }
         $stmt_check->close();
     } else {
         throw new Exception("Database error checking for existing appointments.");
     }


    // =============================================
    // --- Prepare Data for Insertion based on Action ---
    // =============================================
    $therapist_id_null = null; // Therapist assigned later by admin/head
    $rebooked_by_null = null;
    $appointment_date_val = null;
    $appointment_time_val = null;
    $status_val = null;
    $session_type_val = $appointment_type;
    $validation_notes_null = null;
    $edit_count_default = 0;
    $auto_cancel_deadline_null = null;
    $referral_id_val = $referral_id; // Use the ID generated earlier (null if not IE or no upload)
    $pg_session_id_val = null;


    if ($action === 'request_waitlist') {
        // --- WAITLIST REQUEST ---
        if ($appointment_type !== 'IE-OT' && $appointment_type !== 'IE-BT') {
             throw new Exception("Waitlisting is currently only available for Initial Evaluation (OT/BT).");
        }
        // Referral already checked above

        $waitlist_type = $_POST['waitlist_type'] ?? null; // 'specific_date' or 'any_day'
        $specific_date = ($waitlist_type === 'specific_date') ? ($_POST['specific_date'] ?? null) : null;

        if (!$waitlist_type || ($waitlist_type === 'specific_date' && !$specific_date)) {
             throw new Exception("Invalid waitlist request data.");
        }
        // Validate specific date format if provided
        if ($specific_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $specific_date)) {
             throw new Exception("Invalid date format provided for specific date waitlist.");
        }


        $status_val = ($waitlist_type === 'specific_date') ? 'Waitlisted - Specific Date' : 'Waitlisted - Any Day';
        $appointment_date_val = $specific_date; // NULL if 'any_day'
        $appointment_time_val = null; // Time is always NULL for waitlist
        $pg_session_id_val = null;

    } else {
        // --- NORMAL APPOINTMENT BOOKING ('book_appointment' action) ---
        $status_val = "pending"; // Default status for new requests

        if ($appointment_type === "Playgroup") {
            // --- Playgroup Booking ---
            $pg_session_id_val = filter_input(INPUT_POST, 'pg_session_id', FILTER_VALIDATE_INT);
            if (!$pg_session_id_val) {
                 throw new Exception("No Playgroup session selected.");
            }
            $referral_id_val = null; // No referral needed for Playgroup

            // Fetch date/time from the selected session & check capacity again (server-side check)
            $stmt_pg = $connection->prepare("SELECT date, time, max_capacity, current_count FROM playgroup_sessions WHERE pg_session_id = ? FOR UPDATE"); // Lock row
            if ($stmt_pg) {
                 $stmt_pg->bind_param("s", $pg_session_id_val); // pg_session_id is varchar
                 $stmt_pg->execute();
                 $pgResult = $stmt_pg->get_result();
                 if ($pgData = $pgResult->fetch_assoc()) {
                     // Check capacity
                     if ($pgData['current_count'] >= $pgData['max_capacity']) {
                          throw new Exception("Sorry, this Playgroup session just filled up. Please choose another.");
                     }
                     $appointment_date_val = $pgData['date'];
                     $appointment_time_val = $pgData['time'];
                     // Note: We'll increment current_count later AFTER successful appointment insert
                 } else {
                     throw new Exception("Selected Playgroup session not found or invalid.");
                 }
                 $stmt_pg->close();
             } else {
                 throw new Exception("Database error fetching Playgroup session details.");
             }

        } else { // --- IE-OT or IE-BT Booking ---
            $appointment_date_val = $_POST['appointment_date'] ?? null;
            $appointment_time_val = $_POST['appointment_time'] ?? null; // Expecting HH:MM:SS
             $pg_session_id_val = null;
             // Referral requirement already checked

             // Validate Date and Time format/values
             if (!$appointment_date_val || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date_val)) {
                 throw new Exception("Invalid date provided for the appointment.");
             }
             if (!$appointment_time_val || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $appointment_time_val)) {
                  throw new Exception("Invalid time provided for the appointment.");
             }

             // Optional: Add server-side check for date range (min/max advance days)
             // Optional: Add server-side check if the selected slot IS actually available based on get_available_slots_enhanced logic (robust check against race conditions)
        }
    } // End if/else for $action

    // --- Perform the Database Insert ---
    $insertSQL = "INSERT INTO appointments (
        account_id, therapist_id, rebooked_by, patient_id, date, time,
        status, session_type, validation_notes, edit_count,
        auto_cancel_deadline, referral_id, pg_session_id, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt_insert = $connection->prepare($insertSQL);
    if ($stmt_insert === false) {
        throw new Exception("Database error preparing the appointment request.");
    }

    // Types: i=integer, s=string, d=double, b=blob
    // account_id(i), therapist_id(i), rebooked_by(i), patient_id(i), date(s), time(s),
    // status(s), session_type(s), validation_notes(s), edit_count(i),
    // auto_cancel_deadline(s), referral_id(i), pg_session_id(s) <-- pg_session_id is varchar!
    $bind_types = "iiiisssssisii"; // Adjusted for referral_id(i), pg_session_id(s - used to be i) -- Check table definition for pg_session_id! If it's INT use 'i'. If VARCHAR use 's'. Assuming VARCHAR based on schema dump.
     // Re-checking schema dump: `pg_session_id` varchar(20) DEFAULT NULL -- SO IT IS 's'
     $bind_types = "iiiisssssisis"; // Corrected: referral_id=i, pg_session_id=s


    $stmt_insert->bind_param(
        $bind_types,
        $account_id,                // 1 (i)
        $therapist_id_null,         // 2 (i)
        $rebooked_by_null,          // 3 (i)
        $patient_id,                // 4 (i)
        $appointment_date_val,      // 5 (s) - Date (NULL ok for waitlist any day)
        $appointment_time_val,      // 6 (s) - Time (NULL ok for waitlist)
        $status_val,                // 7 (s) - 'Pending' or 'Waitlisted - ...'
        $session_type_val,          // 8 (s) - 'IE-OT', 'IE-BT', 'Playgroup'
        $validation_notes_null,     // 9 (s)
        $edit_count_default,        // 10 (i)
        $auto_cancel_deadline_null, // 11 (s)
        $referral_id_val,           // 12 (i) - Referral ID (NULL ok)
        $pg_session_id_val          // 13 (s) - PG Session ID (NULL ok)
    );

    if (!$stmt_insert->execute()) {
        throw new Exception("Error saving the appointment request: " . $stmt_insert->error);
    }
    $new_appointment_id = $stmt_insert->insert_id;
    $stmt_insert->close();
    error_log("Appointment record created with ID: $new_appointment_id");


    // --- Update Playgroup Count if applicable ---
    if ($appointment_type === "Playgroup" && $pg_session_id_val && $status_val === 'Pending') { // Only increment for new pending playgroup bookings
         $stmt_update_pg = $connection->prepare("UPDATE playgroup_sessions SET current_count = current_count + 1 WHERE pg_session_id = ?");
         if ($stmt_update_pg) {
             $stmt_update_pg->bind_param("s", $pg_session_id_val);
             if (!$stmt_update_pg->execute() || $stmt_update_pg->affected_rows === 0) {
                  // Log warning, but don't necessarily fail the whole transaction? Or should we rollback? Decide policy.
                  error_log("Warning: Could not increment playgroup count for session $pg_session_id_val after booking $new_appointment_id.");
             }
             $stmt_update_pg->close();
         } else {
              error_log("Warning: Could not prepare playgroup count update statement: " . $connection->error);
         }
    }


    // --- Commit Transaction ---
    $connection->commit();

    // --- Send Confirmation Email ---
    $emailSent = send_email_notification($client_email, $client_name, $patient_name, $session_type_val, $appointment_date_val, $appointment_time_val, $status_val);

    // --- Success Response ---
     $successMessage = ($action === 'request_waitlist' ? "Successfully added to the waitlist." : "Appointment requested successfully.");
     $emailMessage = $emailSent ? " A confirmation email has been sent." : " However, the confirmation email could not be sent.";

     echo json_encode([
        "status" => "success",
        "message" => $successMessage . $emailMessage,
        // Send details for Swal confirmation on the frontend
        "swal" => [
             "title" => ($action === 'request_waitlist' ? "Waitlist Request Received!" : "Request Submitted!"),
             "text" => ($action === 'request_waitlist' ? "You've been added to the waitlist. We'll notify you if a slot opens." : "Your appointment request is pending approval. Check your email for confirmation."),
             "icon" => $emailSent ? "success" : "warning" // Use warning if email failed
         ],
         "reload" => false // Prevent automatic reload, let frontend handle reset via Swal .then()
    ]);


} catch (Exception $e) {
    // --- Rollback Transaction on Error ---
    $connection->rollback();
    error_log("Booking/Waitlist Error: " . $e->getMessage());

    // --- Error Response ---
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(), // Send specific error message back
        "swal" => [
             "title" => "Error!",
             "text" => $e->getMessage(),
             "icon" => "error"
        ]
    ]);

} finally {
    // Ensure connection is closed if it was opened
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}
exit(); // Terminate script execution
?>
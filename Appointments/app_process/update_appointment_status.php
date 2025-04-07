<?php
require_once "../../dbconfig.php";
require_once "../../Accounts/signupverify/vendor/autoload.php"; 
use PHPMailer\PHPMailer\PHPMailer;

session_start();

// ✅ Ensure Only Authorized Roles Can Access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ['admin', 'head therapist', 'therapist'])) {
    echo json_encode(["status" => "error", "title" => "Unauthorized", "message" => "Access denied."]);
    exit();
}

// ✅ Ensure request is POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ✅ First, check if JSON is sent
    $requestData = json_decode(file_get_contents("php://input"), true);

    if ($requestData) {  // If JSON is detected
        $appointment_id = $requestData['appointment_id'] ?? null;
        $status = $requestData['status'] ?? null;
        $therapist_id = $requestData['therapist_id'] ?? null;
        $validation_notes = isset($requestData['validation_notes']) ? trim($requestData['validation_notes']) : null;
        $pg_session_id = $requestData['pg_session_id'] ?? null;
        $date = $requestData['date'] ?? null;  // ✅ Fixed missing assignment
        $time = $requestData['time'] ?? null;  // ✅ Fixed missing assignment

    } else {  // Otherwise, fall back to form-encoded `$_POST`
        $appointment_id = $_POST['appointment_id'] ?? null;
        $status = $_POST['status'] ?? null;
        $therapist_id = $_POST['therapist_id'] ?? null;
        $validation_notes = isset($_POST['validation_notes']) ? trim($_POST['validation_notes']) : null;
        $pg_session_id = $requestData['pg_session_id'] ?? null;
        $date = $_POST['date'] ?? null;  // ✅ Fixed missing assignment
        $time = $_POST['time'] ?? null;  // ✅ Fixed missing assignment

    }

    // ✅ Validate input
    if (!$appointment_id || !$status) {
        echo json_encode(["status" => "error", "title" => "Missing Data", "message" => "Invalid request."]);
        exit();
    }

    // ✅ Ensure status is valid
    $validStatuses = ["approved", "declined", "waitlisted", "cancelled", "completed", "pending"];
    if (!in_array(strtolower($status), $validStatuses)) {
        echo json_encode(["status" => "error", "title" => "Invalid Status", "message" => "Invalid status update."]);
        exit();
    }    

    // ✅ Fetch Appointment Details
    $query = "SELECT a.session_type, a.date, a.time, a.status, u.account_Email, 
                     p.first_name, p.last_name, u.account_FName AS client_fname, u.account_LName AS client_lname
              FROM appointments a
              JOIN users u ON a.account_id = u.account_ID
              JOIN patients p ON a.patient_id = p.patient_id
              WHERE a.appointment_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        echo json_encode(["status" => "error", "title" => "Error", "message" => "Appointment not found."]);
        exit();
    }

    $appointment = $result->fetch_assoc();
    $current_status = strtolower($appointment['status']);
    $email = $appointment['account_Email'];
    $session_type = $appointment['session_type'];
    $appointment_date = $appointment['date'];
    $appointment_time = $appointment['time'];
    $patient_name = $appointment['first_name'] . " " . $appointment['last_name'];
    $client_name = $appointment['client_fname'] . " " . $appointment['client_lname'];
    $account_type = strtolower($_SESSION['account_Type']);



    // ✅ Regular Approval Process (Pending → Approved)
    if ($status === "approved" && $current_status === "pending") {
        if ($session_type === "playgroup") {
            // ✅ Use passed session ID from frontend
            $sessionID = $pg_session_id ?? null;

            if (!$sessionID) {
                echo json_encode(["status" => "error", "message" => "Please select a Playgroup session."]);
                exit();
            }

            // ✅ Double-check session still exists and has capacity
            $checkSlotQuery = "SELECT current_count, max_capacity FROM playgroup_sessions WHERE pg_session_id = ? AND status = 'Open'";
            $checkStmt = $connection->prepare($checkSlotQuery);
            $checkStmt->bind_param("s", $sessionID);
            $checkStmt->execute();
            $slotInfo = $checkStmt->get_result()->fetch_assoc();

            if (!$slotInfo) {
                echo json_encode(["status" => "error", "message" => "Selected Playgroup session does not exist or is not open."]);
                exit();
            }

            if ($slotInfo["current_count"] >= $slotInfo["max_capacity"]) {
                echo json_encode(["status" => "error", "message" => "This Playgroup session is already full."]);
                exit();
            }

            // ✅ Update appointment
            $updateAppointmentQuery = "UPDATE appointments 
                                    SET pg_session_id = ?, status = 'Approved' 
                                    WHERE appointment_id = ?";
            $updateStmt = $connection->prepare($updateAppointmentQuery);
            $updateStmt->bind_param("si", $sessionID, $appointment_id);
            $updateStmt->execute();

            // ✅ Increase patient count (only if appointment update succeeded)
            if ($updateStmt->affected_rows > 0) {
                $incrementStmt = $connection->prepare("UPDATE playgroup_sessions SET current_count = current_count + 1 WHERE pg_session_id = ?");
                $incrementStmt->bind_param("s", $sessionID);
                $incrementStmt->execute();

                send_email_notification(
                    email: $email,
                    status: $status,
                    session_type: $session_type,
                    patient_name: $patient_name,
                    client_name: $client_name,
                    appointment_date: $appointment_date,
                    appointment_time: $appointment_time,
                    current_status: $current_status,
                    therapist_id: null,
                    isRebooked: false,
                    reason: $validation_notes
                );
                echo json_encode([
                    "status" => "success",
                    "title" => "Appointment Approved",
                    "message" => "Appointment for <strong>$patient_name</strong> has been <strong>approved</strong>. Email notification sent."
                ]);
                exit();
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to assign the Playgroup session."]);
                exit();
            }

        } else {
            // ✅ Other session types still need therapist assigned
            if (!$therapist_id) {
                echo json_encode(["status" => "error", "title" => "Validation Error", "message" => "A therapist must be assigned."]);
                exit();
            }

            $updateQuery = "UPDATE appointments SET status = ?, therapist_id = ? WHERE appointment_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("sii", $status, $therapist_id, $appointment_id);

            if ($stmt->execute()) {
                send_email_notification(
                    email: $email,
                    status: $status,
                    session_type: $session_type,
                    patient_name: $patient_name,
                    client_name: $client_name,
                    appointment_date: $appointment_date,
                    appointment_time: $appointment_time,
                    current_status: $current_status,
                    therapist_id: null,
                    isRebooked: false,
                    reason: $validation_notes
                );
                echo json_encode(["status" => "success", "title" => "Appointment Approved", "message" => "Appointment for <strong>$patient_name</strong> has been <strong>approved</strong>. Email notification sent."]);
                exit();
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to approve appointment."]);
                exit();
            }
        }
    }



    // ✅ Reschedule Waitlisted Appointment (Waitlisted → Approved)
    else if ($status === "approved" && $current_status === "waitlisted") {
        // If this is a Playgroup session assignment
        if (!empty($pg_session_id)) {
            $updateQuery = "UPDATE appointments SET status = ?, pg_session_id = ?, updated_at = NOW() WHERE appointment_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("ssi", $status, $pg_session_id, $appointment_id);

            if ($stmt->execute()) {
                // ✅ Increment current_count since we assigned a new patient
                $incrementStmt = $connection->prepare("UPDATE playgroup_sessions SET current_count = current_count + 1 WHERE pg_session_id = ?");
                $incrementStmt->bind_param("s", $pg_session_id);
                $incrementStmt->execute();
            
                send_email_notification(
                    email: $email,
                    status: $status,
                    session_type: $session_type,
                    patient_name: $patient_name,
                    client_name: $client_name,
                    appointment_date: $appointment_date,
                    appointment_time: $appointment_time,
                    current_status: $current_status,
                    therapist_id: null,
                    isRebooked: false,
                    reason: $validation_notes
                );
                echo json_encode([
                    "status" => "success",
                    "title" => "Assigned to Playgroup Slot",
                    "message" => "Appointment for <strong>$patient_name</strong> has been assigned to a playgroup session."
                ]);
                exit();
            } else {
                echo json_encode([
                    "status" => "error",
                    "title" => "Database Error",
                    "message" => "Failed to assign playgroup session."
                ]);
                exit();
            }
        }

        // 🔒 Standard behavior for 1-on-1 sessions
        if (empty($date) || empty($time) || empty($therapist_id)) {
            echo json_encode([
                "status" => "error",
                "title" => "Missing Data",
                "message" => "Date, time, and therapist are required for rescheduling."
            ]);
            exit();
        }

        $updateQuery = "UPDATE appointments SET status = ?, date = ?, time = ?, therapist_id = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("sssii", $status, $date, $time, $therapist_id, $appointment_id);

        if ($stmt->execute()) {
            send_email_notification(
                email: $email,
                status: $status,
                session_type: $session_type,
                patient_name: $patient_name,
                client_name: $client_name,
                appointment_date: $appointment_date,
                appointment_time: $appointment_time,
                current_status: $current_status,
                therapist_id: null,
                isRebooked: false,
                reason: $validation_notes
            );            echo json_encode([
                "status" => "success",
                "title" => "Appointment Rescheduled",
                "message" => "Appointment for <strong>$patient_name</strong> has been rescheduled and assigned to a therapist."
            ]);
            exit();
        } else {
            echo json_encode([
                "status" => "error",
                "title" => "Database Error",
                "message" => "Failed to reschedule appointment."
            ]);
            exit();
        }
    }


    // ✅ Therapist Cancelation for "Approved" Appointments
    if ($status === "cancelled" && in_array($account_type, ["therapist", "head therapist", "admin"])) {
        if (empty($validation_notes)) {
            echo json_encode(["status" => "error", "title" => "Missing Information", "message" => "A reason is required for cancellation."]);
            exit();
        }

        // ✅ Update the appointment to "Cancelled" and store validation notes
        $updateQuery = "UPDATE appointments SET status = ?, validation_notes = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("ssi", $status, $validation_notes, $appointment_id);

        if ($stmt->execute()) {
            send_email_notification(
                email: $email,
                status: $status,
                session_type: $session_type,
                patient_name: $patient_name,
                client_name: $client_name,
                appointment_date: $appointment_date,
                appointment_time: $appointment_time,
                current_status: $current_status,
                therapist_id: null,
                isRebooked: false,
                reason: $validation_notes
            );            echo json_encode(["status" => "success", "title" => "Appointment Cancelled", "message" => "Appointment for <strong>$patient_name</strong> has been <strong>cancelled</strong>. Email notification sent."]);
            exit();
        } else {
            echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to cancel appointment."]);
            exit();
        }
    }

    // ✅ Allow Pending → Waitlisted before default error block
    if ($current_status === "pending" && $status === "waitlisted") {
        if (empty($validation_notes)) {
            echo json_encode(["status" => "error", "title" => "Missing Information", "message" => "A reason is required for waitlisting."]);
            exit();
        } else {
            // ✅ Move appointment to waitlisted
            $updateQuery = "UPDATE appointments SET status = ?, validation_notes = ? WHERE appointment_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("ssi", $status, $validation_notes, $appointment_id);
        }
        if ($stmt->execute()) {
            send_email_notification(
                email: $email,
                status: $status,
                session_type: $session_type,
                patient_name: $patient_name,
                client_name: $client_name,
                appointment_date: $appointment_date,
                appointment_time: $appointment_time,
                current_status: $current_status,
                therapist_id: null,
                isRebooked: false,
                reason: $validation_notes
            );            echo json_encode(["status" => "success", "title" => "Appointment Waitlisted", "message" => "Appointment for </strong>$patient_name<strong> has been moved to <strong>Waitlisted</strong>."]);
            exit();
        } else {
            echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to update appointment to Waitlisted."]);
            exit();
        }
    }

    
    
    if ($status === "completed" && $session_type === "playgroup") {
        // ✅ Ensure attendance is marked before completing
        $checkAttendanceQuery = "SELECT COUNT(*) AS pending FROM appointments 
                                 WHERE pg_session_id = ? AND (pg_attendance IS NULL OR pg_attendance = '')";
        $stmt = $connection->prepare($checkAttendanceQuery);
        $stmt->bind_param("s", $pg_sessionID);
        $stmt->execute();
        $result = $stmt->get_result();
        $pending = $result->fetch_assoc();
    
        if ($pending["pending"] > 0) {
            echo json_encode(["status" => "error", "message" => "Please mark attendance for all patients before completing the session."]);
            exit();
        } else {
            // ✅ Mark session as completed
            $updateQuery = "UPDATE playgroup_sessions SET status = 'Completed' WHERE pg_session_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("s", $pg_sessionID);
            $stmt->execute();
        }
    }
    // ✅ Handle "Completed" Status
    if ($status === "completed") {
        if ($current_status === "approved") {  // <-- Fixed Logic
            $updateQuery = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("si", $status, $appointment_id);

            if ($stmt->execute()) {
                send_email_notification(
                    email: $email,
                    status: $status,
                    session_type: $session_type,
                    patient_name: $patient_name,
                    client_name: $client_name,
                    appointment_date: $appointment_date,
                    appointment_time: $appointment_time,
                    current_status: $current_status,
                    therapist_id: null,
                    isRebooked: false,
                    reason: $validation_notes
                );            
                echo json_encode(["status" => "success", "title" => "Appointment Completed", "message" => "Appointment for <strong>$patient_name</strong> has been marked as <strong>Completed</strong>. Email notification sent."]);
            } else {
                echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to update appointment status to completed."]);
            }
        } else {
            echo json_encode(["status" => "error", "title" => "Invalid Status Transition", "message" => "You cannot moddddve from '$current_status' to '$status'."]);
        }
        exit();
    }


    // ✅ Handle "Declined" & "Cancelled" Status (Both Require Validation Notes)
    if ($status === "declined" || $status === "cancelled" && in_array($account_type, ["admin", "head therapist, therapist"])) {
        if (empty($validation_notes)) {
            echo json_encode(["status" => "error", "title" => "Missing Information", "message" => "A reason is required."]);
            exit();
        } else { $updateQuery = "UPDATE appointments SET status = ?, validation_notes = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("ssi", $status, $validation_notes, $appointment_id);
        }

        if ($stmt->execute()) {
            send_email_notification(
                email: $email,
                status: $status,
                session_type: $session_type,
                patient_name: $patient_name,
                client_name: $client_name,
                appointment_date: $appointment_date,
                appointment_time: $appointment_time,
                current_status: $current_status,
                therapist_id: null,
                isRebooked: false,
                reason: $validation_notes
            );            echo json_encode(["status" => "success", "title" => "Appointment $status", "message" => "Appointment for <strong>$patient_name</strong> has been <strong>$status</strong>. Email notification sent."]);
            exit();
        }else {
            echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to decline/cancel appointment."]);
            exit();
        }
    }

    
     // ✅ Error Handling: Prevent invalid transitions
     else {
        echo json_encode(["status" => "error", "title" => "Invalid Status Transition", "message" => "You cannot move from '$current_status' to '$status'."]);
        exit();
    }

    
    //  CLIENT CANCELLATION ( REQUIRES A REASON), notifies all head therapist**
    if ($status === "cancelled" && $account_type === "client") {
        // ✅ Allow Clients to Cancel Only These Statuses
        if (!in_array($current_status, ["pending", "approved", "waitlisted"])) {
            echo json_encode(["status" => "error", "title" => "Invalid Action", "message" => "You can only cancel pending, approved, or waitlisted appointments."]);
            exit();
        }
    
        // ✅ Require a cancellation reason
        if (empty($validation_notes)) {
            echo json_encode(["status" => "error", "title" => "Missing Information", "message" => "Please provide a reason for cancellation."]);
            exit();
        } else {
            // ✅ Prepend "Client Cancelled: " to the notes
            $formatted_notes = "Client Cancelled: " . trim($validation_notes);
    
            // ✅ Update the status and validation notes in the database
            $updateQuery = "UPDATE appointments SET status = ?, validation_notes = ? WHERE appointment_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("ssi", $status, $formatted_notes, $appointment_id);
        }
    
        if ($stmt->execute()) {
            // ✅ Fetch all head therapists' emails
            $emailQuery = "SELECT account_Email FROM users WHERE LOWER(account_Type) = 'head therapist'";
            $emailResult = $connection->query($emailQuery);
            
            $headTherapistEmails = [];
            while ($row = $emailResult->fetch_assoc()) {
                $headTherapistEmails[] = $row['account_Email'];
            }
    
            // ✅ Send email notification to each head therapist
            foreach ($headTherapistEmails as $headTherapistEmail) {
                send_email_notification_to_ht($headTherapistEmail, $patient_name, $client_name, $session_type, $appointment_date, $appointment_time, $validation_notes);
            }
    
            echo json_encode(["status" => "success", "title" => "Appointment Cancelled", "message" => "Your appointment has been cancelled."]);
            exit();
        } else {
            echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to cancel appointment."]);
            exit();
        }
    }
    



        // ✅ Handle "Waitlisted" Status
        if ($status === "waitlisted") {
            if (empty($validation_notes)) {
                echo json_encode(["status" => "error", "title" => "Missing Information", "message" => "A reason is required for waitlisting."]);
                exit();
            } else {
                $updateQuery = "UPDATE appointments SET status = ?, validation_notes = ? WHERE appointment_id = ?";
                $stmt = $connection->prepare($updateQuery);
                $stmt->bind_param("ssi", $status, $validation_notes, $appointment_id);
            }
    
            if ($stmt->execute()) {
                // ✅ Send Email Notification to Client
                send_email_notification($email, $status, $session_type, $patient_name, $client_name, $appointment_date, $appointment_time, $current_status, false, $validation_notes);
    
                echo json_encode([
                    "status" => "success",
                    "title" => "Appointment Waitlisted",
                    "message" => "Appointment for <strong>$patient_name</strong> has been moved to <strong>Waitlisted</strong>. Email notification sent."
                ]);
                exit();
            } else {
                echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to update appointment to Waitlisted."]);
                exit();
            }
        }



}

// ✅ Function to Send Email Notifications
function send_email_notification($email, $status, $session_type, $patient_name, $client_name, $appointment_date, $appointment_time, $current_status, $therapist_id = null, $isRebooked = false, $reason = null) {
    global $connection;
    $mail = new PHPMailer(true);

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
        $mail->Subject = "Appointment Update - $session_type";

        if ($status === "declined" || $status === "cancelled") {
            $emailBody = "
                <h3>Appointment $status</h3>
                <p>Dear <strong>$client_name</strong>,</p>
                <p>Your appointment for <strong>$session_type</strong> with <strong>$patient_name</strong> has been $status.</p>
                <p><strong>Reason:</strong> $reason</p>
                <p>If you have any concerns, please contact us.</p>
            ";
        } // ✅ Special email if appointment was "Waitlisted" before
        else if ($status === "approved" && $current_status === "waitlisted") {
            $emailBody = "
                <h3>Appointment Rescheduled</h3>
                <p>Dear <strong>$client_name</strong>,</p>
                <p>Your waitlisted appointment for <strong>$session_type</strong> with <strong>$patient_name</strong> has now been assigned a new date and time.</p>
                <p><strong>New Schedule:</strong> $appointment_date at $appointment_time</p>
                <p>If you have any concerns, please contact us.</p>
            ";
        } 
        // ✅ Regular approval email
        else {
            $emailBody = "
                <h3>Appointment Approved</h3>
                <p>Dear <strong>$client_name</strong>,</p>
                <p>Your <strong>$session_type</strong> appointment with <strong>$patient_name</strong> has been <strong>$status</strong>.</p>
                <p><strong>Appointment time:</strong> $appointment_date at $appointment_time</p>
                <p>If you have any concerns, please contact us.</p>
            ";
        }
        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ✅ Function to Send Email For HT
function send_email_notification_to_ht($email, $patient_name, $client_name, $session_type, $appointment_date, $appointment_time, $reason) {
    global $connection;
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@myliwanag.com';
        $mail->Password = '[l/+1V/B4';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
        $mail->addAddress($email); // Send to each head therapist
        $mail->isHTML(true);
        $mail->Subject = "Client Cancellation Notice - $session_type";

        $emailBody = "
            <h3>Client Cancelled Appointment</h3>
            <p>Dear Head Therapist,</p>
            <p>The following appointment has been <strong>cancelled</strong> by the client:</p>
            <p><strong>Patient:</strong> $patient_name</p>
            <p><strong>Client:</strong> $client_name</p>
            <p><strong>Session Type:</strong> $session_type</p>
            <p><strong>Appointment Time:</strong> $appointment_date at $appointment_time</p>
            <p><strong>Reason for Cancellation:</strong> $reason</p>
            <p>Please take note of this cancellation for scheduling adjustments.</p>
        ";

        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

?>
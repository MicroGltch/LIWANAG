<?php
require_once "../../dbconfig.php";
require_once "../../Accounts/signupverify/vendor/autoload.php"; 
use PHPMailer\PHPMailer\PHPMailer;

session_start();

if (!isset($_SESSION['account_ID']) || $_SESSION['account_Type'] !== "client") {
    echo json_encode(["status" => "error", "title" => "Unauthorized", "message" => "Access denied."]);
    exit();
}




$requestData = json_decode(file_get_contents("php://input"), true);
$appointment_id = $requestData['appointment_id'] ?? null;
$action = strtolower($requestData['action'] ?? '');
$new_date = $requestData['new_date'] ?? null;
$new_time = $requestData['new_time'] ?? null;
$client_id = $_SESSION['account_ID'];

// ✅ Validate input
if (empty($appointment_id) || empty($action)) {
    echo json_encode(["status" => "error", "title" => "Missing Data", "message" => "Invalid request."]);
    exit();
}

// ✅ Fetch the current appointment and client email
$query = "SELECT a.date, a.time, a.edit_count, a.session_type, a.status, u.account_Email 
          FROM appointments a
          JOIN users u ON a.account_id = u.account_ID
          WHERE a.appointment_id = ? AND a.account_id = ?";

$stmt = $connection->prepare($query);
$stmt->bind_param("ii", $appointment_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    echo json_encode(["status" => "error", "title" => "Unauthorized", "message" => "You can only modify your own appointments."]);
    exit();
}

$client_email = $appointment['account_Email']; // ✅ Get email from the database


// ✅ Handle Cancellation
if ($action === "cancel") {
    if (!in_array($appointment['status'], ["pending", "waitlisted"])) {
        echo json_encode(["status" => "error", "title" => "Invalid Action", "message" => "You can only cancel pending or waitlisted appointments."]);
        exit();
    }

    $validation_notes = $requestData['validation_notes'] ?? null; // ✅ Extract from JSON

    if (empty($validation_notes)) {
        echo json_encode(["status" => "error", "title" => "Missing Information", "message" => "Please provide a reason for cancellation."]);
        exit();
    }

    // ✅ Prepend "Client Cancelled: " to the notes
    $formatted_notes = "Client Cancelled: " . trim($validation_notes);

    $status = "cancelled"; // ✅ Define status before executing
    $updateQuery = "UPDATE appointments SET status = ?, validation_notes = ? WHERE appointment_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("ssi", $status, $formatted_notes, $appointment_id);
    
    if ($stmt->execute()) {
        send_email_notification($client_email, "cancelled", $appointment['session_type'], $appointment['date'], $appointment['time']);
        echo json_encode(["status" => "success", "title" => "Appointment Cancelled", "message" => "Your appointment has been cancelled."]);
        exit();
    } else {
        echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to cancel appointment."]);
        exit();
    }
}

// ✅ Handle Editing (Rescheduling) – ONLY IF STATUS IS "PENDING"
if ($action === "edit") {
    if ($appointment['status'] !== "pending") {
        echo json_encode(["status" => "error", "title" => "Not Allowed", "message" => "You can only edit Pending appointments."]);
        exit();
    }

    if (empty($new_date) || empty($new_time)) {
        echo json_encode(["status" => "error", "title" => "Missing Data", "message" => "New date and time are required."]);
        exit();
    }

    if ($appointment['edit_count'] >= 2) {
        echo json_encode(["status" => "error", "title" => "Edit Limit Reached", "message" => "You can only edit your appointment twice."]);
        exit();
    }

    $updateQuery = "UPDATE appointments SET date = ?, time = ?, edit_count = edit_count + 1 WHERE appointment_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("ssi", $new_date, $new_time, $appointment_id);

    if ($stmt->execute()) {
        // ✅ Send to client
        send_email_notification($client_email, "rescheduled", $appointment['session_type'], $new_date, $new_time);

        // ✅ Also send to all head therapists
        $heads = $connection->query("SELECT account_Email FROM users WHERE account_Type = 'Head Therapist'");
        while ($ht = $heads->fetch_assoc()) {
            send_email_notification($ht['account_Email'], "rescheduled_notice", $appointment['session_type'], $new_date, $new_time);
        }

        echo json_encode(["status" => "success", "title" => "Appointment Updated", "message" => "Your appointment has been updated."]);
        exit();
    } else {
        echo json_encode(["status" => "error", "title" => "Database Error", "message" => "Failed to update appointment."]);
        exit();
    }
}

// ✅ Function to Send Email Notifications for Changes
function send_email_notification($email, $action, $session_type, $date, $time) {
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
        $mail->addAddress($email);
        $mail->isHTML(true);

        // ✅ Email to client when cancelled
        if ($action === "cancelled") {
            $mail->Subject = "Appointment Cancellation Notice";
            $mail->Body = "
                <h3>Appointment Cancelled</h3>
                <p>Dear Client,</p>
                <p>Your appointment for <strong>$session_type</strong> on <strong>$date at $time</strong> has been <strong>cancelled</strong>.</p>
                <p>If this was not intentional, please contact us.</p>
            ";
        }

        // ✅ Email to Head Therapists when client reschedules
        else if ($action === "rescheduled_notice") {
            $mail->Subject = "Client Rescheduled Appointment";
            $mail->Body = "
                <h3>Client Appointment Rescheduled</h3>
                <p>A client has rescheduled their <strong>$session_type</strong> appointment.</p>
                <p><strong>New Date:</strong> $date</p>
                <p><strong>New Time:</strong> $time</p>
                <p>Please review if further action is needed.</p>
            ";
        }

        // ✅ Default email to client for rescheduling
        else if ($action === "rescheduled") {
            $mail->Subject = "Appointment Update Notification";
            $mail->Body = "
                <h3>Appointment Update</h3>
                <p>Dear Client,</p>
                <p>Your appointment details have been <strong>modified</strong>.</p>
                <ul>
                    <li><strong>Session Type:</strong> $session_type</li>
                    <li><strong>Date:</strong> $date</li>
                    <li><strong>Time:</strong> $time</li>
                </ul>
                <p>If you did not make these changes, please contact us immediately.</p>
            ";
        }

        $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

?>

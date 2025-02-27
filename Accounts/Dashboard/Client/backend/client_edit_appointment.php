<?php
require_once "../../../../dbconfig.php";
require_once "../../../../Accounts/signupverify/vendor/autoload.php"; 
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

// ✅ Fetch the current appointment
$query = "SELECT date, time, edit_count, session_type, status FROM appointments WHERE appointment_id = ? AND account_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ii", $appointment_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    echo json_encode(["status" => "error", "title" => "Unauthorized", "message" => "You can only modify your own appointments."]);
    exit();
}

// ✅ Handle Cancellation
if ($action === "cancel") {
    if (!in_array($appointment['status'], ["pending", "waitlisted"])) {
        echo json_encode(["status" => "error", "title" => "Invalid Action", "message" => "You can only cancel pending or waitlisted appointments."]);
        exit();
    }

    $updateQuery = "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("i", $appointment_id);

    if ($stmt->execute()) {
        send_email_notification($_SESSION['email'], "cancelled", $appointment['session_type'], $appointment['date'], $appointment['time']);
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
        send_email_notification($_SESSION['email'], "rescheduled", $appointment['session_type'], $new_date, $new_time);
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

        // ✅ Email Body for Cancellations
        if ($action === "cancelled") {
            $mail->Subject = "Appointment Cancellation Notice";
            $mail->Body = "
                <h3>Appointment Cancelled</h3>
                <p>Dear Client,</p>
                <p>Your appointment for <strong>$session_type</strong> on <strong>$date at $time</strong> has been <strong>cancelled</strong>.</p>
                <p>If this was not intentional, please contact us.</p>
            ";
        }
        // ✅ Email Body for Edits (Rescheduling)
        else {
            $mail->Subject = "Appointment Update Notification";
            $mail->Body = "
                <h3>Appointment Update</h3>
                <p>Dear Client,</p>
                <p>Your appointment details have been <strong>modified</strong>.</p>
                <p><strong>New Appointment Details:</strong></p>
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

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
    } else {  // Otherwise, fall back to form-encoded `$_POST`
        $appointment_id = $_POST['appointment_id'] ?? null;
        $status = $_POST['status'] ?? null;
        $therapist_id = $_POST['therapist_id'] ?? null;
        $validation_notes = isset($_POST['validation_notes']) ? trim($_POST['validation_notes']) : null;
    }

    // ✅ Debug log to check which format is received
    error_log("Received appointment_id: " . $appointment_id);
    error_log("Received status: " . $status);
    error_log("Received therapist_id: " . $therapist_id);
    error_log("Received validation_notes: " . $validation_notes);

    // ✅ Validate input
    if (!$appointment_id || !$status) {
        echo json_encode(["status" => "error", "title" => "Missing Data", "message" => "Invalid request."]);
        exit();
    }

    // ✅ Ensure status is valid
    $validStatuses = ["approved", "declined", "waitlisted", "cancelled", "completed", "rebooked"];
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
    $email = $appointment['account_Email'];
    $session_type = $appointment['session_type'];
    $appointment_date = $appointment['date'];
    $appointment_time = $appointment['time'];
    $patient_name = $appointment['first_name'] . " " . $appointment['last_name'];
    $client_name = $appointment['client_fname'] . " " . $appointment['client_lname'];

    // ✅ Handle "Approved" Status
    if ($status === "approved") {
        if (!$therapist_id) {
            echo json_encode(["status" => "error", "title" => "Validation Error", "message" => "A therapist must be assigned."]);
            exit();
        }

        $updateQuery = "UPDATE appointments SET status = ?, therapist_id = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("sii", $status, $therapist_id, $appointment_id);

        if ($stmt->execute()) {
            send_email_notification($email, $status, $session_type, $patient_name, $client_name, $appointment_date, $appointment_time, $therapist_id);
            echo json_encode(["status" => "success", "title" => "Appointment Approved", "message" => "Appointment for **$patient_name** has been **approved**. Email notification sent."]);
            exit();
        }
    }

    // ✅ Handle "Declined" & "Cancelled" Status (Both Require Validation Notes)
    if ($status === "declined" || $status === "cancelled") {
        if (empty($validation_notes)) {
            echo json_encode(["status" => "error", "title" => "Missing Information", "message" => "A reason is required."]);
            exit();
        }

        $updateQuery = "UPDATE appointments SET status = ?, validation_notes = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("ssi", $status, $validation_notes, $appointment_id);

        if ($stmt->execute()) {
            send_email_notification($email, $status, $session_type, $patient_name, $client_name, $appointment_date, $appointment_time, null, false, $validation_notes);
            echo json_encode(["status" => "success", "title" => "Appointment $status", "message" => "Appointment for **$patient_name** has been **$status**. Email notification sent."]);
            exit();
        }
    }

    // ✅ Handle "Completed" Status
    if ($status === "completed") {
        if ($appointment["status"] !== "approved") {
            echo json_encode(["status" => "error", "title" => "Invalid Action", "message" => "Only approved appointments can be marked as completed."]);
            exit();
        }

        $updateQuery = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("si", $status, $appointment_id);

        if ($stmt->execute()) {
            send_email_notification($email, $status, $session_type, $patient_name, $client_name, $appointment_date, $appointment_time);
            echo json_encode(["status" => "success", "title" => "Appointment Completed", "message" => "Appointment for **$patient_name** has been marked as **Completed**. Email notification sent."]);
            exit();
        }
    }
}

// ✅ Function to Send Email Notifications
function send_email_notification($email, $status, $session_type, $patient_name, $client_name, $appointment_date, $appointment_time, $therapist_id = null, $isRebooked = false, $reason = null) {
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
        } else {
            $emailBody = "
                <h3>Appointment Status Update</h3>
                <p>Dear <strong>$client_name</strong>,</p>
                <p>Your appointment for <strong>$session_type</strong> with <strong>$patient_name</strong> has been updated to <strong>$status</strong>.</p>
            ";
        }

        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

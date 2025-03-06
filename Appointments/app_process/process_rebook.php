<?php
require_once "../../dbconfig.php";
require_once "../../Accounts/signupverify/vendor/autoload.php"; // ✅ Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// ✅ Restrict Access to Therapists
// if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
//     echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
//     exit();
// }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointmentID = $_POST['appointment_id'] ?? null; // ✅ Make it optional
    $patientID = $_POST['patient_id'];
    $therapistID = $_SESSION['account_ID']; // Therapist doing the rebooking
    $newDate = $_POST['new_date'];
    $newTime = $_POST['new_time'];
    $status = "Pending"; // Rebooked appointments require validation
    $sessionType = "Rebooking"; // ✅ Automatically set session type

    $connection->begin_transaction(); // Start transaction

    try {
        // ✅ Check if patient already has a pending/approved appointment (excluding the one being completed)
        $checkExistingQuery = "SELECT appointment_id FROM appointments 
                            WHERE patient_id = ? 
                            AND status IN ('Pending', 'Approved') 
                            AND appointment_id != ?";
        $stmt = $connection->prepare($checkExistingQuery);
        $stmt->bind_param("ii", $patientID, $appointmentID);
        $stmt->execute();
        $result = $stmt->get_result();


        if ($result->num_rows > 0) {
            throw new Exception("This patient already has a pending or approved appointment.");
        }

        // ✅ Fetch Client Email & Name
        $emailQuery = "SELECT u.account_Email, u.account_FName, u.account_LName, p.first_name, p.last_name
                       FROM users u
                       JOIN patients p ON u.account_ID = p.account_id
                       WHERE p.patient_id = ?";
        $stmt = $connection->prepare($emailQuery);
        $stmt->bind_param("i", $patientID);
        $stmt->execute();
        $result = $stmt->get_result();
        $client = $result->fetch_assoc();

        if (!$client) {
            throw new Exception("Failed to retrieve client details.");
        }

        $client_email = $client["account_Email"];
        $client_name = $client["account_FName"] . " " . $client["account_LName"];
        $patient_name = $client["first_name"] . " " . $client["last_name"];

        // ✅ Insert the new appointment with "Pending" status
        $query = "INSERT INTO appointments (account_id, patient_id, date, time, session_type, status, rebooked_by) 
                SELECT account_id, ?, ?, ?, ?, ?, ?
                FROM appointments WHERE appointment_id = ?";

        $sessionType = $_POST['service_type'] ?? null;
        if (!$sessionType) {
            throw new Exception("Service type is missing.");
        }

        $stmt = $connection->prepare($query);
        $stmt->bind_param("issssii", $patientID, $newDate, $newTime, $sessionType, $status, $therapistID, $appointmentID);
        $stmt->execute();

        // ✅ Mark original appointment as completed
        $updateQuery = "UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("i", $appointmentID);
        $stmt->execute();

        // ✅ Commit Transaction
        $connection->commit();

        // ✅ Send Email Notification
        if (send_email_notification($client_email, $client_name, $patient_name, $sessionType, $newDate, $newTime)) {
            echo json_encode(["status" => "success", "message" => "Appointment rebooked successfully. The client has been notified."]);
        } else {
            echo json_encode(["status" => "success", "message" => "Appointment rebooked successfully, but the email notification failed."]);
        }
    } catch (Exception $e) {
        $connection->rollback(); // ❌ Rollback in case of error
        echo json_encode(["status" => "error", "message" => "Failed to rebook appointment: " . $e->getMessage()]);
    }
}

// ✅ Function to Send Email Notification for Rebooking
function send_email_notification($email, $client_name, $patient_name, $session_type, $appointment_date, $appointment_time) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com'; // Change this to your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@myliwanag.com'; // Change to your email
        $mail->Password = '[l/+1V/B4'; // Change to your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
        $mail->addAddress($email, $client_name);
        $mail->isHTML(true);
        $mail->Subject = "Rebooked Appointment Confirmation";

        $emailBody = "
            <h3>Your Patient Has Been Rebooked</h3>
            <p>Dear <strong>$client_name</strong>,</p>
            <p>The following rebooking has been made for your patient, <strong>$patient_name</strong>:</p>
            <ul>
                <li><strong>Session Type:</strong> $session_type</li>
                <li><strong>Date:</strong> $appointment_date</li>
                <li><strong>Time:</strong> $appointment_time</li>
                <li><strong>Status:</strong> Pending</li>
            </ul>
            <p>We will notify you once the appointment has been confirmed.</p>
            <p>Thank you for choosing our therapy center.</p>
        ";

        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

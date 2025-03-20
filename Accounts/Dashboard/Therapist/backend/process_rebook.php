<?php
require_once "../../../../dbconfig.php";
require_once "../../../../Accounts/signupverify/vendor/autoload.php"; // ✅ Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// ✅ Restrict Access to Therapists
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointmentID = $_POST['appointment_id'] ?? null;
    $patientID = $_POST['patient_id'];
    $therapistID = $_SESSION['account_ID'];
    $newDate = $_POST['new_date'];
    $newTime = $_POST['new_time'];
    $status = "Pending";
    $sessionType = "Rebooking";
    $serviceType = $_POST['service_type'] ?? null; // ✅ New service type from rebooking form

    $connection->begin_transaction();

    try {
        // ✅ Check if patient already has a pending/approved appointment
        $checkExistingQuery = "SELECT appointment_id FROM appointments 
                               WHERE patient_id = ? 
                               AND status IN ('Pending', 'Approved')";
        $stmt = $connection->prepare($checkExistingQuery);
        $stmt->bind_param("i", $patientID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("This patient already has a pending or approved appointment.");
        }

        if ($appointmentID) {
            // ✅ Case 1: Rebooking from an existing appointment
            $getAccountIdQuery = "SELECT account_id FROM appointments WHERE appointment_id = ?";
            $stmt = $connection->prepare($getAccountIdQuery);
            $stmt->bind_param("i", $appointmentID);
            $stmt->execute();
            $result = $stmt->get_result();
            $oldAppointment = $result->fetch_assoc();

            if (!$oldAppointment) {
                throw new Exception("Failed to fetch previous appointment details.");
            }

            $accountID = $oldAppointment['account_id'];
            $rebookedBy = $therapistID;


            // ✅ Mark original appointment as completed
            $updateQuery = "UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("i", $appointmentID);
            $stmt->execute();
        } else {
            // ✅ Case 2: Rebooking a past patient (No appointment_id)
            $getAccountIdQuery = "SELECT account_id FROM patients WHERE patient_id = ?";
            $stmt = $connection->prepare($getAccountIdQuery);
            $stmt->bind_param("i", $patientID);
            $stmt->execute();
            $result = $stmt->get_result();
            $patientData = $result->fetch_assoc();

            if (!$patientData) {
                throw new Exception("Failed to fetch patient details.");
            }

            $accountID = $patientData['account_id'];
            $rebookedBy = $therapistID; // ✅ Ensure `rebooked_by` is set

        }

        // ✅ Insert new appointment (without service_type, since it's stored in patients)
        $insertQuery = "INSERT INTO appointments (account_id, patient_id, date, time, session_type, status, rebooked_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bind_param("iissssi", $accountID, $patientID, $newDate, $newTime, $sessionType, $status, $rebookedBy);

        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Rebooking failed: No new appointment was created.");
        }

        // ✅ Update patient’s service type
        $updatePatientQuery = "UPDATE patients SET service_type = ? WHERE patient_id = ?";
        $stmt = $connection->prepare($updatePatientQuery);
        $stmt->bind_param("si", $serviceType, $patientID);
        $stmt->execute();

        $connection->commit();

        // ✅ Fetch client email details
        $emailQuery = "SELECT u.account_Email, u.account_FName, u.account_LName, p.first_name, p.last_name
        FROM users u
        JOIN patients p ON u.account_ID = p.account_id
        WHERE p.patient_id = ?";
        $stmt = $connection->prepare($emailQuery);
        $stmt->bind_param("i", $patientID);
        $stmt->execute();
        $result = $stmt->get_result();
        $client = $result->fetch_assoc();

        if ($client) {
            $client_email = $client["account_Email"];
            $client_name = $client["account_FName"] . " " . $client["account_LName"];
            $patient_name = $client["first_name"] . " " . $client["last_name"];

            // ✅ Call the email function after a successful transaction
            if (send_email_notification($client_email, $client_name, $patient_name, $sessionType, $newDate, $newTime)) {
            echo json_encode(["status" => "success", "message" => "Appointment rebooked successfully. The client has been notified."]);
            } else {
            echo json_encode(["status" => "success", "message" => "Appointment rebooked successfully, but the email notification failed."]);
            }
        }

        echo json_encode(["status" => "success", "message" => "Appointment rebooked successfully. "]);
    } catch (Exception $e) {
        $connection->rollback();
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

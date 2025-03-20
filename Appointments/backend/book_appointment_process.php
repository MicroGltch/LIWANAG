<?php
require_once "../../dbconfig.php"; // ✅ Uses existing connection
require_once "../../Accounts/signupverify/vendor/autoload.php"; // ✅ Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();

if (!isset($_SESSION['account_ID'])) {
    echo json_encode(["status" => "error", "message" => "You must be logged in to book an appointment."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    global $connection; // ✅ Use the existing connection
    $account_id = $_SESSION['account_ID'];
    $patient_id = $_POST['patient_id'];
    $appointment_type = $_POST['appointment_type'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $has_referral = $_POST['has_referral'] ?? null;

    $official_referral = $_FILES['official_referral']['name'] ?? null;
    $proof_of_booking = $_FILES['proof_of_booking']['name'] ?? null;
    
    $status = "Pending";
    $referral_id = null; // Default: No referral

    // ✅ Fetch Client Email & Name from `users` Table
    $emailQuery = "SELECT account_Email, account_FName, account_LName FROM users WHERE account_ID = ?";
    $stmt = $connection->prepare($emailQuery);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $client_email = $user['account_Email'] ?? null;
    $client_name = $user['account_FName'] . " " . $user['account_LName'];

    if (!$client_email) {
        echo json_encode(["status" => "error", "message" => "Error: Unable to retrieve client email."]);
        exit();
    }

    // ✅ Prevent Multiple Pending/Confirmed Appointments for the Same Session Type
    $check_existing = "SELECT session_type FROM appointments WHERE patient_id = ? AND status IN ('pending', 'approved', 'waitlisted')";
    $stmt = $connection->prepare($check_existing);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['session_type'] === $appointment_type) {
            echo json_encode(["status" => "error", "message" => "This patient already has a pending or confirmed appointment for this session type."]);
            exit();
        }
    }

    // ✅ Validate Initial Evaluation Booking Date
    if ($appointment_type === "Initial Evaluation") {
        $minDate = new DateTime();
        $minDate->modify('+2 days');
        $maxDate = new DateTime();
        $maxDate->modify('+30 days');

        $selectedDate = new DateTime($appointment_date);
        
        if ($selectedDate < $minDate) {
            echo json_encode(["status" => "error", "message" => "Initial Evaluation must be booked at least 3 days in advance."]);
            exit();
        }

        if ($selectedDate > $maxDate) {
            echo json_encode(["status" => "error", "message" => "Initial Evaluation can only be booked up to 30 days in advance."]);
            exit();
        }
    }

    // ✅ Check Playgroup Session Capacity
    if ($appointment_type === "Playgroup") {
        $check_capacity = "SELECT COUNT(*) as count FROM appointments WHERE date = ? AND time = ? AND session_type = 'Playgroup'";
        $stmt = $connection->prepare($check_capacity);
        $stmt->bind_param("ss", $appointment_date, $appointment_time);
        $stmt->execute();
        $capacity_result = $stmt->get_result();
        $capacity_row = $capacity_result->fetch_assoc();

        if ($capacity_row['count'] >= 6) {
            echo json_encode(["status" => "error", "message" => "This playgroup session is already full. Please choose another time."]);
            exit();
        }
    }

    // ✅ Ensure Initial Evaluation has a referral
    if ($appointment_type === "Initial Evaluation") {
        if (empty($official_referral) && empty($proof_of_booking)) {
            echo json_encode(["status" => "error", "message" => "A doctor's referral or proof of booking is required for Initial Evaluation."]);
            exit();
        }
    }

    // ✅ Ensure Initial Evaluation has a referral
    if ($appointment_type === "Initial Evaluation") {
        if (empty($_FILES['official_referral']['name']) && empty($_FILES['proof_of_booking']['name'])) {
            echo json_encode(["status" => "error", "message" => "A doctor's referral or proof of booking is required for Initial Evaluation."]);
            exit();
        }
    }

    // ✅ Handle Doctor’s Referral Upload
    $uploadDir = "../../uploads/doctors_referrals/";
    $officialFileName = null;
    $proofFileName = null;
    $referralType = null; // New variable to track referral type

    if (!empty($_FILES['official_referral']['name'])) {
        $officialFileName = time() . "_official_" . basename($_FILES['official_referral']['name']);
        $officialFilePath = $uploadDir . $officialFileName;
        move_uploaded_file($_FILES['official_referral']['tmp_name'], $officialFilePath);
        $referralType = 'official'; // Set referral type as official
    }

    if (!empty($_FILES['proof_of_booking']['name']) && empty($officialFileName)) {
        $proofFileName = time() . "_proof_" . basename($_FILES['proof_of_booking']['name']);
        $proofFilePath = $uploadDir . $proofFileName;
        move_uploaded_file($_FILES['proof_of_booking']['tmp_name'], $proofFilePath);
        $referralType = 'proof of booking'; // Set referral type as proof only if official is not set
    }

    // ✅ Insert into `doctor_referrals` table if a referral exists
    if ($officialFileName || $proofFileName) {
        $insertReferralSQL = "INSERT INTO doctor_referrals (patient_id, official_referral_file, proof_of_booking_referral_file, referral_type) 
                      VALUES (?, ?, ?, ?)";
        $stmt = $connection->prepare($insertReferralSQL);
        $stmt->bind_param("isss", $patient_id, $officialFileName, $proofFileName, $referralType);
        if ($stmt->execute()) {
            $referral_id = $stmt->insert_id; // ✅ Get the new referral ID
        }
        $stmt->close();
    }


    // ✅ Insert Appointment Into `appointments` Table
    $insertAppointmentSQL = "INSERT INTO appointments (account_id, patient_id, date, time, session_type, status, referral_id) 
                             VALUES (?, ?, ?, ?, ?, 'Pending', ?)";
    $stmt = $connection->prepare($insertAppointmentSQL);

    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "SQL error: " . $connection->error]);
        exit();
    }

    $stmt->bind_param("iisssi", $account_id, $patient_id, $appointment_date, $appointment_time, $appointment_type, $referral_id);

    if ($stmt->execute()) {
        $appointment_id = $stmt->insert_id; // ✅ Get the newly inserted appointment ID

        // ✅ Send Confirmation Email
        if (send_email_notification($client_email, $client_name, $appointment_type, $appointment_date, $appointment_time)) {
            echo json_encode(["status" => "success", "message" => "Appointment booked successfully. A confirmation email has been sent."]);
        } else {
            echo json_encode(["status" => "success", "message" => "Appointment booked successfully, but email notification failed."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Error booking appointment."]);
    }
    
    $stmt->close();
    $connection->close();
}


// ✅ Function to Send Email Notification for New Appointments
function send_email_notification($email, $client_name, $session_type, $appointment_date, $appointment_time) {
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
        $mail->Subject = "Appointment Confirmation - Therapy Center";

        $emailBody = "
            <h3>Appointment Confirmation</h3>
            <p>Dear <strong>$client_name</strong>,</p>
            <p>Your appointment has been successfully booked with the following details:</p>
            <ul>
                <li><strong>Session Type:</strong> $session_type</li>
                <li><strong>Date:</strong> $appointment_date</li>
                <li><strong>Time:</strong> $appointment_time</li>
                <li><strong>Status:</strong> Pending</li>
            </ul>
            <p>We will notify you once your appointment is confirmed.</p>
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
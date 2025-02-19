<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    echo json_encode(["status" => "error", "message" => "You must be logged in to book an appointment."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_id = $_POST['patient_id'];
    $appointment_type = $_POST['appointment_type'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $doctors_referral = $_FILES['doctors_referral']['name'] ?? null;

    $account_id = $_SESSION['account_ID'];
    $status = "Pending";

    // ✅ Prevent Multiple Pending/Confirmed Appointments for the Selected Patient Only
    $check_existing = "SELECT * FROM appointments WHERE patient_id = ? AND status IN ('Pending', 'Confirmed')";
    $stmt = $connection->prepare($check_existing);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "This patient already has a pending or confirmed appointment."]);
        exit();
    }

    // ✅ Restrict Initial Evaluation (IE) to be booked **at least** 3 days from today
    if ($appointment_type === "Initial Evaluation") {
        $minDate = new DateTime(); 
        $minDate->modify('+3 days'); // Minimum allowed date (3 days from today)
        $minDateString = $minDate->format('Y-m-d');

        $maxDate = new DateTime();
        $maxDate->modify('+30 days'); // Maximum allowed booking (30 days from today or as per settings)
        $maxDateString = $maxDate->format('Y-m-d');

        // ✅ Ensure the appointment date is **at least 3 days ahead**
        if ($appointment_date < $minDateString) {
            echo json_encode(["status" => "error", "message" => "Initial Evaluation must be booked at least 3 days in advance. Minimum allowed date: $minDateString"]);
            exit();
        }

        // ✅ Ensure it does not exceed max allowed booking days
        if ($appointment_date > $maxDateString) {
            echo json_encode(["status" => "error", "message" => "Initial Evaluation can only be booked up to $maxDaysAdvance days in advance. Max allowed date: $maxDateString"]);
            exit();
        }
    }


    // ✅ Ensure Playgroup Sessions Don't Exceed 6 Patients
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

    // ✅ Doctor’s Referral Requirement for IE
    $target_file = null;
    if ($appointment_type === "Initial Evaluation") {
        if (empty($doctors_referral)) {
            echo json_encode(["status" => "error", "message" => "A doctor's referral or proof of booking is required for Initial Evaluation."]);
            exit();
        }

        // File upload handling
        $target_dir = "../uploads/doctors_referrals/";
        $file_ext = strtolower(pathinfo($doctors_referral, PATHINFO_EXTENSION));
        $allowed_types = ["jpg", "jpeg", "png", "pdf"];
        $max_file_size = 5 * 1024 * 1024;

        if (!in_array($file_ext, $allowed_types)) {
            echo json_encode(["status" => "error", "message" => "Invalid file type. Only JPG, JPEG, PNG, and PDF are allowed."]);
            exit();
        }

        if ($_FILES['doctors_referral']['size'] > $max_file_size) {
            echo json_encode(["status" => "error", "message" => "File is too large. Maximum size is 5MB."]);
            exit();
        }

        $new_file_name = uniqid() . "." . $file_ext;
        $target_file = $target_dir . $new_file_name;
        move_uploaded_file($_FILES['doctors_referral']['tmp_name'], $target_file);
    }

    // ✅ Insert Appointment Into Database
    $query = "INSERT INTO appointments (account_id, patient_id, date, time, session_type, doctor_referral, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $connection->prepare($query);
    
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "SQL error: " . $connection->error]);
        exit();
    }

    // ✅ Ensure doctor_referral is NULL-safe
    if ($target_file === null) {
        $stmt->bind_param("iisssss", $account_id, $patient_id, $appointment_date, $appointment_time, $appointment_type, $target_file, $status);
    } else {
        $stmt->bind_param("iisssss", $account_id, $patient_id, $appointment_date, $appointment_time, $appointment_type, $target_file, $status);
    }

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Appointment booked successfully."]);
    } else {
        error_log("Database Error: " . $stmt->error);
        echo json_encode(["status" => "error", "message" => "Error booking appointment."]);
    }

    $stmt->close();
}
?>

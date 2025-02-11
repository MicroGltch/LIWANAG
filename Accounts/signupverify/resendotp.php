<?php
    session_start();
    require_once "../../dbconfig.php";
    include "setotp.php";

    date_default_timezone_set('Asia/Manila');  // Or your desired timezone

    if (!isset($_SESSION['email'])) {
        echo json_encode(["status" => "error", "message" => "Session expired. Please sign up again."]);
        exit();
    }

    $email = $_SESSION['email'];

    // 1. Check if it's been at least 1 minute since the last OTP request (using otp_time)
    $last_request_check_sql = "SELECT otp_time FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($last_request_check_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $last_request_time = strtotime($row['otp_time']); // Convert otp_time to timestamp
        $current_time = time();

        if ($current_time - $last_request_time < 60) { // 60 seconds = 1 minute
            $remaining_seconds = 60 - ($current_time - $last_request_time);
            echo json_encode(["status" => "error", "message" => "Please wait " . $remaining_seconds . " seconds before resending OTP."]);
            exit();
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Error checking last OTP request time. Please try again later."]);
        exit();
    }

    // 2. Generate NEW OTP and update database (including otp_time)
    $new_otp = rand(100000, 999999);
    $new_otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes")); // Calculate expiry time

    $update_otp_sql = "UPDATE users SET otp = ?, otp_expiry = ?, otp_time = NOW() WHERE account_Email = ?"; // Update all three
    $stmt = $connection->prepare($update_otp_sql);
    $stmt->bind_param("sss", $new_otp, $new_otp_expiry, $email); // Bind all three values

    if ($stmt->execute()) {
        send_verification("User", $email, $new_otp); // Send the NEW OTP
        echo json_encode(["status" => "success", "message" => "A new OTP has been sent to your email."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update OTP. Please try again later."]);
    }

    $stmt->close(); // Close the statement
    $connection->close();
?>
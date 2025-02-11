<?php
    session_start();
    require_once "../../dbconfig.php";
    include "setotp.php";

    if (!isset($_SESSION['email'])) {
        echo json_encode(["status" => "error", "message" => "Session expired. Please sign up again."]);
        exit();
    }

    $email = $_SESSION['email'];

    // Check last OTP request time (using NOW() for consistency)
    $check_time_sql = "SELECT otp_expiry FROM users WHERE account_Email =?";
    $stmt = $connection->prepare($check_time_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $otp_expiry_time = strtotime($row['otp_expiry']); 
        if (time() < $otp_expiry_time) {
            $remaining_seconds = $otp_expiry_time - time();
            echo json_encode(["status" => "error", "message" => "Please wait ". $remaining_seconds. " seconds before resending OTP."]);
            exit();
        }
    }

    // Generate new OTP and update database (using NOW() for consistency)
    $new_otp = rand(100000, 999999);
    $update_otp_sql = "UPDATE users SET otp =?, otp_expiry = NOW() + INTERVAL 5 MINUTE WHERE account_Email =?";
    $stmt = $connection->prepare($update_otp_sql);
    $stmt->bind_param("ss", $new_otp, $email);

    if ($stmt->execute()) {
        // Send new OTP email
        send_verification("User", $email, $new_otp);
        echo json_encode(["status" => "success", "message" => "A new OTP has been sent to your email."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update OTP. Please try again later."]);
    }
?>
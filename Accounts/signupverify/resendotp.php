<?php
    session_start();
    require_once "../../dbconfig.php";
    include "setotp.php";

    date_default_timezone_set('Asia/Manila');

    if (!isset($_SESSION['email'])) {
        echo json_encode(["status" => "error", "message" => "Session expired. Please sign up again."]);
        exit();
    }

    $email = $_SESSION['email'];

    // 1. Check Account Status FIRST
    $check_active_sql = "SELECT account_Status FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($check_active_sql); // Use $stmt consistently
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result(); // Use $result consistently

    if ($result && $row = $result->fetch_assoc()) { // Use $row consistently
        if ($row['account_Status'] === 'Active') {
            $update_null_sql = "UPDATE users SET otp = NULL, otp_time = NULL, otp_expiry = NULL WHERE account_Email = ?";
            $stmt_null = $connection->prepare($update_null_sql);
            $stmt_null->bind_param("s", $email);
            $stmt_null->execute();
            $stmt_null->close();
            $stmt->close(); // Close the status check statement

            echo json_encode(["status" => "success", "message" => "Account is already active."]);
            exit();
        }
        $stmt->close(); // Close the status check statement
    } else {
        echo json_encode(["status" => "error", "message" => "Error checking account status."]);
        exit();
    }

    if (isset($_GET['check_expiry'])) {
        $get_expiry_sql = "SELECT otp_expiry FROM users WHERE account_Email = ?";
        $stmt = $connection->prepare($get_expiry_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result && $row = $result->fetch_assoc()) {
            $expiry_time = $row['otp_expiry'];
            if ($expiry_time) {
                echo json_encode(["status" => "success", "expiry_time" => $expiry_time]);
            } else {
                echo json_encode(["status" => "error", "message" => "No OTP found."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Error fetching OTP expiry."]);
        }
    
        $stmt->close();
        $connection->close();
        exit(); // Important: Stop further processing
    }

    // 2. Generate NEW OTP and update database (including otp_time)
    $new_otp = rand(100000, 999999);

    // Consistent time handling:
    $otp_time = date("Y-m-d H:i:s"); // Current time in PHP (Asia/Manila)
    $new_otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes", strtotime($otp_time)));

    $update_otp_sql = "UPDATE users SET otp = ?, otp_expiry = ?, otp_time = ? WHERE account_Email = ?"; // Update all three
    $stmt = $connection->prepare($update_otp_sql);
    $stmt->bind_param("ssss", $new_otp, $new_otp_expiry, $otp_time, $email); // Bind $otp_time as well

    if ($stmt->execute()) {
        send_verification("User", $email, $new_otp); // Send the NEW OTP
        echo json_encode(["status" => "success", "message" => "A new OTP has been sent to your email."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update OTP. Please try again later."]);
    }

    $stmt->close(); // Close the statement
    $connection->close();
?>
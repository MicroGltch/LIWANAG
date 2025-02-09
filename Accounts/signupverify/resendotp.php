<?php
    session_start();
    require_once "../../dbconfig.php";
    include "setotp.php";

    if (!isset($_SESSION['email'])) {
        header("Location: ../signuppage.php");
        exit();
    }

    $email = $_SESSION['email'];

    // Check last OTP request time
    $check_time_sql = "SELECT otp_expiry FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($check_time_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $last_otp_time = strtotime($row['otp_expiry']) - 300; // Original OTP time before 5-minute expiry
        if (time() - $last_otp_time < 60) {
            echo json_encode(["status" => "error", "message" => "Please wait a minute before resending OTP."]);
            exit();
        }
    }

    // Generate new OTP and update database
    $new_otp = rand(100000, 999999);
    $new_otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

    $update_otp_sql = "UPDATE users SET otp = ?, otp_expiry = NOW() + INTERVAL 5 MINUTE WHERE account_Email = ?";
    $stmt = $connection->prepare($update_otp_sql);
    $stmt->bind_param("ss", $new_otp, $email);
    $stmt->execute();

    // Send new OTP email
    send_verification("User", $email, $new_otp);

    echo json_encode(["status" => "success", "message" => "A new OTP has been sent to your email."]);
?>

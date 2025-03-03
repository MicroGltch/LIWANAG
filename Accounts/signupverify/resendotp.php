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
$stmt = $connection->prepare($check_active_sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    if ($row['account_Status'] === 'Active') {
        // Clear any existing OTP data in otp_verifications table
        $update_null_sql = "UPDATE otp_verifications SET otp = NULL, expiry_time = NULL WHERE email = ?";
        $stmt_null = $connection->prepare($update_null_sql);
        $stmt_null->bind_param("s", $email);
        $stmt_null->execute();
        $stmt_null->close();
        $stmt->close();

        echo json_encode(["status" => "success", "message" => "Account is already active."]);
        exit();
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Error checking account status."]);
    exit();
}

// 2. Generate NEW OTP and update database
$new_otp = random_int(100000, 999999); // Use random_int()
$new_otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// Update OTP in otp_verifications table
$update_otp_sql = "UPDATE otp_verifications SET otp = ?, expiry_time = ?, used = 0 WHERE email = ?";
$stmt = $connection->prepare($update_otp_sql);
$stmt->bind_param("sss", $new_otp, $new_otp_expiry, $email);

if ($stmt->execute()) {
    // Get full name from users table
    $getFullName = "SELECT account_FName, account_LName FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($getFullName);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $fullname = $row['account_FName'] . " " . $row['account_LName'];

    send_verification($fullname, $email, $new_otp);
    echo json_encode(["status" => "success", "message" => "A new OTP has been sent to your email."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update OTP. Please try again later."]);
}

$stmt->close();
$connection->close();
?>
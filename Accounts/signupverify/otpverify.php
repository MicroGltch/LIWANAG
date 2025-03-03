<?php
session_start();
require_once "../../dbconfig.php";

if (!isset($_SESSION['email'])) {
    echo "Session expired. Please sign up again.";
    exit();
}

$email = $_SESSION['email'];

date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $otp_input = trim($_POST['otp']);

    // Verify OTP from otp_verifications table
    $otp_sql = "SELECT * FROM otp_verifications WHERE email = ? AND otp = ? AND expiry_time > NOW() AND used = 0";
    $stmt = $connection->prepare($otp_sql);
    $stmt->bind_param("ss", $email, $otp_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // Update user status
        $updatesql = "UPDATE users SET account_Status = 'Active' WHERE account_Email = ?";
        $stmt = $connection->prepare($updatesql);
        $stmt->bind_param("s", $email);
        $stmt->execute();

        // Mark OTP as used
        $updateOtp = "UPDATE otp_verifications SET used = 1 WHERE email = ? AND otp = ?";
        $stmt = $connection->prepare($updateOtp);
        $stmt->bind_param("ss",$email,$otp_input);
        $stmt->execute();

        echo "success";
        exit();
    } else {
        // Check for expired OTP
        $expiry_check_sql = "SELECT expiry_time FROM otp_verifications WHERE email = ? AND expiry_time < NOW()";
        $stmt = $connection->prepare($expiry_check_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $expiry_result = $stmt->get_result();

        if ($expiry_result->num_rows > 0) {
            echo "OTP expired. Please request a new OTP.";
        } else {
            echo "Incorrect OTP. Please try again.";
        }
        exit();
    }
}
?>
<?php
session_start();
require_once "../../dbconfig.php";

if (!isset($_SESSION['email'])) {
    echo "Session expired. Please sign up again.";
    exit();
}

$email = $_SESSION['email'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $otp_input = trim($_POST['otp']);

    $otp_sql = "SELECT * FROM users WHERE account_Email = ? AND otp = ? AND otp_expiry > NOW()";
    $stmt = $connection->prepare($otp_sql);
    $stmt->bind_param("ss", $email, $otp_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $updatesql = "UPDATE users SET account_Status = 'Active', otp = NULL, otp_expiry = NULL WHERE account_Email = ?";
        $stmt = $connection->prepare($updatesql);
        $stmt->bind_param("s", $email);
        $stmt->execute();

        session_unset();
        session_destroy();

        echo "success";
        exit();
    } else {
        $expiry_check_sql = "SELECT otp_expiry FROM users WHERE account_Email = ? AND otp_expiry < NOW()";
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

$delete_unverified = "DELETE FROM users WHERE account_Status = 'Pending' AND otp_expiry < NOW()";
$connection->query($delete_unverified);

$connection->close();
?>
<?php
session_start();
require_once "../../dbconfig.php";

// Check if the user session exists
if (!isset($_SESSION['email'])) {
    echo "Session expired. Please sign up again.";
    exit();
}

$email = $_SESSION['email'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $otp_input = trim($_POST['otp']);

    // For testing, just check if OTP is not empty
    if (!empty($otp_input)) { 
        echo "success";  // ONLY echo "success"
        exit(); // Important: Stop further execution
    } else {
        echo "OTP is empty"; // ONLY echo error message
        exit();
    }
}

// Delete unverified accounts only if the latest OTP has expired 
// (This part can stay as it is)
$delete_unverified = "DELETE FROM users WHERE account_Status = 'Pending' AND otp_expiry < NOW()";
$connection->query($delete_unverified);

$connection->close();?>
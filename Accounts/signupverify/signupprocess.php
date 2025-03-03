<?php
session_start();
require_once "../../dbconfig.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["signup"])) {
    date_default_timezone_set('Asia/Manila');

    $firstName   = ucfirst(strtolower($_POST['fname']));
    $lastName    = ucfirst(strtolower($_POST['lname']));
    $email       = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Use password_hash()
    $address     = $_POST['address'];
    $phoneNumber = $_POST['phone']; 
    $created     = date("Y-m-d H:i:s");

    $fullname = $firstName . " " . $lastName;

    // Generate a secure OTP
    $otp = random_int(100000, 999999); // More secure OTP
    $otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

    // Check if email already exists (using prepared statements)
    $checkEmail = "SELECT * FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($checkEmail);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['signup_error'] = "The email you entered is already registered. Please use a different email.";
        header("Location: ../signuppage.php");
        exit();
    }
    $stmt->close();

    // Insert user and OTP data (using prepared statements)
    $insertAccount = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_Type, account_Status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'Client', 'Pending', ?, ?)";
    $stmt = $connection->prepare($insertAccount);
    $stmt->bind_param("sssssss", $firstName, $lastName, $email, $password, $address, $created, $created);

    if ($stmt->execute()) {
        $stmt->close();

        //insert the otp into the otp table
        $insertOTP = "INSERT INTO otp_verifications (email, otp, expiry_time) VALUES (?, ?, ?)";
        $stmt2 = $connection->prepare($insertOTP);
        $stmt2->bind_param("sis",$email,$otp,$otp_expiry);

        if($stmt2->execute()){
            $stmt2->close();

            include "setotp.php"; // Include your email sending function
            send_verification($fullname, $email, $otp);

            $_SESSION['signup_success'] = "Signup successful! A one-time password has been sent to your email. It will expire in 5 minutes.";
            $_SESSION['email'] = $email;
            header("Location: verify.php");
            exit();
        } else {
            $_SESSION['signup_error'] = "An error occurred during OTP storage. Please try again.";
            header("Location: ../signuppage.php");
            exit();
        }

    } else {
        $_SESSION['signup_error'] = "An error occurred during signup. Please try again.";
        header("Location: ../signuppage.php");
        exit();
    }

} else {
    header("Location: ../signuppage.php");
    exit();
}
?>

<?php
    session_start();
    require_once "../../dbconfig.php"; // Include your database connection file

    include "setotp.php"; // Include your OTP setting/sending function

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["signup"])) {
        date_default_timezone_set('Asia/Manila');

        $firstName = ucfirst(strtolower($_POST['fname']));
        $lastName = ucfirst(strtolower($_POST['lname']));
        $email = $_POST['email'];
        $password = md5($_POST['password']);
        $address = $_POST['address'];
        $phoneNumber = $_POST['phone'];
        $created = date("Y-m-d H:i:s");

        $fullname = $firstName . " " . $lastName;

        $otp = rand(000000, 999999);
        $otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

        $checkEmail = "SELECT * FROM users WHERE account_Email = '$email'";
        $result = $connection->query($checkEmail);  // $connection is now available

        if ($result->num_rows > 0) {
            $_SESSION['signup_error'] = "The email you entered is already registered. Please use a different email.";
            header("Location: ../signuppage.php"); // Corrected redirect path
            exit();
        } else {
            $insertAccount = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status, created_at, updated_at, otp, otp_expiry) 
                            VALUES ('$firstName', '$lastName', '$email', '$password', '$address', '$phoneNumber', 'Client', 'Pending', '$created', '$created', $otp, '$otp_expiry')";

            $insertResult = $connection->query($insertAccount); // Use $connection here

            $_SESSION['email'] = $email; // Store email in session

            if ($insertResult == TRUE) {
                send_verification($fullname, $email, $otp);

                $_SESSION['signup_success'] = "Signup successful! A one-time password has been sent to your email. It will expire in 5 minutes.";
                $_SESSION['email'] = $email; // Store the email in the session for verification
                header("Location: verify.php"); // Redirect to verification page (relative to signupprocess.php)
                exit();
            } else {
                $_SESSION['signup_error'] = "An error occurred during signup. Please try again.";
                header("Location: ../signuppage.php");
                exit();
            }
        }
    } else {
        header("Location: ../signuppage.php"); // Handle direct access to the file (optional)
        exit();
    }
?>
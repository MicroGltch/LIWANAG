<?php
    session_start();
    require_once "../../dbconfig.php";

    include "setotp.php";

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
        $result = $connection->query($checkEmail);  

        if ($result->num_rows > 0) {
            $_SESSION['signup_error'] = "The email you entered is already registered. Please use a different email.";
            header("Location: ../signuppage.php"); 
            exit();
        } else {
            $insertAccount = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status, created_at, updated_at, otp, otp_time, otp_expiry) 
                            VALUES ('$firstName', '$lastName', '$email', '$password', '$address', '$phoneNumber', 'Client', 'Pending', '$created', '$created', $otp,  '$created', '$otp_expiry')";

            $insertResult = $connection->query($insertAccount); 

            $_SESSION['email'] = $email; 

            if ($insertResult == TRUE) {
                send_verification($fullname, $email, $otp);

                $_SESSION['signup_success'] = "Signup successful! A one-time password has been sent to your email. It will expire in 5 minutes.";
                $_SESSION['email'] = $email;
                header("Location: verify.php"); 
                exit();
            } else {
                $_SESSION['signup_error'] = "An error occurred during signup. Please try again.";
                header("Location: ../signuppage.php");
                exit();
            }
        }
    } else {
        header("Location: ../signuppage.php"); 
        exit();
    }
?>
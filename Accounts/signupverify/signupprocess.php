<?php
session_start();
require_once "../../dbconfig.php";

// Include Twilio's PHP helper library via Composer's autoload
require __DIR__ . '/twilio-php-app/vendor/autoload.php';
use Twilio\Rest\Client;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["signup"])) {
    date_default_timezone_set('Asia/Manila');

    $firstName   = ucfirst(strtolower($_POST['fname']));
    $lastName    = ucfirst(strtolower($_POST['lname']));
    $email       = $_POST['email'];
    $password    = md5($_POST['password']);
    $address     = $_POST['address'];
    $phoneNumber = $_POST['phone']; // Ensure this is in E.164 format (e.g., "+639774458430")
    $created     = date("Y-m-d H:i:s");

    $fullname = $firstName . " " . $lastName;

    // Check if an active account already exists with this email.
    $checkActiveEmail = "SELECT * FROM users WHERE account_Email = ? AND account_Status = 'Active'";
    $stmt = $connection->prepare($checkActiveEmail);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultActiveEmail = $stmt->get_result();

    if ($resultActiveEmail->num_rows > 0) {
        $_SESSION['signup_error'] = "The email you entered is already registered. Please use a different email.";
        header("Location: ../signuppage.php");
        exit();
    }
    $stmt->close();

    // Check if an active account already exists with this phone number.
    $checkActivePhone = "SELECT * FROM users WHERE account_PNum = ? AND account_Status = 'Active'";
    $stmt = $connection->prepare($checkActivePhone);
    $stmt->bind_param("s", $phoneNumber);
    $stmt->execute();
    $resultActivePhone = $stmt->get_result();

    if ($resultActivePhone->num_rows > 0) {
        $_SESSION['signup_error'] = "The phone number you entered is already registered. Please use a different number.";
        header("Location: ../signuppage.php");
        exit();
    }
    $stmt->close();

    // Check if a pending account exists with this email.
    $checkPending = "SELECT * FROM users WHERE account_Email = ? AND account_Status = 'Pending'";
    $stmt = $connection->prepare($checkPending);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultPending = $stmt->get_result();

    if ($resultPending->num_rows > 0) {
        // Update the pending account with the new details.
        $updateAccount = "UPDATE users SET 
                             account_FName = ?, 
                             account_LName = ?, 
                             account_Password = ?, 
                             account_Address = ?, 
                             account_PNum = ?, 
                             updated_at = ?
                          WHERE account_Email = ? AND account_Status = 'Pending'";
        $stmt = $connection->prepare($updateAccount);
        $stmt->bind_param("sssssss", $firstName, $lastName, $password, $address, $phoneNumber, $created, $email);
        $updateResult = $stmt->execute();
        $stmt->close();

        if (!$updateResult) {
            $_SESSION['signup_error'] = "An error occurred updating your pending account. Please try again.";
            header("Location: ../signuppage.php");
            exit();
        }
    } else {
        // Insert a new pending account.
        $insertAccount = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, 'Client', 'Pending', ?, ?)";
        $stmt = $connection->prepare($insertAccount);
        $stmt->bind_param("ssssssss", $firstName, $lastName, $email, $password, $address, $phoneNumber, $created, $created);
        $insertResult = $stmt->execute();
        $stmt->close();

        if (!$insertResult) {
            $_SESSION['signup_error'] = "An error occurred during signup. Please try again.";
            header("Location: ../signuppage.php");
            exit();
        }
    }

    // Use Twilio Verify API to send OTP via SMS.
    $sid        = "[SID]";
    $token      = "[TOKEN]";
    $serviceSid = "[SERVICEID]"; // Your Verify Service SID
    $twilio     = new Client($sid, $token);

    try {
        // Initiate the verification via SMS.
        $verification = $twilio->verify->v2->services($serviceSid)
                               ->verifications
                               ->create($phoneNumber, "sms");

        $_SESSION['signup_success'] = "Signup successful! A one-time password has been sent to your phone.";
        $_SESSION['email'] = $email;
        header("Location: verify.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['signup_error'] = "Failed to send OTP: " . $e->getMessage();
        header("Location: ../signuppage.php");
        exit();
    }
} else {
    header("Location: ../signuppage.php");
    exit();
}
?>

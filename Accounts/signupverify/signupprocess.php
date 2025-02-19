<?php
session_start();
require_once "../../dbconfig.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["signup"])) {
    date_default_timezone_set('Asia/Manila');

    $firstName   = ucfirst(strtolower($_POST['fname']));
    $lastName    = ucfirst(strtolower($_POST['lname']));
    $email       = $_POST['email'];
    $password    = md5($_POST['password']);
    $address     = $_POST['address'];
    $phoneNumber = $_POST['phone']; 
    $created     = date("Y-m-d H:i:s");

    // Check if email or phone number is already registered
    $checkExisting = "SELECT * FROM users WHERE account_Email = ? OR account_PNum = ?";
    $stmt = $connection->prepare($checkExisting);
    $stmt->bind_param("ss", $email, $phoneNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['signup_error'] = "An account with this email or phone number already exists.";
        header("Location: ../signuppage.php");
        exit();
    }
    $stmt->close();

    // Insert user into `users` table with `account_Status = 'Pending'`
    $insertUser = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status, created_at, updated_at) 
                   VALUES (?, ?, ?, ?, ?, ?, 'Client', 'Pending', ?, ?)";
    $stmt = $connection->prepare($insertUser);
    $stmt->bind_param("ssssssss", $firstName, $lastName, $email, $password, $address, $phoneNumber, $created, $created);
    $stmt->execute();
    $stmt->close();

    // Store email & phone in session
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phoneNumber;

    // Redirect to OTP verification
    header("Location: verify.php");
    exit();
}
?>

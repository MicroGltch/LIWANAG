<?php
session_start();
include "config.php";

$message = ""; // Initialize an empty message variable

    $email = $_POST['email'];
    $password = $_POST['password'];

    $loginsql = "SELECT * FROM users WHERE account_Email = '$email' AND account_Password = '$password'";
    $loginresult = $conn->query($loginsql);

    // check if valid login
    if($loginresult->num_rows == 1){  
        $row = $loginresult->fetch_assoc();
        $accountType = $row['account_Type'];
        $accountId= $row['account_ID'];
        
        

        if ($accountType == 'Client') {
            $_SESSION['userId'] = $accountId;
            header("Location: hompage.php");
            exit(); // Make sure to exit after redirect
        // } else {
        //     $_SESSION['userId'] = $accountId; // Assuming you have an 'admin' session variable
        //     header("Location: AdminHomepage.php");
        //     exit();
         }  // pang iba't ibang types na
    } else {
        $message = "Invalid email or password!";
        header("Location: loginpage.php?loginError=" . urlencode($message)); // Pass the error message through URL
        exit();
    }

?>
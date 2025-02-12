<html><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></html>
<?php
session_start();
include "../dbconfig.php";

$message = ""; // Initialize an empty message variable

    $email = $_POST['email'];
    $password = md5($_POST['password']);

    $checkEmail = "SELECT * FROM users WHERE account_Email = '$email'";
        $checkResult = $connection->query($checkEmail);

        if (empty($_POST['email'])|| empty($_POST['password'])) {
           
            $message = "Incomplete Fields!";
            header(header: "Location: loginpage.php?loginError=" . urlencode($message)); // Pass the error message through URL
            exit();
        } else if ($checkResult->num_rows == 0) {

            $message = "Invalid email or password!";
            header(header: "Location: loginpage.php?loginError=" . urlencode($message)); // Pass the error message through URL
            exit();
        }

        $loginsql = "SELECT * FROM users WHERE account_Email = '$email' AND account_Password = '$password'";
        $loginresult = $connection->query($loginsql);

    // check if valid login
    
        if($loginresult->num_rows == 1){  
            $row = $loginresult->fetch_assoc();
            
            $fullname = $row['account_FName'] . " " . $row['account_LName'];
            $_SESSION['username'] = $fullname;
            echo "<script>window.location.href = '../homepage.php';</script>";
            exit();

        } else {
        $message = "Invalid email or password!";
        header(header: "Location: loginpage.php?loginError=" . urlencode($message)); // Pass the error message through URL
        exit();
    }

?>
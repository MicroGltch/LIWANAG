<?php
session_start();
include "../../dbconfig.php";

header('Content-Type: application/json');


if (isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = md5($_POST['password']);

    $checkEmail = "SELECT * FROM users WHERE account_Email = '$email'";
    $checkResult = $connection->query($checkEmail);

    if (empty($email) || empty($_POST['password'])) {
        echo json_encode(['sweetalert' => ["Incomplete Fields!", "Please complete the fields to login.", "error"]]);
        exit();
    } else if ($checkResult->num_rows == 0) {
        echo json_encode(['sweetalert' => ["Account not Found", "Account does not exist. Please sign up.", "error"]]);
        exit();
    } else {
        $statusCheckSql = "SELECT account_Status FROM users WHERE account_Email = '$email'";
        $statusResult = $connection->query($statusCheckSql);

        if ($statusResult && $row = $statusResult->fetch_assoc()) {
            $status = $row['account_Status'];

            if ($status == 'Active') {
                $loginsql = "SELECT * FROM users WHERE account_Email = '$email' AND account_Password = '$password'";
                $loginresult = $connection->query($loginsql);

                if ($loginresult && $loginresult->num_rows == 1) {
                    $row = $loginresult->fetch_assoc();
                    $fullname = $row['account_FName'] . " " . $row['account_LName'];
                    $_SESSION['username'] = $fullname;
                    echo json_encode(['redirect' => '../homepage.php']);
                    exit();
                } else {
                    echo json_encode(['sweetalert' => ["Invalid Password", "Please check your email or password.", "error"]]);
                    exit();
                }
            } elseif ($status == 'Pending') {
                echo json_encode(['sweetalert' => ["Pending Account", "Your account is pending verification. Please verify your account or sign up again.", "info"]]);
                exit();
            } else { // Other status (e.g., 'Suspended')
                echo json_encode(['sweetalert' => ["Account Status", "Your account is " . $status . ". Please contact support.", "warning"]]);
                exit();
            }
        } else {
            error_log("Error checking account status: " . $connection->error);
            echo json_encode(['sweetalert' => ["Error", "An error occurred. Please try again later.", "error"]]);
            exit();
        }
    }
} else {
    // Handle the case where the form is not submitted.  You might want to redirect.
    echo json_encode(['sweetalert' => ["Error", "No data received.", "error"]]);
    exit();
}
?>
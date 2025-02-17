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
        } elseif ($checkResult->num_rows == 0) {
            echo json_encode(['sweetalert' => ["Account not Found", "Account does not exist. Please sign up.", "error"]]);
            exit();
        } else { // Email found, check status
            $statusCheckSql = "SELECT account_Status, created_at FROM users WHERE account_Email = '$email'";
            $statusResult = $connection->query($statusCheckSql);

            // *** KEY CHANGE: Check query result AND row fetch ***
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
                    $created_at = new DateTime($row['created_at']);
                    $now = new DateTime();
                    $diff = $now->diff($created_at);
                    $days = $diff->days;
                    $hours = $diff->h;

                    if ($days < 1 || ($days == 0 && $hours < 24)) {
                        
                        $_SESSION['email'] = $email; 
                        $_SESSION['pending_login'] = true; // Set the flag

                        $isPendingAccount = true;  // Flag


                        echo json_encode([
                            'sweetalert' => [
                                "Pending Account",
                                "Your account is pending verification. Please verify your account to continue.",
                                "info",
                                '<a href="../Accounts/signupverify/verify.php" class="uk-button uk-button-primary">Verify Now</a>', // HTML content
                                $isPendingAccount // Add the flag to the JSON response
                            ]
                        ]);
                        exit();
                    } else {
                        $deleteSql = "DELETE FROM users WHERE account_Email = '$email'";
                        if ($connection->query($deleteSql)) {
                            echo json_encode(['sweetalert' => ["Account Expired", "Please sign up again.", "info"]]);
                            exit();
                        } else {
                            echo json_encode(['sweetalert' => ["Error", "Error deleting account.", "error"]]);
                            exit();
                        }
                    }
                } else { // Other status
                    error_log("Unknown account status: " . $status); // Log unknown status
                    echo json_encode(['sweetalert' => ["Error", "An error occurred. Please try again later.", "error"]]);
                    exit();
                }
            } else { // Query failed or no row fetched
                error_log("Error checking account status: " . $connection->error);
                echo json_encode(['sweetalert' => ["Error", "An error occurred. Please try again later.", "error"]]);
                exit();
            }
        }
    } else {
        echo json_encode(['sweetalert' => ["Error", "No data received.", "error"]]);
        exit();
    }
?>
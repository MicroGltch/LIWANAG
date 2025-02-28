<?php
session_start();
include "../../dbconfig.php";

header('Content-Type: application/json');

if (isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password']; // Do NOT hash it yet!

    // 🔍 Fetch user details (including stored password)
    $checkEmail = "SELECT account_ID, account_FName, account_LName, account_Status, account_PNum, created_at, account_Password, account_Type 
                   FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($checkEmail);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if (empty($email) || empty($_POST['password'])) {
        echo json_encode(['sweetalert' => ["Incomplete Fields!", "Please complete the fields to login.", "error"]]);
        exit();
    } elseif ($result->num_rows === 0) {
        echo json_encode(['sweetalert' => ["Account not Found", "Account does not exist. Please sign up.", "error"]]);
        exit();
    } else {
        $row = $result->fetch_assoc();
        $status = $row['account_Status'];
        $phone = $row['account_PNum'];
        $accountID = $row['account_ID'];
        $role = $row['account_Type']; // ✅ Use correct database column
        $storedPassword = $row['account_Password']; // Get stored password

        $created_at = new DateTime($row['created_at']);
        $now = new DateTime();
        $diff = $now->diff($created_at);
        $days = $diff->days;
        $hours = $diff->h;

        // 🔑 **PASSWORD CHECK (Use only password_verify)**
        if (!password_verify($password, $storedPassword)) {
            echo json_encode(['sweetalert' => ["Invalid Password", "Please check your email or password.", "error"]]);
            exit();
        }

        // ✅ **Store user details in session**
        $_SESSION['username'] = $row['account_FName'] . " " . $row['account_LName'];
        $_SESSION['account_ID'] = $accountID;
        $_SESSION['account_Type'] = $role; // ✅ Store as `account_Type`, matching database


        // ✅ **Handle Pending Accounts**
        if ($status === 'Pending') {
            if ($days < 1 || ($days === 0 && $hours < 24)) {
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;

                echo json_encode([
                    'sweetalert' => ["Pending Account", "Your account is pending verification. Please verify your phone number.", "info"],
                    'redirect' => '../signupverify/verify.php',
                    'pending' => true
                ]);
                exit();
            } else {
                // ❌ **DELETE EXPIRED ACCOUNT**
                $deleteSql = "DELETE FROM users WHERE account_Email = ?";
                $stmt = $connection->prepare($deleteSql);
                $stmt->bind_param("s", $email);

                if ($stmt->execute()) {
                    echo json_encode(['sweetalert' => ["Account Expired", "Your verification time has expired. Please sign up again.", "info"]]);
                } else {
                    echo json_encode(['sweetalert' => ["Error", "Error deleting expired account.", "error"]]);
                }
                $stmt->close();
                exit();
            }
        }

        // ✅ **Log in Regular Users**
        echo json_encode(['redirect' => 'dashboard/redirect.php']); // Redirect to dynamic dashboard
        exit();
    }
}
?>

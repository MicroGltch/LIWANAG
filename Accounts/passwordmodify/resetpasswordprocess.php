<?php
require_once "../../dbconfig.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    // Secure password hashing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Retrieve stored hashed token from DB
    $query = "SELECT s.account_ID, s.reset_token FROM security_tokens s
              INNER JOIN users u ON s.account_ID = u.account_ID
              WHERE s.reset_token_expiry > NOW() AND u.account_Email = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $accountID = $row['account_ID'];
        $storedHashedToken = $row['reset_token'];

        // Verify the token using password_verify()
        if (password_verify($token, $storedHashedToken)) {
            // Update password
            $update_sql = "UPDATE users SET account_Password = ? WHERE account_ID = ?";
            $stmt = $connection->prepare($update_sql);
            $stmt->bind_param("si", $hashed_password, $accountID);
            if ($stmt->execute()) {
                // Delete reset token after successful reset
                $delete_token_sql = "DELETE FROM security_tokens WHERE account_ID = ?";
                $stmt = $connection->prepare($delete_token_sql);
                $stmt->bind_param("i", $accountID);
                $stmt->execute();

                // Redirect to login page after success
                header("Location: ../loginpage.php?reset=success");
                exit;
            } else {
                die("Error updating password.");
            }
        } else {
            die("Invalid or expired reset token.");
        }
    } else {
        die("Invalid or expired reset token.");
    }
}
}
?>

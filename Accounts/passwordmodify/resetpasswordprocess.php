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

    // Check if token is valid and not expired
    $query = "SELECT s.account_ID FROM security_tokens s
              INNER JOIN users u ON s.account_ID = u.account_ID
              WHERE s.reset_token = ? AND s.reset_token_expiry > NOW() AND u.account_Email = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $accountID = $row['account_ID'];

        // Update password and reset login attempts
        $update_sql = "UPDATE users SET account_Password = ?, login_attempts = 0 WHERE account_ID = ?";
        $stmt = $connection->prepare($update_sql);
        $stmt->bind_param("si", $hashed_password, $accountID);
        if ($stmt->execute()) {
            // Delete the reset token after successful password update
            $delete_token_sql = "DELETE FROM security_tokens WHERE account_ID = ? AND reset_token IS NOT NULL";
            $stmt = $connection->prepare($delete_token_sql);
            $stmt->bind_param("i", $accountID);
            $stmt->execute();

            echo "Password successfully reset. <a href='../loginpage.php'>Login here</a>";
        } else {
            echo "Error updating password.";
        }
    } else {
        die("Invalid or expired reset token.");
    }
}
?>

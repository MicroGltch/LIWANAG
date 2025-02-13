<?php
require_once "../../dbconfig.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    $hashed_password = md5($password);

    // Update password and clear token
    $update_sql = "UPDATE users SET account_Password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE account_Email = ?";
    $stmt = $connection->prepare($update_sql);
    $stmt->bind_param("ss", $hashed_password, $email);
    if ($stmt->execute()) {
        echo "Password successfully reset. <a href='../loginpage.php'>Login here</a>";
    } else {
        echo "Error updating password.";
    }
}
?>

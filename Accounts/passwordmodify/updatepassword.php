<?php
// require_once "../../dbconfig.php";

// if ($_SERVER["REQUEST_METHOD"] == "POST") {
//     $token = $_POST['token'];
//     $new_password = $_POST['new_password'];
//     $confirm_password = $_POST['confirm_password'];

//     if ($new_password !== $confirm_password) {
//         die("Passwords do not match.");
//     }

//     // Hash the new password
//     $hashed_password = md5($new_password);

//     // Verify token
//     $stmt = $connection->prepare("SELECT account_Email FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
//     $stmt->bind_param("s", $token);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     if ($row = $result->fetch_assoc()) {
//         $email = $row['account_Email'];

//         // Update password and remove reset token
//         $stmt = $connection->prepare("UPDATE users SET account_Password = ?, reset_token = NULL, reset_expiry = NULL WHERE account_Email = ?");
//         $stmt->bind_param("ss", $hashed_password, $email);
//         if ($stmt->execute()) {
//             echo "Password reset successful! You can now <a href='login.php'>log in</a>.";
//         } else {
//             echo "Error updating password.";
//         }
//     } else {
//         die("Invalid or expired reset link.");
//     }

//     $stmt->close();
//     $connection->close();
// }
?>

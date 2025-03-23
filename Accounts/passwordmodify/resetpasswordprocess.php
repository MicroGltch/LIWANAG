<?php
require_once "../../dbconfig.php";
require __DIR__ . '/../signupverify/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        header("Location: resetpasswordpage.php?token=$token&email=$email&error=" . urlencode("Passwords do not match."));
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hash the new password

    $query = "SELECT s.account_ID, s.reset_token, u.account_Password FROM security_tokens s
              INNER JOIN users u ON s.account_ID = u.account_ID
              WHERE u.account_Email = ? AND s.reset_token_expiry > NOW()";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $accountID = $row['account_ID'];
        $storedHashedToken = $row['reset_token'];
        $existingPassword = $row['account_Password'];

        if (!password_verify($token, $storedHashedToken)) {
            header("Location: resetpasswordpage.php?token=$token&email=$email&error=" . urlencode("Invalid or expired reset token."));
            exit();
        }

        // Compare the hashed new password with the existing hashed password
        if (password_verify($password, $existingPassword)) {
            header("Location: resetpasswordpage.php?token=$token&email=$email&error=" . urlencode("Your new password must be different from your current password."));
            exit();
        }

        $update_sql = "UPDATE users SET account_Password = ?, login_attempts = 0, updated_at = NOW() WHERE account_ID = ?";
        $stmt = $connection->prepare($update_sql);
        $stmt->bind_param("si", $hashed_password, $accountID);

        if ($stmt->execute()) {
            $delete_token_sql = "DELETE FROM security_tokens WHERE account_ID = ?";
            $stmt = $connection->prepare($delete_token_sql);
            $stmt->bind_param("i", $accountID);
            $stmt->execute();

            // Email Change Password Success
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.hostinger.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'no-reply@myliwanag.com';
                $mail->Password = '[l/+1V/B4';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "Password Reset Success";

                $mail->Body = "Your password has been successfully reset.<br><br>
                               You can now log in with your new password.<br><br>
                               Best regards, <br> LIWANAG Team";

                $mail->send();

                // Redirect to success page
                header("Location: resetpasswordsuccess.php?status=success&message=" . urlencode("Password Reset Success, Please Login."));
                exit();

            } catch (Exception $e) {
                // Redirect to error page
                header("Location: resetpasswordsuccess.php?status=error&message=" . urlencode("Error sending email: " . $e->getMessage()));
                exit();
            }
        } else {
            // Redirect to error page
            header("Location: resetpasswordsuccess.php?status=error&message=" . urlencode("Error updating password."));
            exit();
        }
    } else {
        // Redirect to error page
        header("Location: resetpasswordsuccess.php?status=error&message=" . urlencode("Invalid or expired reset token."));
        exit();
    }
}
?>
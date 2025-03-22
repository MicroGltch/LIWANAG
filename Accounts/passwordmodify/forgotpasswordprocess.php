<?php
require_once "../../dbconfig.php";
require __DIR__ . '/../signupverify/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Check if email exists
    $query = "SELECT account_ID FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $accountID = $row['account_ID'];

        // Generate a secure token
        $token = bin2hex(random_bytes(50));
        $hashedToken = password_hash($token, PASSWORD_BCRYPT); // Hash the token
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes")); // Token valid for 15 minutes

        // Delete old token
        $delete_old_token = "DELETE FROM security_tokens WHERE account_ID = ?";
        $stmt = $connection->prepare($delete_old_token);
        $stmt->bind_param("i", $accountID);
        $stmt->execute();
        $stmt->close();

        // Store new hashed reset token
        $insert_token = "INSERT INTO security_tokens (account_ID, reset_token, reset_token_expiry) VALUES (?, ?, ?)";
        $stmt = $connection->prepare($insert_token);
        $stmt->bind_param("iss", $accountID, $hashedToken, $expiry);
        $stmt->execute();
        $stmt->close();

        // Send reset email
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
            $mail->Subject = "Password Reset Request";

            // Send the raw (non-hashed) token in the reset link
            $reset_link = "http://localhost/LIWANAG/Accounts/passwordmodify/resetpasswordpage.php?token=$token&email=$email";
            $mail->Body = "Hello,<br><br>Click the link below to reset your password:<br>
                           <a href='$reset_link'>Reset Password</a><br><br>
                           This link is valid for 15 minutes.<br><br>
                           If you didn't request this, you can ignore this email.<br><br>
                           Best regards, <br> LIWANAG Team";

            $mail->send();
            echo json_encode(["status" => "success", "message" => "Password reset link sent to your email."]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Error sending email."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Email not found."]);
    }
}
?>

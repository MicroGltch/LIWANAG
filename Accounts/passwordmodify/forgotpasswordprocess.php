<?php
require_once "../../dbconfig.php";
require __DIR__ . '/../signupverify/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Check if email exists
    $query = "SELECT * FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $token = bin2hex(random_bytes(50)); // Generate a secure token
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes")); // Token valid for 15 mins

        // Store token in database
        $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE account_Email = ?";
        $stmt = $connection->prepare($update_sql);
        $stmt->bind_param("sss", $token, $expiry, $email);
        $stmt->execute();

        // Send reset email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'danielpeig@gmail.com';
            $mail->Password = 'jilrihriaqqjwhwr';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('danielpeig@gmail.com', "Little Wanderer's Therapy Center");
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Password Reset Request";
            $reset_link = "http://localhost:3000/LIWANAG/Accounts/passwordmodify/resetpasswordpage.php?token=$token";
            $mail->Body = "Hello,<br><br>Click the link below to reset your password:<br>
                           <a href='$reset_link'>Reset Password Link</a><br><br>
                           This link is valid for 15 minutes.<br><br>
                           If you didn't request this, you can ignore this email.<br><br>
                            Best regards, <br> LIWANAG TeamLIWANAG Team";

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

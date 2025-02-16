<?php
require_once "../../dbconfig.php";
require __DIR__ . '/../signupverify/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $connection->prepare("SELECT account_FName FROM users WHERE account_Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $fullname = $row['account_FName'];

        // Generate token
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        // Store token in DB
        $stmt = $connection->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE account_Email = ?");
        $stmt->bind_param("sss", $token, $expiry, $email);
        $stmt->execute();

        // Send email with reset link
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
            $mail->Body = "Hello $fullname, <br><br>
                Click the link below to reset your password. This link will expire in 15 minutes:<br>
                <a href='http://localhost:3000/LIWANAG/Accounts/passwordmodify/resetpasswordpage.php?token=$token'>Reset Password</a><br><br>
                If you didn't request this, you can ignore this email.<br><br>
                Best regards, <br> LIWANAG Team";

            $mail->send();
            echo "A reset link has been sent to your email.";
        } catch (Exception $e) {
            echo "Failed to send email: " . $mail->ErrorInfo;
        }
    } else {
        echo "No account found with this email.";
    }

    $stmt->close();
    $connection->close();
}
?>

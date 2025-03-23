<?php
require __DIR__ . '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email verification function
function send_verification($fullname, $email, $otp) {
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
        $mail->addAddress($email, $fullname);
        $mail->isHTML(true);
        $mail->Subject = "Account Verification Code";
        $mail->Body = "Hello! ".$fullname." <br>
                    <br> Your One-Time Password (OTP) for account verification is:<br>
                    <h3>".$otp."</h3>
                    <br> This code is valid for 5 minutes. Please do not share this code with anyone.<br>
                    <br>If you did not make this request, please ignore this email.<br>
                    <br>Best regards,<br> LIWANAG Team";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
    }
}
?>
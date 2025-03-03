<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

function send_verification($fullname, $email, $otp){
    $mail = new PHPMailer(true);                              // Passing true enables exceptions
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com'; // Use Hostinger's SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@myliwanag.com'; // Use the working email
        $mail->Password = '[l/+1V/B4'; // Use the working password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use the working encryption
        $mail->Port = 465; // Use the working port
    
        //Recipients
        $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
        $mail->addAddress($email, $fullname);
        //Content
        $mail->isHTML(true);  // Set email format to HTML
        $mail->Subject = "Account Verification Code";
        $mail->Body    = "Hello! ".$fullname." <br>
                        <br> Your One-Time Password (OTP) for account verification is:<br>
                        <h3>".$otp."</h3>
                        <br> This code is valid for 5 minutes. Please do not share this code with anyone.<br>
                        <br>If you did not make this request, please ignore this email.<br>
                        <br>Best regards,<br> LIWANAG Team";
        $mail->send();
    } catch (Exception $e) {
        echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
    }

}
?>
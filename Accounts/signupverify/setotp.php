<?php
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

function send_verification($fullname, $email, $otp){
    $mail = new PHPMailer(true);                              // Passing true enables exceptions
    try {

        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = 'danielpeig@gmail.com';                 // SMTP username
        $mail->Password = 'jilrihriaqqjwhwr';                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, ssl also accepted
        $mail->Port = 587;                                    // TCP port to connect to
    
        //Recipients
        $mail->setFrom('danielpeig@gmail.com', "Little Wanderer's Therapy Center");
        $mail->addAddress( $email);     // Add a recipient
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

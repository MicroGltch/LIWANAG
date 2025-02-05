<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor\autoload.php';

function send_verification($fullname, $email, $otp){
    $mail = new PHPMailer(true);                              // Passing true enables exceptions
    try {
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = 'lancebagsit@gmail.com';                 // SMTP username
        $mail->Password = 'lzcmndobxefuzhhp';                           // SMTP password //Note: Remoce Spaces
        $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, ssl also accepted
        $mail->Port = 587;                                    // TCP port to connect to
    
        //Recipients
        $mail->setFrom( $email, 'LIWANAG'); //'email na pagsesendan','Pangalan ng Company/Website' lagyan ng \ pag gusto may ' sa loob ng ' '
        $mail->addAddress( $email);     // Add a recipient
        //Content
        $mail->isHTML(true);  // Set email format to HTML
        $mail->Subject = "Registration OTP Verification"; //Title/Subject of Email
        $mail->Body    = "<h1>Hello <i>".$fullname."</i></h1>
        This is your account verification code: <font color=red> ".$otp; // paayos nalang po ng design :))

        $mail->send();
        ?>
            <script>
                alert("Email Successfully Send!!")
            </script>
        <?php
    } catch (Exception $e) {
        echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
    }



}


?>
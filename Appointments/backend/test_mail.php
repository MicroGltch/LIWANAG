<?php
require_once "../../Accounts/signupverify/vendor/autoload.php"; 

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com'; // Change to your SMTP provider
    $mail->SMTPAuth = true;
    $mail->Username = 'no-reply@myliwanag.com'; // Your email
    $mail->Password = '[l/+1V/B4'; // Your email password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use PHPMailer::ENCRYPTION_STARTTLS for port 587
    $mail->Port       = 465; 

    // Email content
    $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center"); 
    $mail->addAddress('raphaelgabrielgeronimo@gmail.com', 'Test Receiver');
    $mail->Subject = 'Test Email from Hostinger';
    $mail->Body    = 'If you receive this email, SMTP is working!';

    if ($mail->send()) {
        echo '✅ Test email sent successfully!';
    }
} catch (Exception $e) {
    echo "❌ Email sending failed. Error: {$mail->ErrorInfo}";
}
?>

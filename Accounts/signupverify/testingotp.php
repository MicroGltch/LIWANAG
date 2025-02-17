<?php
// twilio_sms.php

// Include Twilio's PHP helper library using Composer's autoload.
require __DIR__ . '/twilio-php-app/vendor/autoload.php';

use Twilio\Rest\Client;

/**
 * Sends an OTP via SMS using Twilio.
 *
 * @param string $phone The recipient's phone number in E.164 format (e.g. "+639774458430").
 * @param string $otp   The OTP code to send.
 * @return string       The Twilio Message SID.
 */
function send_sms_verification($phone, $otp) {
    $sid    = "AC1e59afe3546d1c8e663ba8ff00e006fa";
    $token  = "d7775e6d8d7b463120c6343fdbd45f00";
    $twilio = new Client($sid, $token);
    
    // Replace with your purchased Twilio phone number (in E.164 format).
    $fromNumber = "+YourTwilioNumber"; 
    
    $message = $twilio->messages
                      ->create($phone, // the destination phone number
                               array(
                                   "body" => "Your One-Time Password (OTP) is: " . $otp,
                                   "from" => $fromNumber
                               )
                      );
    return $message->sid;
}
?>

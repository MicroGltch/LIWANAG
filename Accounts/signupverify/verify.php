<?php
session_start();
require_once "../../dbconfig.php";

// Check if session data exists
if (!isset($_SESSION['email']) || !isset($_SESSION['phone'])) {
    $_SESSION['otp_error'] = "Session expired. Please sign up again.";
    header("Location: ../signuppage.php");
    exit();
}

$email = $_SESSION['email'];
$phoneNumber = $_SESSION['phone'];

// ✅ Ensure phone number is in E.164 format (must start with `+`)
if (substr($phoneNumber, 0, 1) !== '+') {
    $phoneNumber = '+63' . ltrim($phoneNumber, '0'); // Remove leading 0 and add +63
}

// Twilio API credentials
require __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;

$sid        = "";
$token      = "";
$serviceSid = "";
$twilio     = new Client($sid, $token);

try {
    $verification = $twilio->verify->v2->services($serviceSid)
                           ->verifications
                           ->create($phoneNumber, "sms");

    $_SESSION['otp_sent'] = "An OTP has been sent to your phone.";
} catch (Exception $e) {
    $_SESSION['otp_error'] = "Failed to send OTP: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width" />
    <title>LIWANAG - VERIFY ACCOUNT</title>
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css"/>
</head>
<body>
    <nav class="uk-navbar-container">
        <div class="uk-container">
            <div uk-navbar>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo">Little Wanderer's Therapy Center</a>
                </div>
            </div>
        </div>
    </nav>
    <div class="uk-width-1@s uk-width-1@l">
        <hr>
    </div>
    <div class="uk-flex uk-flex-center uk-flex-middle uk-height-viewport">
        <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
            <h3 class="uk-card-title uk-flex uk-flex-center">Verify your Phone Number</h3>
            <p class="uk-flex uk-flex-center">Please input the One-Time Password (OTP) sent to your phone</p>

            <!-- ✅ Show error messages -->
            <?php if (isset($_SESSION['otp_error'])): ?>
                <p style="color: red;"><?php echo $_SESSION['otp_error']; unset($_SESSION['otp_error']); ?></p>
            <?php endif; ?>

            <form id="otp-form" class="uk-form-stacked uk-grid-medium" uk-grid action="otpverify.php" method="POST"> 
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="otp-input">OTP Verification.<br>If you don't receive the SMS, please request a new OTP.</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="otp-input" type="text" name="otp">
                        <span class="error" id="otp-error" style="color: red;"></span>
                    </div>
                </div>
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="verify" class="uk-button uk-button-primary uk-width-1@s">Verify</button>
                </div>
            </form>

            <div class="uk-margin">
                <button id="resend-otp" class="uk-button uk-button-secondary uk-width-1@s">Resend OTP</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../accountJS/otp.js"></script>
</body>
</html>

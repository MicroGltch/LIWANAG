<?php
session_start();
require_once "../../dbconfig.php";

// Include Twilio's PHP helper library via Composer's autoload
require __DIR__ . '/twilio-php-app/vendor/autoload.php';
use Twilio\Rest\Client;

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['email'])) {
    echo "Session expired. Please sign up again.";
    exit();
}

$email = $_SESSION['email'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $otp_input = trim($_POST['otp']);

    // Retrieve user's phone number and account_ID from the database.
    $sql = "SELECT account_ID, account_PNum FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        echo "Account not found.";
        exit();
    }
    
    $row = $result->fetch_assoc();
    $phoneNumber = $row['account_PNum'];
    $accountID   = $row['account_ID'];
    $stmt->close();

    // Ensure the phone number is in E.164 format (must start with '+').
    if (substr($phoneNumber, 0, 1) !== '+') {
        $phoneNumber = '+' . $phoneNumber;
    }

    // Check the OTP using Twilio Verify API.
    $sid        = "[SID]";
    $token      = "[TOKEN]";
    $serviceSid = "[SERVICEID]"; 
    $twilio     = new Client($sid, $token);

    try {
        $verification_check = $twilio->verify->v2->services($serviceSid)
                                        ->verificationChecks
                                        ->create([
                                            "to"   => $phoneNumber,
                                            "code" => $otp_input
                                        ]);

        if ($verification_check->status === "approved") {
            // OTP verified—activate the account.
            $updateSql = "UPDATE users SET account_Status = 'Active' WHERE account_ID = ?";
            $stmt = $connection->prepare($updateSql);
            $stmt->bind_param("i", $accountID);
            $stmt->execute();
            $stmt->close();

            session_unset();
            session_destroy();
            echo "success";
            exit();
        } else {
            echo "Incorrect OTP or OTP expired. Please request a new OTP.";
            exit();
        }
    } catch (Exception $e) {
        echo "Error verifying OTP: " . $e->getMessage();
        exit();
    }
}

$connection->close();
?>

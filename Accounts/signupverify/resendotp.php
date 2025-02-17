<?php
session_start();
require_once "../../dbconfig.php";

// Include Twilio's PHP helper library via Composer's autoload
require __DIR__ . '/twilio-php-app/vendor/autoload.php';
use Twilio\Rest\Client;

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['email'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please sign up again."]);
    exit();
}

$email = $_SESSION['email'];

// Retrieve user's phone number and account status from the database.
$getUserSql = "SELECT account_ID, account_PNum, account_Status FROM users WHERE account_Email = ?";
$stmt = $connection->prepare($getUserSql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    // If account is already active, no need to resend OTP.
    if ($row['account_Status'] === 'Active') {
        echo json_encode(["status" => "success", "message" => "Account is already active."]);
        exit();
    }
    $phoneNumber = $row['account_PNum'];
} else {
    echo json_encode(["status" => "error", "message" => "Error retrieving account details."]);
    exit();
}
$stmt->close();

// Ensure the phone number is in E.164 format (starts with '+')
if (substr($phoneNumber, 0, 1) !== '+') {
    $phoneNumber = '+' . $phoneNumber;
}

// Initiate a new verification request via Twilio Verify API.
$sid        = "[SID]";
$token      = "[TOKEN]";
$serviceSid = "[SERVICEID]"; 
$twilio     = new Client($sid, $token);

try {
    $verification = $twilio->verify->v2->services($serviceSid)
                            ->verifications
                            ->create($phoneNumber, "sms");
    echo json_encode(["status" => "success", "message" => "A new OTP has been sent to your phone."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to resend OTP. " . $e->getMessage()]);
}

$connection->close();
?>

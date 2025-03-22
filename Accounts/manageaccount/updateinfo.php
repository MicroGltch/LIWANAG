<?php
require_once "../../dbconfig.php";
require_once "../../Accounts/signupverify/vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();
//debugging
error_reporting(E_ERROR);
ini_set('display_errors', 0);
header('Content-Type: application/json');
error_log("POST data received: " . print_r($_POST, true));
$userid = $_SESSION['account_ID'];

header('Content-Type: application/json');
$errors = [];
$_SESSION['update_errors'] = [];
$_SESSION['update_success'] = "";

// Fetch user account type
$stmt = $connection->prepare("SELECT account_Type, account_Password, account_Email FROM users WHERE account_ID = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($account_Type, $account_Password, $currentEmail);
$stmt->fetch();
$stmt->close();


//Change Password
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    // Simplified variable access - use only underscore version for consistency
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Basic debug logging - don't log actual passwords in production
    error_log("Password change attempt received");
    
    $userid = $_SESSION['account_ID'] ?? null; // Retrieve user ID from session
    
    if (!$userid) {
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }

    // Fetch current password hash from DB
    $stmt = $connection->prepare("SELECT account_Password FROM users WHERE account_ID = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Database error: preparing statement failed.']);
        exit();
    }
    $stmt->bind_param("i", $userid);
    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Database error: executing statement failed.']);
        exit();
    }
    $stmt->bind_result($hashedPassword);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'User not found or database error.']);
        exit();
    }
    $stmt->close();

    // For debugging only - use in development, remove in production
    file_put_contents('password_debug.log', 
        "Received parameters: " . print_r($_POST, true) . "\n" .
        "Current password entered: " . $currentPassword . "\n" .
        "Stored hash: " . $hashedPassword . "\n" .
        "Verification result: " . (password_verify($currentPassword, $hashedPassword) ? "true" : "false") . "\n",
        FILE_APPEND);

  // Validate current password with plain text against stored hash
  if (!password_verify($currentPassword, $hashedPassword)) {
    echo json_encode(['error' => 'Current password is incorrect']);
    exit();
}

// Validate new password
if ($newPassword !== $confirmPassword) {
    echo json_encode(['error' => 'New passwords do not match']);
    exit();
}

if (strlen($newPassword) < 8) {
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit();
}

if (!preg_match('/[A-Z]/', $newPassword)) {
    echo json_encode(['error' => 'Password must contain at least one uppercase letter']);
    exit();
}

if (!preg_match('/[a-z]/', $newPassword)) {
    echo json_encode(['error' => 'Password must contain at least one lowercase letter']);
    exit();
}

if (!preg_match('/[0-9]/', $newPassword)) {
    echo json_encode(['error' => 'Password must contain at least one number']);
    exit();
}

if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
    echo json_encode(['error' => 'Password must contain at least one special character']);
    exit();
}

// Hash new password and update
$hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $connection->prepare("UPDATE users SET account_Password = ? WHERE account_ID = ?");
if (!$stmt) {
    echo json_encode(['error' => 'Database error: preparing update statement failed.']);
    exit();
}
$stmt->bind_param("si", $hashedNewPassword, $userid);

if ($stmt->execute()) {
    echo json_encode(['success' => 'Password updated successfully']);
} else {
    echo json_encode(['error' => 'Failed to update password. Database error.']);
}
$stmt->close();
exit();
}
///////

// Function to get the correct dashboard URL
function getDashboardURL($account_Type) {
    switch ($account_Type) {
        case 'admin':
            return "../../Dashboards/admindashboard.php";
        case 'therapist':
            return "../../Dashboards/therapistdashboard.php";
        case 'client':
        default:
            return "../../Dashboards/clientdashboard.php";
    }
}


$dashboardURL = getDashboardURL($account_Type);

function getDashboardURLSettings($account_Type) {
    switch ($account_Type) {
        case 'admin':
            return "../../Dashboards/admindashboard.php#settings";
        case 'therapist':
            return "../../Dashboards/therapistdashboard.php#settings";
        case 'client':
        default:
            return "../../Dashboards/clientdashboard.php#settings";
    }
}


$dashboardURLSettings = getDashboardURLSettings($account_Type);

// ** Upload Profile Picture **
if (isset($_POST['action']) && $_POST['action'] === 'upload_profile_picture' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    $filename = uniqid() . '_' . basename($file['name']);


    // Define upload path
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/LIWANAG/uploads/profile_pictures/";
    $destination = $uploadDir . $filename;


    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    chmod($uploadDir, 0777);


    // Validate file
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));


    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(["success" => false, "error" => "Invalid file type."]);
        exit;
    }


    if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
        echo json_encode(["success" => false, "error" => "File size must be < 2MB."]);
        exit;
    }


    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $stmt = $connection->prepare("UPDATE users SET profile_picture = ? WHERE account_ID = ?");
        $stmt->bind_param("si", $filename, $userid);
        $stmt->execute();
        $stmt->close();


        echo json_encode(["success" => true, "imagePath" => "/LIWANAG/uploads/profile_pictures/" . $filename]);
    } else {
        echo json_encode(["success" => false, "error" => "File upload failed."]);
    }
    exit;
}


// ** Remove Profile Picture **
$data = json_decode(file_get_contents("php://input"), true);
if (isset($data['action']) && $data['action'] === 'remove_profile_picture') {
    // Fetch existing profile picture
    $stmt = $connection->prepare("SELECT profile_picture FROM users WHERE account_ID = ?");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();


    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $profilePicture = $userData['profile_picture'];


        // Delete file if it exists
        if ($profilePicture && file_exists($_SERVER['DOCUMENT_ROOT'] . "/LIWANAG/uploads/profile_pictures/" . $profilePicture)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . "/LIWANAG/uploads/profile_pictures/" . $profilePicture);
        }


        // Reset profile picture to NULL in the database
        $stmt = $connection->prepare("UPDATE users SET profile_picture = NULL WHERE account_ID = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $stmt->close();


        echo json_encode(["success" => true, "imagePath" => "../CSS/default.jpg"]);
    } else {
        echo json_encode(["success" => false, "error" => "User not found."]);
    }
    exit;
}


// ** Update User Details **
if (isset($_POST['action']) && $_POST['action'] === 'update_user_details') {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $phoneNumber = trim($_POST['phoneNumber']);

    // ** Validate First Name **
    if (!preg_match("/^[A-Za-z ]{2,30}$/", $firstName)) {
        $_SESSION['update_errors']['firstName'] = "Only letters allowed (2-30 characters).";
    }

    // ** Validate Last Name **
    if (!preg_match("/^[A-Za-z ]{2,30}$/", $lastName)) {
        $_SESSION['update_errors']['lastName'] = "Only letters allowed (2-30 characters).";
    }

    // ** Validate Email **
    if (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
        $_SESSION['update_errors']['email'] = "Invalid email format.";
    }

    // ** Validate Mobile Number (Auto-convert format) **
    $phoneNumber = preg_replace('/\s+/', '', $phoneNumber); // Remove spaces

    if (!preg_match("/^09\d{9}$/", $phoneNumber)) {  
        $_SESSION['update_errors']['phoneNumber'] = "Phone number must be in the format 09XXXXXXXXX.";  
    } else {
        $phoneNumber = (int) $phoneNumber; // Convert to integer (removes leading 0 when stored)
    }

    // ** Store in Session to Preserve Data on Reload **
    if (!isset($_SESSION['update_errors']['phoneNumber'])) {
        $_SESSION['phoneNumber'] = $phoneNumber;
    }

    // ** Check if email or phone number already exists (excluding current user) **
    $stmt = $connection->prepare("SELECT account_ID FROM users WHERE (account_Email = ? OR account_PNum = ?) AND account_ID != ?");
    $stmt->bind_param("ssi", $email, $phoneNumber, $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['update_errors']['duplicate'] = "An account with this email or phone number already exists.";
    }
    $stmt->close();

    // ** If there are errors, return JSON response instead of redirecting **
    if (!empty($_SESSION['update_errors'])) {
        echo json_encode(['errors' => $_SESSION['update_errors']]);
        exit();
    }

    // Check if the email was changed
    if ($email !== $currentEmail) {
        $otp = rand(100000, 999999);
        $_SESSION['email_otp'] = password_hash($otp, PASSWORD_DEFAULT); // Securely store OTP
        $_SESSION['new_email'] = $email;
    
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'no-reply@myliwanag.com';
            $mail->Password = '[l/+1V/B4'; // Store this securely, e.g., in an env file
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
    
            $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
            $mail->addAddress($email);
            $mail->Subject = 'Email Verification Code';
            $mail->Body = "Your OTP code is: $otp";
    
            $mail->send();
            echo json_encode(['otp_required' => true]);
        } catch (Exception $e) {
            error_log("OTP email error: " . $mail->ErrorInfo); // Log error instead of exposing it
            echo json_encode(['error' => "Failed to send OTP. Please try again later."]);
        }
        exit();
    } else {
        // If email wasn't changed, update other user details directly
        $stmt = $connection->prepare("UPDATE users SET account_FName = ?, account_LName = ?, account_PNum = ? WHERE account_ID = ?");
        $stmt->bind_param("ssii", $firstName, $lastName, $phoneNumber, $userid);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => 'Profile updated successfully']);
        } else {
            echo json_encode(['error' => 'Failed to update profile']);
        }
        $stmt->close();
        exit();
    }
}

// ** Verify OTP and update email - MOVED OUTSIDE THE PREVIOUS CONDITIONAL BLOCK **
if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $enteredOtp = $_POST['otp'] ?? '';

    error_log("Entered OTP: " . $enteredOtp);
    error_log("email_otp: " . $_SESSION['email_otp']);
    error_log("new_email: " . $_SESSION['new_email']);

    if (!isset($_SESSION['email_otp']) || !isset($_SESSION['new_email'])) {
        error_log("Session expired.");
        $response = ['error' => 'Session expired. Please request a new OTP.'];
        error_log("Sending JSON response: " . json_encode($response));
        echo json_encode($response);
        error_log("JSON response sent.");
        exit();
    }

    if (password_verify($enteredOtp, $_SESSION['email_otp'])) {
        $newEmail = $_SESSION['new_email'];
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $phoneNumber = trim($_POST['phoneNumber'] ?? '');
        
        // Convert phone number if provided
        if (!empty($phoneNumber)) {
            $phoneNumber = preg_replace('/\s+/', '', $phoneNumber);
            $phoneNumber = (int) $phoneNumber;
        }

        // Update user details with new email and other fields if provided
        if (!empty($firstName) && !empty($lastName) && !empty($phoneNumber)) {
            $stmt = $connection->prepare("UPDATE users SET account_Email = ?, account_FName = ?, account_LName = ?, account_PNum = ? WHERE account_ID = ?");
            $stmt->bind_param("sssii", $newEmail, $firstName, $lastName, $phoneNumber, $userid);
        } else {
            // Update just the email if other fields weren't provided
            $stmt = $connection->prepare("UPDATE users SET account_Email = ? WHERE account_ID = ?");
            $stmt->bind_param("si", $newEmail, $userid);
        }

        if ($stmt->execute()) {
            unset($_SESSION['email_otp'], $_SESSION['new_email']);
            error_log("Email updated successfully.");
            $response = ['success' => 'Email updated successfully!'];
            error_log("Sending JSON response: " . json_encode($response));
            echo json_encode($response);
            error_log("JSON response sent.");
        } else {
            error_log("Failed to update email: " . $stmt->error);
            $response = ['error' => 'Failed to update email. Please try again.'];
            error_log("Sending JSON response: " . json_encode($response));
            echo json_encode($response);
            error_log("JSON response sent.");
        }

        $stmt->close();
    } else {
        error_log("Incorrect OTP.");
        $response = ['error' => 'Incorrect OTP.'];
        error_log("Sending JSON response: " . json_encode($response));
        echo json_encode($response);
        error_log("JSON response sent.");
    }
    exit();
}
echo json_encode(['success' => 'Test successful']);

?>
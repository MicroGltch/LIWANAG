<?php
include "../../dbconfig.php";
session_start();
$userid = $_SESSION['account_ID'];

header('Content-Type: application/json');
$errors = [];
$_SESSION['update_errors'] = []; // Clear previous errors
$_SESSION['update_success'] = "";

// Fetch user account type
$stmt = $connection->prepare("SELECT account_Type FROM users WHERE account_ID = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($account_Type);
$stmt->fetch();
$stmt->close();

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

// Profile Picture Upload
if (isset($_POST['action']) && $_POST['action'] === 'upload_profile_picture' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    $filename = uniqid() . '_' . basename($file['name']);
    $destination = "../../images/profilepictures/" . $filename; // Corrected path

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $stmt = $connection->prepare("UPDATE users SET profile_picture = ? WHERE account_ID = ?");
        $stmt->bind_param("si", $filename, $userid);
        $stmt->execute();
        $stmt->close();
        // Success message can be added here.
    } else {
        // Error handling can be added here.
    }
    header("Location: ../../Dashboards/clientdashboard.php");
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

    if (preg_match("/^0\d{10}$/", $phoneNumber)) {
        $phoneNumber = "+63" . substr($phoneNumber, 1);
    } elseif (!preg_match("/^\+63\d{10}$/", $phoneNumber)) {
        $_SESSION['update_errors']['phoneNumber'] = "Phone number must be in the format +63XXXXXXXXXX.";
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

    // ** Update User Data if no errors **
    $stmt = $connection->prepare("UPDATE users SET account_FName = ?, account_LName = ?, account_Email = ?, account_PNum = ? WHERE account_ID = ?");
    $stmt->bind_param("ssssi", $firstName, $lastName, $email, $phoneNumber, $userid);
    $stmt->execute();
    $stmt->close();

    // ** Set Success Message **
    $_SESSION['update_success'] = "Profile updated successfully!";
    echo json_encode(['success' => $_SESSION['update_success']]); 
    exit();
    
}
?>
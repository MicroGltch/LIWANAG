<?php
include "../../dbconfig.php";
session_start();
$userid = $_SESSION['account_ID'];

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

// Update User Details
if (isset($_POST['action']) && $_POST['action'] === 'update_user_details') {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];
    $phoneNumber = $_POST['phoneNumber'];

    $stmt = $connection->prepare("UPDATE users SET account_FName = ?, account_LName = ?, account_Email = ?, account_PNum = ? WHERE account_ID = ?");
    $stmt->bind_param("ssssi", $firstName, $lastName, $email, $phoneNumber, $userid); // Corrected bind_param
    $stmt->execute();
    $stmt->close();
    
    // Check if email or phone number is already registered
    $checkExisting = "SELECT * FROM users WHERE account_Email = ? OR account_PNum = ?";
    $stmt = $connection->prepare($checkExisting);
    $stmt->bind_param("ss", $email, $phoneNumber);
    $stmt->execute();
    $result = $stmt->get_result();


    if ($result->num_rows > 0) {
        $_SESSION['signup_error'] = "An account with this email or phone number already exists.";
        header("Location: $dashboardURLSettings");
        exit();
    }
    $stmt->close();
    header("Location: $dashboardURL");
    exit();

}
?>
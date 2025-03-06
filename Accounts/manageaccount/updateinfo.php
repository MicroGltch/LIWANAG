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

// ** Upload Profile Picture **
if (isset($_POST['action']) && $_POST['action'] === 'upload_profile_picture' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    $filename = uniqid() . '_' . basename($file['name']);


    // Define upload path
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/LIWANAG/LIWANAG/uploads/profile_pictures/";
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


        echo json_encode(["success" => true, "imagePath" => "/LIWANAG/LIWANAG/uploads/profile_pictures/" . $filename]);
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
        if ($profilePicture && file_exists($_SERVER['DOCUMENT_ROOT'] . "/LIWANAG/LIWANAG/uploads/profile_pictures/" . $profilePicture)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . "/LIWANAG/LIWANAG/uploads/profile_pictures/" . $profilePicture);
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
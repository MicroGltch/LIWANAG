<?php
include "../../dbconfig.php";
session_start();
$userid = $_SESSION['account_ID'];

// Profile Picture Upload
if (isset($_POST['action']) && $_POST['action'] === 'upload_profile_picture' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    $filename = uniqid() . '_' . $file['name'];
    $destination = '../images/profilepictures/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $stmt = $connection->prepare("UPDATE users SET profile_picture = ? WHERE account_ID = ?");
        $stmt->bind_param("ss", $filename, $userid);
        $stmt->execute();
        $stmt->close();
        //Success message.
    } else {
        //error message.
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
    $stmt->bind_param("sssss", $firstName, $lastName, $email, $phoneNumber, $userid); // Corrected bind_param
    $stmt->execute();
    $stmt->close();
    header("Location: ../../Dashboards/clientdashboard.php"); // Corrected redirect
    exit;
}
?>
<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

$patient_id = $_POST['patient_id'];
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$age = $_POST['age'];
$gender = $_POST['gender'];
$existing_picture = $_POST['existing_profile_picture']; // Get the existing profile picture

$target_dir = "../../uploads/profile_pictures/";
$new_file_name = $existing_picture; // Default to existing picture

// ✅ If a new profile picture is uploaded, process it
if (!empty($_FILES['profile_picture']['name'])) {
    $file_name = $_FILES['profile_picture']['name'];
    $file_tmp = $_FILES['profile_picture']['tmp_name'];
    $file_size = $_FILES['profile_picture']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_types = ["jpg", "jpeg", "png"];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file_ext, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
        header("Location: ../frontend/edit_patient_form.php");
        exit();
    }

    if ($file_size > $max_file_size) {
        $_SESSION['error'] = "File is too large. Maximum allowed size is 5MB.";
        header("Location: ../frontend/edit_patient_form.php");
        exit();
    }

    // ✅ Generate a unique filename and save it
    $new_file_name = uniqid() . "." . $file_ext;
    $target_file = $target_dir . $new_file_name;
    
    if (!move_uploaded_file($file_tmp, $target_file)) {
        $_SESSION['error'] = "Failed to upload profile picture.";
        header("Location: ../frontend/edit_patient_form.php");
        exit();
    }
}

// ✅ Update patient details in the database
$query = "UPDATE patients SET first_name = ?, last_name = ?, age = ?, gender = ?, profile_picture = ? WHERE patient_id = ? AND account_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ssissii", $first_name, $last_name, $age, $gender, $new_file_name, $patient_id, $_SESSION['account_ID']);

if ($stmt->execute()) {
    $_SESSION['success'] = "Patient details updated successfully!";
} else {
    $_SESSION['error'] = "Error updating patient details.";
}

$stmt->close();
header("Location: edit_patient_form.php");
exit();
?>

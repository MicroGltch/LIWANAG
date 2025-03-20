<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../../Accounts/loginpage.php");
    exit();
}

// Retrieve form data
$patient_id = $_POST['patient_id'];
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$bday = $_POST['bday'];
$gender = $_POST['gender'];
$existing_picture = $_POST['existing_profile_picture'];

var_dump($_POST['bday']); // Debugging

$target_dir = "../../../uploads/profile_pictures/";
$new_file_name = $existing_picture;

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
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patientss"); // Redirect to the dashboard
        exit();
    }

    if ($file_size > $max_file_size) {
        $_SESSION['error'] = "File is too large. Maximum allowed size is 5MB.";
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
        exit();
    }

    // ✅ Generate a unique filename and save it
    $new_file_name = uniqid() . "." . $file_ext;
    $target_file = $target_dir . $new_file_name;

    if (!move_uploaded_file($file_tmp, $target_file)) {
        $_SESSION['error'] = "Failed to upload profile picture.";
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
        exit();
    } 
}

if (!empty($_POST['bday'])) {
    $date = DateTime::createFromFormat('Y-m-d', $_POST['bday']);
    if ($date) {
        $bday = $date->format('Y-m-d');
    } else {
        $_SESSION['error'] = "Invalid date format.";
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
        exit();
    }
} else {
    $bday = null;
}

// Update patient details in the database
$query = "UPDATE patients SET first_name = ?, last_name = ?, bday = ?, gender = ?, profile_picture = ? WHERE patient_id = ? AND account_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ssissii", $first_name, $last_name, $bday, $gender, $new_file_name, $patient_id, $_SESSION['account_ID']);

// Log the query parameters
error_log("Update Query Parameters: first_name=" . $first_name . ", last_name=" . $last_name . ", bday=" . ($bday !== null ? $bday : 'NULL') . ", gender=" . $gender . ", profile_picture=" . $new_file_name . ", patient_id=" . $patient_id . ", account_id=" . $_SESSION['account_ID']);

if(!$stmt){
    error_log("Prepare failed: ". $connection->error);
}
error_log("Bind Params: first_name=" . $first_name . ", last_name=" . $last_name . ", bday=" . ($bday !== null ? $bday : 'NULL') . ", gender=" . $gender . ", profile_picture=" . $new_file_name . ", patient_id=" . $patient_id . ", account_id=" . $_SESSION['account_ID']);
$stmt->bind_param("ssissii", $first_name, $last_name, $bday, $gender, $new_file_name, $patient_id, $_SESSION['account_ID']);


if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $_SESSION['success'] = "Patient details updated successfully!";
    } else {
        $_SESSION['error'] = "No rows updated. Patient details may be the same.";
    }
} else {
    $_SESSION['error'] = "Error updating patient details: " . $stmt->error;
    error_log("SQL Error: " . $stmt->error);
}

$stmt->close();
header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
exit();
?>

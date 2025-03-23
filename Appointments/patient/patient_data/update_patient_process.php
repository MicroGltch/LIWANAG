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

$target_dir = "../../../uploads/profile_pictures/";
$new_file_name = $existing_picture;

// ✅ Handle Profile Picture
if (!empty($_FILES['profile_picture']['name'])) {
    $file_name = $_FILES['profile_picture']['name'];
    $file_tmp = $_FILES['profile_picture']['tmp_name'];
    $file_size = $_FILES['profile_picture']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_types = ["jpg", "jpeg", "png"];
    $max_file_size = 5 * 1024 * 1024;

    if (!in_array($file_ext, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
        exit();
    }

    if ($file_size > $max_file_size) {
        $_SESSION['error'] = "File is too large. Maximum allowed size is 5MB.";
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
        exit();
    }

    $new_file_name = uniqid('profile_') . "." . $file_ext;
    $target_file = $target_dir . $new_file_name;

    if (!move_uploaded_file($file_tmp, $target_file)) {
        $_SESSION['error'] = "Failed to upload profile picture.";
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
        exit();
    }
}

// ✅ Validate birthday
if (!empty($bday)) {
    $date = DateTime::createFromFormat('Y-m-d', $bday);
    if (!$date) {
        $_SESSION['error'] = "Invalid date format.";
        header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
        exit();
    }
    $bday = $date->format('Y-m-d');
} else {
    $bday = null;
}

// ✅ Handle Doctor’s Referral Upload (Unified)
$uploadDir = "../../../uploads/doctors_referrals/";
$officialFileName = null;
$proofFileName = null;
$referralType = null;

// Official Referral
if (!empty($_FILES['official_referral']['name'])) {
    $ext = strtolower(pathinfo($_FILES['official_referral']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ["jpg", "jpeg", "png", "pdf"])) {
        $officialFileName = uniqid("official_") . "." . $ext;
        move_uploaded_file($_FILES['official_referral']['tmp_name'], $uploadDir . $officialFileName);
        $referralType = 'official';
    }
}

// Proof of Booking
if (!empty($_FILES['proof_of_booking']['name'])) {
    $ext = strtolower(pathinfo($_FILES['proof_of_booking']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ["jpg", "jpeg", "png", "pdf"])) {
        $proofFileName = uniqid("proof_") . "." . $ext;
        move_uploaded_file($_FILES['proof_of_booking']['tmp_name'], $uploadDir . $proofFileName);
        $referralType = 'proof_of_booking';
    }
}

// ✅ Check if referral record exists
$referralCheck = $connection->prepare("SELECT referral_id FROM doctor_referrals WHERE patient_id = ?");
$referralCheck->bind_param("i", $patient_id);
$referralCheck->execute();
$referralCheck->store_result();

if ($referralCheck->num_rows > 0) {
    $referralCheck->bind_result($referral_id);
    $referralCheck->fetch();
    $referralCheck->close();

    if ($officialFileName || $proofFileName) {
        $stmt = $connection->prepare("INSERT INTO doctor_referrals (patient_id, official_referral_file, proof_of_booking_referral_file) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $patient_id, $officialFileName, $proofFileName);
        $stmt->execute();
        $stmt->close();
    }
} else {
    $referralCheck->close();
    if ($officialFileName || $proofFileName) {
        $stmt = $connection->prepare("INSERT INTO doctor_referrals (patient_id, official_referral_file, proof_of_booking_referral_file, referral_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $patient_id, $officialFileName, $proofFileName, $referralType);
        $stmt->execute();
        $stmt->close();
    }
}

// ✅ Update patient details
$query = "UPDATE patients SET first_name = ?, last_name = ?, bday = ?, gender = ?, profile_picture = ? WHERE patient_id = ? AND account_id = ?";
$stmt = $connection->prepare($query);
// BEFORE: bind_param("ssissii", ...)
$stmt = $connection->prepare("UPDATE patients SET first_name = ?, last_name = ?, bday = ?, gender = ?, profile_picture = ? WHERE patient_id = ? AND account_id = ?");

// If birthday is null, bind as NULL explicitly
if ($bday === null) {
    $stmt->bind_param("sssssii", $first_name, $last_name, $bday, $gender, $new_file_name, $patient_id, $_SESSION['account_ID']);
} else {
    $stmt->bind_param("sssssii", $first_name, $last_name, $bday, $gender, $new_file_name, $patient_id, $_SESSION['account_ID']);
}

if ($stmt->execute()) {
    $_SESSION['success'] = $stmt->affected_rows > 0
        ? "Patient details updated successfully!"
        : "No rows updated. Patient details may be the same.";
} else {
    $_SESSION['error'] = "Error updating patient details: " . $stmt->error;
    error_log("SQL Error: " . $stmt->error);
}

$stmt->close();
header("Location: ../../../Dashboards/clientdashboard.php#view-registered-patients");
exit();
?>

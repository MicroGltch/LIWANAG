<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['account_ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_id = $_SESSION['account_ID'];
    $first_name = $_POST['patient_fname'];
    $last_name = $_POST['patient_lname'];
    $bday = $_POST['patient_birthday'];
    $gender = $_POST['patient_gender'];
    $referral_type = isset($_POST['referral_type']) ? $_POST['referral_type'] : 'none';

    // Check for duplicates
    $stmt_check = $connection->prepare("SELECT COUNT(*) FROM patients WHERE first_name = ? AND last_name = ? AND bday = ? AND gender = ?");
    $stmt_check->bind_param("ssss", $first_name, $last_name, $bday, $gender);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        echo json_encode(['status' => 'duplicate', 'message' => 'A patient with these credentials already exists.']);
        exit();
    }

    // Handle profile picture
    $target_dir_profile = "../../../uploads/profile_pictures/";
    if (!isset($_FILES['patient_picture']) || $_FILES['patient_picture']['error'] !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'Profile picture is required.']);
        exit();
    }

    $profile = $_FILES['patient_picture'];
    $profile_ext = strtolower(pathinfo($profile['name'], PATHINFO_EXTENSION));
    $allowed_types = ["jpg", "jpeg", "png"];
    $max_file_size = 5 * 1024 * 1024;

    if (!in_array($profile_ext, $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid profile picture file type.']);
        exit();
    }
    if ($profile['size'] > $max_file_size) {
        echo json_encode(['status' => 'error', 'message' => 'Profile picture is too large.']);
        exit();
    }

    $new_profile_name = uniqid('profile_') . "." . $profile_ext;
    $profile_path = $target_dir_profile . $new_profile_name;

    if (!move_uploaded_file($profile['tmp_name'], $profile_path)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload profile picture.']);
        exit();
    }

    // Insert patient first
    $insertPatientSQL = "INSERT INTO patients (account_id, first_name, last_name, bday, gender, profile_picture, service_type)
                         VALUES (?, ?, ?, ?, ?, ?, 'For Evaluation')";
    $stmt = $connection->prepare($insertPatientSQL);
    $stmt->bind_param("isssss", $account_id, $first_name, $last_name, $bday, $gender, $new_profile_name);

    if ($stmt->execute()) {
        $patient_id = $stmt->insert_id; // Get inserted patient ID
        $stmt->close();

        // Handle Doctor's Referral Upload
        if ($referral_type !== 'none' && isset($_FILES['referral_file']) && $_FILES['referral_file']['error'] === 0) {
            $uploadDir = "../../../uploads/doctors_referrals/";
            
            // Make sure directory exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $referral_file = $_FILES['referral_file'];
            $referral_ext = strtolower(pathinfo($referral_file['name'], PATHINFO_EXTENSION));
            $allowed_referral_types = ["jpg", "jpeg", "png", "pdf"];
            
            if (!in_array($referral_ext, $allowed_referral_types)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid referral file type.']);
                exit();
            }
            
            $new_referral_name = uniqid('referral_') . "." . $referral_ext;
            $referral_path = $uploadDir . $new_referral_name;
            
            if (!move_uploaded_file($referral_file['tmp_name'], $referral_path)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to upload referral file.']);
                exit();
            }
            
            // Prepare values for database insertion
            $official_referral_file = null;
            $proof_of_booking_file = null;
            
            if ($referral_type === 'official') {
                $official_referral_file = $new_referral_name;
            } else if ($referral_type === 'proof_of_booking') {
                $proof_of_booking_file = $new_referral_name;
            }
            
            // Insert into doctor_referrals table
            $insertReferralSQL = "INSERT INTO doctor_referrals (patient_id, official_referral_file, proof_of_booking_referral_file, referral_type)
                                 VALUES (?, ?, ?, ?)";
            $stmt = $connection->prepare($insertReferralSQL);
            $stmt->bind_param("isss", $patient_id, $official_referral_file, $proof_of_booking_file, $referral_type);
            
            if (!$stmt->execute()) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save referral information: ' . $stmt->error]);
                exit();
            }
            $stmt->close();
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Patient registered successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    }
}
?>
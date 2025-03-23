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
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'Profile picture is required.']);
        exit();
    }

    $profile = $_FILES['profile_picture'];
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

        // ✅ Handle Doctor’s Referral Upload (Unified)
        $uploadDir = "../../../uploads/doctors_referrals/";
        $officialFileName = null;
        $proofFileName = null;
        $referralType = null;

        if (!empty($_FILES['referral_file']['name'])) {
            $referral_ext = strtolower(pathinfo($_FILES['referral_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($referral_ext, ["jpg", "jpeg", "png", "pdf"])) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid referral file type.']);
                exit();
            }

            $type = $_POST['referral_type']; // expected: official or proof_of_booking

            if ($type === 'official') {
                $officialFileName = time() . "_official_" . basename($_FILES['referral_file']['name']);
                move_uploaded_file($_FILES['referral_file']['tmp_name'], $uploadDir . $officialFileName);
                $referralType = 'official';
            } elseif ($type === 'proof_of_booking') {
                $proofFileName = time() . "_proof_" . basename($_FILES['referral_file']['name']);
                move_uploaded_file($_FILES['referral_file']['tmp_name'], $uploadDir . $proofFileName);
                $referralType = 'proof_of_booking';
            }
        }

        // ✅ Insert into `doctor_referrals` if file was uploaded
        if ($officialFileName || $proofFileName) {
            $insertReferralSQL = "INSERT INTO doctor_referrals (patient_id, official_referral_file, proof_of_booking_referral_file, referral_type)
                                  VALUES (?, ?, ?, ?)";
            $stmt = $connection->prepare($insertReferralSQL);
            $stmt->bind_param("isss", $patient_id, $officialFileName, $proofFileName, $referralType);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['status' => 'success', 'message' => 'Patient registered successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    }
}
?>

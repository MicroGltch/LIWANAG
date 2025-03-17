<?php

require_once "../../../dbconfig.php";

session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['account_ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['patient_fname'];
    $last_name = $_POST['patient_lname'];
    $bday = $_POST['patient_birthday'];
    $gender = $_POST['patient_gender'];

    $target_dir = "../../../uploads/profile_pictures/";
    $file_name = $_FILES['profile_picture']['name'];
    $file_tmp = $_FILES['profile_picture']['tmp_name'];
    $file_size = $_FILES['profile_picture']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_types = ["jpg", "jpeg", "png"];
    $max_file_size = 5 * 1024 * 1024;

    if (!in_array($file_ext, $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type.']);
    } elseif ($file_size > $max_file_size) {
        echo json_encode(['status' => 'error', 'message' => 'File is too large.']);
    } else {
        $new_file_name = uniqid() . "." . $file_ext;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $query = "INSERT INTO patients (account_id, first_name, last_name, bday, gender, profile_picture, service_type) VALUES (?, ?, ?, ?, ?, ?, 'For Evaluation')";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("isssss", $_SESSION['account_ID'], $first_name, $last_name, $bday, $gender, $new_file_name);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Patient registered successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload profile picture.']);
        }
    }
}
?>
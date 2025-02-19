<?php
require_once "../../dbconfig.php";
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../../Accounts/loginverify/loginlogic.php?error=Please log in to continue.");
    exit();
}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['patient_fname'];
    $last_name = $_POST['patient_lname'];
    $age = $_POST['patient_age'];
    $gender = $_POST['patient_gender'];
    $profile_picture = $_FILES['profile_picture']['name'];

    // Handle file upload
    $target_dir = "../uploads/";
    $target_file = $target_dir . basename($profile_picture);
    
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
        // Insert patient record
        $query = "INSERT INTO patients (account_id, first_name, last_name, age, gender, profile_picture, service_type) 
                  VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("isssss", $_SESSION['account_ID'], $first_name, $last_name, $age, $gender, $target_file);

        if ($stmt->execute()) {
            $success = "Patient registered successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Failed to upload profile picture.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Patient</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Patient Registration</h2>

        <?php if ($success): ?>
            <p class="uk-alert-success uk-padding-small"><?php echo $success; ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="uk-alert-danger uk-padding-small"><?php echo $error; ?></p>
        <?php endif; ?>

        <form action="register_patient.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked">
            <label>First Name:</label>
            <input class="uk-input" type="text" name="patient_fname" required>
            <label>Last Name:</label>
            <input class="uk-input" type="text" name="patient_lname" required>
            <label>Age:</label>
            <input class="uk-input" type="number" name="patient_age" required>
            <label>Gender:</label>
            <select class="uk-select" name="patient_gender">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
            <label>Profile Picture:</label>
            <input class="uk-input" type="file" name="profile_picture" required>
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Register</button>
        </form>
    </div>
</body>
</html>




<?php

require_once "../../dbconfig.php";

session_start();

// Debug: Check if session is starting properly
if (!isset($_SESSION)) {
    die("Session failed to start.");
}

// Check if the user is logged in 
if (!isset($_SESSION['username']) || !isset($_SESSION['account_ID'])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['patient_fname'];
    $last_name = $_POST['patient_lname'];
    $age = $_POST['patient_age'];
    $gender = $_POST['patient_gender'];
    
    // File upload handling
    $target_dir = "../../uploads/profile_pictures/";
    $file_name = $_FILES['profile_picture']['name'];
    $file_tmp = $_FILES['profile_picture']['tmp_name'];
    $file_size = $_FILES['profile_picture']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed file types and size limit (5MB)
    $allowed_types = ["jpg", "jpeg", "png"];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file_ext, $allowed_types)) {
        $error = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
    } elseif ($file_size > $max_file_size) {
        $error = "File is too large. Maximum allowed size is 5MB.";
    } else {
        // Generate unique file name
        $new_file_name = uniqid() . "." . $file_ext;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            // Store file path in database
            $query = "INSERT INTO patients (account_id, first_name, last_name, age, gender, profile_picture, service_type) 
                      VALUES (?, ?, ?, ?, ?, ?, 'For Evaluation')";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("isssss", $_SESSION['account_ID'], $first_name, $last_name, $age, $gender, $new_file_name);

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

        <form action="register_patient_form.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked">
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
            <input class="uk-input" type="file" name="profile_picture" accept=".jpg, .jpeg, .png" required>
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Register</button>
        </form>


    </div>
    
    <a href="../frontend/book_appointment_form.php" class="uk-button uk-button-danger uk-margin-top">Book Appointment</a>

    <a href="../../Accounts/logout.php" class="uk-button uk-button-danger uk-margin-top">Logout</a>

</body>
</html>

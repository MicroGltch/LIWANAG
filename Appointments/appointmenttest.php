<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Frontend - Therapy Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Patient Registration</h2>
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
        <hr>
        
        <h2>Book an Appointment</h2>
        <form action="book_appointment.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked">
            <label>Select Patient:</label>
            <select class="uk-select" name="patient_id" required></select>
            <label>Appointment Type:</label>
            <select class="uk-select" name="appointment_type" required>
                <option value="Initial Evaluation">Initial Evaluation</option>
                <option value="Playgroup">Playgroup</option>
            </select>
            <label>Date:</label>
            <input class="uk-input" type="date" name="appointment_date" required>
            <label>Time:</label>
            <input class="uk-input" type="time" name="appointment_time" required>
            <label>Doctor's Referral (Optional for Playgroup, Required for IE):</label>
            <input class="uk-input" type="file" name="doctors_referral">
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Book</button>
        </form>
        <hr>

        <h2>Assign Therapist</h2>
        <form action="assign_therapist.php" method="POST" class="uk-form-stacked">
            <label>Select Appointment:</label>
            <select class="uk-select" name="appointment_id" required></select>
            <label>Select Therapist:</label>
            <select class="uk-select" name="therapist_id" required></select>
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Assign</button>
        </form>
    </div>

    <hr>
    <h2>Backend PHP Files</h2>
    <h3>register_patient.php</h3>
    <pre>
    <?php
    require_once "../dbconfig.php";
    session_start();
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $first_name = $_POST['patient_fname'];
        $last_name = $_POST['patient_lname'];
        $age = $_POST['patient_age'];
        $gender = $_POST['patient_gender'];
        $profile_picture = $_FILES['profile_picture']['name'];
    
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($profile_picture);
        move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file);
    
        $query = "INSERT INTO patients (account_id, first_name, last_name, age, gender, profile_picture, service_type) VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("isssss", $_SESSION['account_ID'], $first_name, $last_name, $age, $gender, $target_file);
    
        if ($stmt->execute()) {
            echo "Patient registered successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    ?>
    </pre>

    <h3>book_appointment.php</h3>
    <pre>
    <?php
    require_once "dbconfig.php";
    session_start();
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $patient_id = $_POST['patient_id'];
        $appointment_type = $_POST['appointment_type'];
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $doctors_referral = $_FILES['doctors_referral']['name'] ?? null;
    
        if ($appointment_type === "Initial Evaluation" && empty($doctors_referral)) {
            echo "A doctor's referral is required for Initial Evaluation.";
            exit();
        }
    
        if (!empty($doctors_referral)) {
            $target_file = "uploads/" . basename($doctors_referral);
            move_uploaded_file($_FILES['doctors_referral']['tmp_name'], $target_file);
        }
    
        $query = "INSERT INTO appointments (account_id, patient_id, date, time, session_type, doctor_referral, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("iissss", $_SESSION['account_ID'], $patient_id, $appointment_date, $appointment_time, $appointment_type, $target_file);
    
        if ($stmt->execute()) {
            echo "Appointment booked successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    ?>
    </pre>
</body>
</html>


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
<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
// if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
//     header("Location: ../../../loginpage.php");
//     exit();
// }

// ✅ Get `patient_id` from URL instead of `appointment_id`
$patientID = $_GET['patient_id'] ?? null;

if (!$patientID) {
    echo "Invalid patient or unauthorized access.";
    exit();
}

$appointmentID = $_GET['appointment_id'] ?? null;

if (!$appointmentID) {
    echo "Invalid appointment.";
    exit();
}

// ✅ Fetch appointment booking limits and business hours from settings table
$settingsQuery = "SELECT business_hours_start, business_hours_end, min_days_advance, max_days_advance FROM settings LIMIT 1";
$settingsStmt = $connection->prepare($settingsQuery);
$settingsStmt->execute();
$settingsResult = $settingsStmt->get_result();
$settings = $settingsResult->fetch_assoc();

$minDays = $settings['min_days_advance'] ?? 1; // Default to 1 if not set
$maxDays = $settings['max_days_advance'] ?? 30; // Default to 30 if not set
$businessHoursStart = $settings['business_hours_start'] ?? "08:00"; // Default 8 AM
$businessHoursEnd = $settings['business_hours_end'] ?? "17:00"; // Default 5 PM

// ✅ Calculate min/max selectable dates
$minDate = date('Y-m-d', strtotime("+$minDays days"));
$maxDate = date('Y-m-d', strtotime("+$maxDays days"));



// ✅ Fetch patient details
$query = "SELECT first_name, last_name, service_type FROM patients WHERE patient_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $patientID);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    echo "Patient not found.";
    exit();
}

$patient_name = htmlspecialchars($patient['first_name'] . " " . $patient['last_name']);
$service_type = htmlspecialchars($patient['service_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rebook Appointment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Rebook Appointment for <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?></h2>
        <form id="rebookForm" action="process_rebook.php" method="POST" class="uk-form-stacked">
            <input type="hidden" name="appointment_id" value="<?= $appointmentID; ?>">
            <input type="hidden" name="patient_id" value="<?= $patientID; ?>">


            <label>Patient Service Type:</label>
            <select class="uk-select" name="service_type" required>
                <option value="Occupational Therapy" <?= ($service_type == "Occupational Therapy") ? "selected" : ""; ?>>Occupational Therapy</option>
                <option value="Behavioral Therapy" <?= ($service_type == "Behavioral Therapy") ? "selected" : ""; ?>>Behavioral Therapy</option>
            </select>


            <label>Date for next session:</label>
            <input class="uk-input" type="date" name="new_date" required min="<?= $minDate; ?>" max="<?= $maxDate; ?>">


            <label>Time for next session:</label>
            <input class="uk-input" type="time" name="new_time" required min="<?= $businessHoursStart; ?>" max="<?= $businessHoursEnd; ?>">


            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Rebook Appointment</button>
            <a href="../../Dashboards/therapistdashboard.php" class="uk-button uk-button-default">Cancel</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById("rebookForm").addEventListener("submit", function(event) {
            event.preventDefault();
            let formData = new FormData(this);

            fetch("process_rebook.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    Swal.fire("Success!", data.message, "success").then(() => {
                        window.location.href = "therapist_dashboard.php";
                    });
                } else {
                    Swal.fire("Error!", data.message, "error");
                }
            })
            .catch(error => console.error("Error:", error));
        });
    </script>
</body>
</html>

<?php
require_once "../../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header("Location: ../loginpage.php");
    exit();
}

// ✅ Get `patient_id` from URL instead of `appointment_id`
$patientID = $_GET['patient_id'] ?? null;

if (!$patientID) {
    echo "Invalid patient or unauthorized access.";
    exit();
}

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
        <h2>Rebook Appointment for <?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></h2>
        <form id="rebookForm" action="process_rebook.php" method="POST" class="uk-form-stacked">
            <input type="hidden" name="appointment_id" value="<?= $appointmentID; ?>">
            <input type="hidden" name="patient_id" value="<?= $appointment['patient_id']; ?>">

            <label>Patient Service Type:</label>
            <select class="uk-select" name="service_type" required>
                <option value="Occupational Therapy" <?= ($appointment['service_type'] == "Occupational Therapy") ? "selected" : ""; ?>>Occupational Therapy</option>
                <option value="Behavioral Therapy" <?= ($appointment['service_type'] == "Behavioral Therapy") ? "selected" : ""; ?>>Behavioral Therapy</option>
            </select>

            <label>Session Type:</label>
            <select class="uk-select" name="session_type" required>
                <option value="Occupational Therapy" <?= ($appointment['session_type'] == "Occupational Therapy") ? "selected" : ""; ?>>Occupational Therapy</option>
                <option value="Behavioral Therapy" <?= ($appointment['session_type'] == "Behavioral Therapy") ? "selected" : ""; ?>>Behavioral Therapy</option>
            </select>

            <label>Date for next session:</label>
            <input class="uk-input" type="date" name="new_date" required>

            <label>Time for next session:</label>
            <input class="uk-input" type="time" name="new_time" required>

            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Rebook Appointment</button>
            <a href="therapist_dashboard.php" class="uk-button uk-button-default">Cancel</a>
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
                        window.location.href = "../therapist_dashboard.php";
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

<?php
require_once "../../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header("Location: ../../../Accounts/loginpage.php");
    exit();
}

$therapistID = $_SESSION['account_ID'];

// ✅ Fetch previous patients the therapist interacted with
$query = "SELECT DISTINCT p.patient_id, p.first_name, p.last_name, p.service_type 
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          WHERE a.therapist_id = ? 
          AND a.status = 'Completed'";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $therapistID);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Fetch system settings (business hours, advance booking, blocked dates)
$settingsQuery = "SELECT business_hours_start, business_hours_end, max_days_advance, min_days_advance, blocked_dates 
                  FROM settings LIMIT 1";
$settingsResult = $connection->query($settingsQuery);
$settings = $settingsResult->fetch_assoc();

$businessHoursStart = $settings["business_hours_start"] ?? "09:00:00";
$businessHoursEnd = $settings["business_hours_end"] ?? "17:00:00";
$maxDaysAdvance = $settings["max_days_advance"] ?? 30;
$minDaysAdvance = $settings["min_days_advance"] ?? 3;
$blockedDates = !empty($settings["blocked_dates"]) ? json_decode($settings["blocked_dates"], true) : []; // Ensure array
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rebook Patient</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="/LIWANAG/CSS/uikit-3.22.2/css/uikit.min.css">
<script src="/LIWANAG/CSS/uikit-3.22.2/js/uikit.min.js"></script>
<script src="/LIWANAG/CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>


    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="/LIWANAG/CSS/style.css" type="text/css" >

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!-- Include Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    html, body {
    background-color: #ffffff !important;
}

</style>

</head>

<body>

    <div class="uk-container uk-margin-top">
        <h2 class="uk-text-bold" >Rebook a Previous Patient</h2>


        <form id="rebookForm" action="../../app_process/process_rebook.php" method="POST" class="uk-form-stacked">
            
        <label class="uk-form-label">Select Patient:</label>

            <select class="uk-select" name="patient_id" id="patient_id" required>
                <option value="" disabled selected>Select a Patient</option>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= $patient['patient_id']; ?>" data-service="<?= htmlspecialchars($patient['service_type'] ?? ''); ?>">
                        <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="uk-form-label">Service Type:</label>
            <select class="uk-select" name="service_type" id="service_type" required>
                <option value="" disabled selected>Select Service Type</option>
                <option value="Occupational Therapy">Occupational Therapy</option>
                <option value="Behavioral Therapy">Behavioral Therapy</option>
            </select>

            <label class="uk-form-label">Date for next session:</label>
            <input class="uk-input" type="text" name="new_date" id="new_date" required>

            <label class="uk-form-label">Time for next session:</label>
            <select class="uk-select" name="new_time" id="new_time" required></select>

            
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Rebook Appointment</button>

            <button href="../../../Dashboards/therapistdashboard.php" class="uk-button uk-button-primary uk-margin-top">Cancel</button>

        </form>
        
 
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let blockedDates = <?= json_encode($blockedDates) ?>; // Load blocked dates from PHP
            let minDaysAdvance = <?= $minDaysAdvance ?>; 
            let maxDaysAdvance = <?= $maxDaysAdvance ?>;
            let patientDropdown = document.getElementById("patient_id");
            let serviceTypeDropdown = document.getElementById("service_type");
            let sessionTypeDropdown = document.getElementById("session_type");
            let dateInput = document.getElementById("new_date");
            let timeInput = document.getElementById("new_time");
            let submitButton = document.querySelector("button[type='submit']");

            let today = new Date();
            let minDate = new Date(today);
            minDate.setDate(today.getDate() + minDaysAdvance);

            let maxDate = new Date();
            maxDate.setDate(today.getDate() + maxDaysAdvance);

            // ✅ Apply Flatpickr to disable past dates & blocked dates
            flatpickr(dateInput, {
                minDate: minDate.toISOString().split("T")[0], 
                maxDate: maxDate.toISOString().split("T")[0], 
                dateFormat: "Y-m-d",
                disable: blockedDates,
                onChange: function() {
                    updateAvailableTimes();
                    checkExistingAppointment();
                }
            });

            function updateAvailableTimes() {
                timeInput.innerHTML = "";
                let startHour = parseInt("<?= $businessHoursStart ?>".split(":")[0]);
                let endHour = parseInt("<?= $businessHoursEnd ?>".split(":")[0]);

                for (let hour = startHour; hour < endHour; hour++) {
                    let formattedTime = `${hour.toString().padStart(2, "0")}:00`;
                    let option = document.createElement("option");
                    option.value = formattedTime;
                    option.textContent = formattedTime;
                    timeInput.appendChild(option);
                }
            }

            // ✅ Function to check existing appointment in real-time
            function checkExistingAppointment() {
                    let patientID = patientDropdown.value;
                    if (!patientID) return;

                    fetch(`../patient_data/check_patient_appointment.php?patient_id=${patientID}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === "error") {
                                Swal.fire({
                                    title: "Rebooking Not Allowed",
                                    html: `
                                        <p>${data.message}</p>
                                        <p><strong>Existing Status:</strong> ${data.existing_status}</p>
                                        <p><strong>Date:</strong> ${data.existing_date}</p>
                                        <p><strong>Time:</strong> ${data.existing_time}</p>
                                        <p>Please select a different patient or check the existing appointment.</p>
                                    `,
                                    icon: "warning"
                                });

                                submitButton.disabled = true; // ❌ Disable form submission
                            } else {
                                submitButton.disabled = false; // ✅ Allow submission if no conflict
                            }
                        })
                        .catch(error => {
                            console.error("Error checking existing appointments:", error);
                            Swal.fire("Error", "An error occurred while checking for existing appointments.", "error");
                        });
                }

                // ✅ Trigger validation when a patient is selected
                patientDropdown.addEventListener("change", checkExistingAppointment);
            });
    </script>
</body>
</html>

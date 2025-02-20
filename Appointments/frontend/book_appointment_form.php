<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// Fetch timetable settings safely
$settingsQuery = "SELECT business_hours_start, business_hours_end, max_days_advance, min_days_advance, blocked_dates, initial_eval_duration, playgroup_duration 
                  FROM settings LIMIT 1";
$settingsResult = $connection->query($settingsQuery);
$settings = $settingsResult->fetch_assoc();

$businessHoursStart = $settings["business_hours_start"] ?? "09:00:00";
$businessHoursEnd = $settings["business_hours_end"] ?? "17:00:00";
$maxDaysAdvance = $settings["max_days_advance"] ?? 30;
$minDaysAdvance = $settings["min_days_advance"] ?? 4;
$blockedDates = !empty($settings["blocked_dates"]) ? json_decode($settings["blocked_dates"], true) : []; // Ensure array
$ieDuration = $settings["initial_eval_duration"] ?? 60;
$pgDuration = $settings["playgroup_duration"] ?? 120;

// ✅ Fetch registered patients
$patientsQuery = "SELECT patient_id, first_name, last_name FROM patients WHERE account_id = ?";
$stmt = $connection->prepare($patientsQuery);
$stmt->bind_param("i", $_SESSION['account_ID']);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$role = strtolower(trim($_SESSION['account_Type']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
        
    <!-- Include Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Book an Appointment</h2>
        <p>Your Role: <strong><?= ucfirst($role); ?></strong></p>

        <form id="appointmentForm" action="../backend/book_appointment_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked">
            <label>Select Patient:</label>
            <select class="uk-select" name="patient_id" id="patient_id" required>
                <option value="" disabled selected>Select a Patient</option>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= $patient['patient_id']; ?>">
                        <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- ✅ Display Patient Details Here -->
            <div id="patientDetails" class="uk-margin uk-card uk-card-default uk-card-body" style="display: none;">
                <h4>Patient Details</h4>
                <p><strong>Name:</strong> <span id="patient_name"></span></p>
                <p><strong>Age:</strong> <span id="patient_age"></span></p>
                <p><strong>Gender:</strong> <span id="patient_gender"></span></p>
                <p><strong>Service Type:</strong> <span id="patient_service"></span></p>
                <img id="patient_profile" src="" alt="Profile Picture" class="uk-border-rounded" style="width: 100px; height: 100px; display: none;">

                <!-- ✅ Edit Patient Button -->
                <button id="editPatientBtn" class="uk-button uk-button-secondary uk-margin-top" style="display: none;">
                    Edit Patient Details
                </button>
            </div>

            <label>Appointment Type:</label>
            <select class="uk-select" name="appointment_type" id="appointment_type" required>
                <option value="" disabled selected>Select Appointment Type</option>
                <option value="Initial Evaluation">Initial Evaluation</option>
                <option value="Playgroup">Playgroup</option>
            </select>

            <label>Date:</label>
            <input class="uk-input" type="date" name="appointment_date" id="appointment_date" required>

            <label>Time:</label>
            <select class="uk-select" name="appointment_time" id="appointment_time" required></select>

            <div id="referralQuestion" style="display: none;">
                <label>
                    Do you have a doctor's referral?
                    <i class="fas fa-info-circle" uk-tooltip="A doctor's referral is required for Initial Evaluation. If you don't have one, provide proof of a scheduled referral appointment."></i>
                </label>
                <select class="uk-select" id="has_referral">
                    <option value="" disabled selected>Select Answer</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>

            <div id="referralUpload" style="display: none;">
                <label id="referralLabel">Doctor's Referral:</label>
                <input class="uk-input" type="file" name="doctors_referral" id="doctors_referral" accept=".jpg, .jpeg, .png, .pdf">
            </div>

            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Book</button>
        </form>
    </div>


    <script>
    //for timetable
    document.addEventListener("DOMContentLoaded", function () {
        let blockedDates = <?= json_encode($blockedDates) ?>; //  Load blocked dates from PHP
        let minDaysAdvance = <?= $minDaysAdvance ?> + 1; // Add 1 day to achieve the req. Ex: today is feb 20, the earliest is feb 24
        let maxDaysAdvance = <?= $maxDaysAdvance ?>;
        
        let today = new Date();
        let minDate = new Date(today);
        minDate.setDate(today.getDate() + minDaysAdvance);  // ✅ Prevent booking before min_days_advance

        let maxDate = new Date();
        maxDate.setDate(today.getDate() + maxDaysAdvance);  // ✅ Prevent booking beyond max_days_advance

        // ✅ Apply Flatpickr to disable past dates & blocked dates
        flatpickr("#appointment_date", {
            minDate: minDate.toISOString().split("T")[0], 
            maxDate: maxDate.toISOString().split("T")[0], 
            dateFormat: "Y-m-d",
            disable: blockedDates, // ✅ Blocked dates are **fully unselectable**
            onChange: function(selectedDates, dateStr) {
                updateAvailableTimes();
            }
        });

        let timeInput = document.getElementById("appointment_time");
        let appointmentType = document.getElementById("appointment_type");

        function updateAvailableTimes() {
            timeInput.innerHTML = "";
            let selectedType = appointmentType.value;
            let interval = selectedType === "Playgroup" ? <?= $pgDuration ?> : <?= $ieDuration ?>;
            let startHour = parseInt("<?= $businessHoursStart ?>".split(":")[0]);
            let endHour = parseInt("<?= $businessHoursEnd ?>".split(":")[0]);

            for (let hour = startHour; hour < endHour; hour += interval / 60) {
                let formattedTime = `${hour.toString().padStart(2, "0")}:00`;
                let option = document.createElement("option");
                option.value = formattedTime;
                option.textContent = formattedTime;
                timeInput.appendChild(option);
            }
        }

        appointmentType.addEventListener("change", function () {
            updateAvailableTimes();
        });
    });

    //if the selected patient has a pending appointment, then it will return to "select appointment type".
        document.addEventListener("DOMContentLoaded", function () {
        let appointmentType = document.getElementById("appointment_type");
        let referralQuestion = document.getElementById("referralQuestion");
        let referralUpload = document.getElementById("referralUpload");
        let referralLabel = document.getElementById("referralLabel");
        let hasReferral = document.getElementById("has_referral");
        let doctorsReferral = document.getElementById("doctors_referral");
        let patientDropdown = document.getElementById("patient_id");

        // ✅ Show/Hide Doctor's Referral Question Based on Appointment Type
        appointmentType.addEventListener("change", function () {
            if (appointmentType.value === "Initial Evaluation") {
                referralQuestion.style.display = "block";
                referralUpload.style.display = "none";
            } else {
                referralQuestion.style.display = "none";
                referralUpload.style.display = "none";
            }

            // ✅ Check Pending Appointments on Change
            checkPendingAppointments();
        });

        // ✅ Show Referral Upload If Answer is "Yes" or "No"
        hasReferral.addEventListener("change", function () {
            referralUpload.style.display = "block";
            referralLabel.textContent = (hasReferral.value === "yes") 
                ? "Upload Doctor's Referral:" 
                : "Upload Proof of Booking for Doctor's Referral:";
        });

        // ✅ Prevent Booking for Same Session Type & Reset Selection on Error
        function checkPendingAppointments() {
            let patientID = patientDropdown.value;
            let selectedType = appointmentType.value;

            if (!patientID || !selectedType) return; // Skip if nothing selected

            fetch("../backend/check_pending_appointment.php?patient_id=" + patientID)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "error" && data.existing_type === selectedType) {
                        Swal.fire("Error!", "This patient already has a pending or confirmed appointment for this session type.", "error")
                            .then(() => {
                                // ✅ Reset Dropdown Selection on Error
                                appointmentType.value = "";
                                referralQuestion.style.display = "none";
                                referralUpload.style.display = "none";
                            });
                    }
                })
                .catch(error => console.error("Error checking pending appointments:", error));
        }

        // ✅ Trigger Check when Patient is Selected
        patientDropdown.addEventListener("change", checkPendingAppointments);
    });


    // dynamically fetch patient details when selected
    document.addEventListener("DOMContentLoaded", function () {
        let patientDropdown = document.getElementById("patient_id");
        let patientDetailsDiv = document.getElementById("patientDetails");
        let patientName = document.getElementById("patient_name");
        let patientAge = document.getElementById("patient_age");
        let patientGender = document.getElementById("patient_gender");
        let patientService = document.getElementById("patient_service");
        let patientProfile = document.getElementById("patient_profile");
        let editPatientBtn = document.getElementById("editPatientBtn");

        patientDropdown.addEventListener("change", function () {
            let patientID = this.value;
            
            if (!patientID) {
                patientDetailsDiv.style.display = "none";
                return;
            }

            fetch("../patient/fetch_patient_details.php?patient_id=" + patientID)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        patientName.textContent = data.patient.first_name + " " + data.patient.last_name;
                        patientAge.textContent = data.patient.age;
                        patientGender.textContent = data.patient.gender;
                        patientService.textContent = data.patient.service_type ?? "Pending";

                        if (data.patient.profile_picture) {
                            patientProfile.src = "../../uploads/profile_pictures/" + data.patient.profile_picture;
                            patientProfile.style.display = "block";
                        } else {
                            patientProfile.style.display = "none";
                        }

                        editPatientBtn.style.display = "inline-block";
                        editPatientBtn.onclick = function () {
                            window.location.href = "../patient/edit_patient_form.php";
                        };

                        patientDetailsDiv.style.display = "block";
                    } else {
                        patientDetailsDiv.style.display = "none";
                    }
                })
                .catch(error => console.error("Error fetching patient details:", error));
        });
    });

</script>

</body>
</html>

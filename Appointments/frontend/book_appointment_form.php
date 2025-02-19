<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// ✅ Fetch settings safely
$settingsQuery = "SELECT business_hours_start, business_hours_end, max_days_advance, min_days_advance, blocked_dates, initial_eval_duration, playgroup_duration 
                  FROM settings LIMIT 1";
$settingsResult = $connection->query($settingsQuery);
$settings = $settingsResult->fetch_assoc();

$businessHoursStart = $settings["business_hours_start"] ?? "09:00:00";
$businessHoursEnd = $settings["business_hours_end"] ?? "17:00:00";
$maxDaysAdvance = $settings["max_days_advance"] ?? 30;
$minDaysAdvance = $settings["min_days_advance"] ?? 3;
$blockedDates = !empty($settings["blocked_dates"]) ? json_decode($settings["blocked_dates"], true) : []; // ✅ Ensure array
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
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Book an Appointment</h2>
        <p>Your Role: <strong><?= ucfirst($role); ?></strong></p>

        <form id="appointmentForm" action="../backend/book_appointment_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked">
            <label>Select Patient:</label>
            <select class="uk-select" name="patient_id" required>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= $patient['patient_id']; ?>">
                        <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

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
        document.addEventListener("DOMContentLoaded", function () {
            let dateInput = document.getElementById("appointment_date");
            let timeInput = document.getElementById("appointment_time");
            let appointmentType = document.getElementById("appointment_type");
            let referralQuestion = document.getElementById("referralQuestion");
            let referralUpload = document.getElementById("referralUpload");
            let hasReferral = document.getElementById("has_referral");
            let doctorsReferral = document.getElementById("doctors_referral");

            let blockedDates = <?= json_encode($blockedDates) ?>;

            let today = new Date();
            let minDate = new Date(today);
            minDate.setDate(today.getDate() + <?= $minDaysAdvance ?>);
            let maxDate = new Date();
            maxDate.setDate(today.getDate() + <?= $maxDaysAdvance ?>);

            dateInput.setAttribute("min", minDate.toISOString().split("T")[0]);
            dateInput.setAttribute("max", maxDate.toISOString().split("T")[0]);

            dateInput.addEventListener("change", function () {
                let selectedDate = this.value;
                let isBlocked = blockedDates.some(date => date === selectedDate);

                if (isBlocked) {
                    Swal.fire({
                        icon: "error",
                        title: "Unavailable Date",
                        text: "This date is unavailable for booking.",
                    });
                    this.value = "";
                }
            });

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
                referralQuestion.style.display = appointmentType.value === "Initial Evaluation" ? "block" : "none";
                referralUpload.style.display = "none";
            });

            hasReferral.addEventListener("change", function () {
                referralUpload.style.display = "block";
                document.getElementById("referralLabel").textContent = hasReferral.value === "yes" ? "Upload Doctor's Referral:" : "Upload Proof of Booking for Doctor's Referral:";
            });

            document.getElementById("appointmentForm").addEventListener("submit", function (event) {
                if (appointmentType.value === "Initial Evaluation" && !doctorsReferral.files.length) {
                    event.preventDefault();
                    Swal.fire("Error!", "A doctor's referral or proof of booking is required for Initial Evaluation.", "error");
                }
            });
        });
    </script>
</body>
</html>

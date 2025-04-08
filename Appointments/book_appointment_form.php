<?php
require_once "../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    header("Location: ../Accounts/loginpage.php");
    exit();
}

if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "client") {
    header("Location: ../Accounts/loginpage.php");
    exit();
}

// Fetch timetable settings safely
$settingsQuery = "SELECT max_days_advance, min_days_advance, blocked_dates, initial_eval_duration, playgroup_duration 
                     FROM settings LIMIT 1";
$settingsResult = $connection->query($settingsQuery);
$settings = $settingsResult->fetch_assoc();

// $businessHoursStart = $settings["business_hours_start"] ?? "09:00:00";
// $businessHoursEnd = $settings["business_hours_end"] ?? "17:00:00";
$maxDaysAdvance = $settings["max_days_advance"] ?? 30;
$minDaysAdvance = $settings["min_days_advance"] ?? 3;
$blockedDates = !empty($settings["blocked_dates"]) ? json_decode($settings["blocked_dates"], true) : []; // Ensure array
$ieDuration = $settings["initial_eval_duration"] ?? 60;
$pgDuration = $settings["playgroup_duration"] ?? 120;

//Get per-day hours from business_hours_by_day
$bizHoursByDay = [];
$result = $connection->query("SELECT day_name, start_time, end_time FROM business_hours_by_day");
while ($row = $result->fetch_assoc()) {
    $bizHoursByDay[$row['day_name']] = [
        'start' => $row['start_time'],
        'end'   => $row['end_time']
    ];
}

$closedDays = array_keys(array_filter($bizHoursByDay, fn($v) => is_null($v['start']) || is_null($v['end'])));

$openOverrideDates = [];
$exceptions = $connection->query("SELECT exception_date FROM business_hours_exceptions WHERE start_time IS NOT NULL AND end_time IS NOT NULL");
while ($row = $exceptions->fetch_assoc()) {
    $openOverrideDates[] = $row['exception_date'];
}

//Check for a specific date override
// $overrideStmt = $connection->prepare("SELECT start_time, end_time FROM business_hours_exceptions WHERE exception_date = ?");
// $overrideStmt->bind_param("s", $date);
// $overrideStmt->execute();
// $overrideResult = $overrideStmt->get_result();
// $override = $overrideResult->fetch_assoc();

// if ($override) {
//     $start = $override['start_time'];
//     $end = $override['end_time'];
// } else {
//     $dayOfWeek = date("l", strtotime($date));
//     $start = $bizHoursByDay[$dayOfWeek]['start'];
//     $end = $bizHoursByDay[$dayOfWeek]['end'];
// }


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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="../CSS/style.css" type="text/css" />

    <style>
        html,
        body {
            background-color: #ffffff !important;
        }

        .appointment-container {
            display: flex;
            gap: 20px;
            /* Adjust as needed for spacing */
        }

        .appointment-form {
            flex: 1;
            /* Take up available space */
        }

        .patient-details-container {
            flex: 1;
            /* Take up available space */
        }

    </style>
</head>

<body>
    <div class="uk-container uk-margin-top appointment-container">
        <div class="appointment-form">
            <div>
            <h2 class="uk-card-title uk-text-bold">Book an Appointment</h2>
                <p>Your Role: <strong><?= ucfirst($role); ?></strong></p>

                <?php if (empty($patients)): ?>
                    <div class="uk-alert-warning" uk-alert style="width: 100%;">
                        <p>Please Register a Patient before Booking an Appointment.</p>
                        <button class="uk-button uk-button-primary" onclick="goToRegisterPatient()">Register a Patient</button>
                    </div>
                <?php else: ?>
            </div>

            <form id="appointmentForm" action="app_process/book_appointment_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked uk-grid-medium" uk-grid>
                <div class="uk-width-1-1">
                    <label class="uk-form-label" for="patient_id">Select Patient:</label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="patient_id" id="patient_id" required>
                            <option value="" disabled selected>Select a Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= $patient['patient_id']; ?>">
                                    <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="patient_name" id="patient_name_hidden">
                    </div>
                </div>

                <div class="uk-width-1-1">
                    <label class="uk-form-label" for="appointment_type">Appointment Type:</label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="appointment_type" id="appointment_type" required>
                            <option value="" disabled selected>Select Appointment Type</option>
                            <option value="Initial Evaluation">Initial Evaluation</option>
                            <option value="Playgroup">Playgroup</option>
                        </select>
                    </div>
                </div>

                <div class="uk-width-1-1" id="date_time_container">
                    <label class="uk-form-label" for="appointment_date">Date:</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" type="date" name="appointment_date" id="appointment_date" required>
                    </div>

                    <label class="uk-form-label" for="appointment_time">Time:</label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="appointment_time" id="appointment_time" required></select>
                    </div>
                </div>

                <div class="uk-width-1-1" id="playgroup_session_container" style="display: none;">
                    <label class="uk-form-label" for="pg_session_id">Select Playgroup Session:</label>
                    <div class="uk-form-controls">
                        <select class="uk-select" name="pg_session_id" id="pg_session_id" required>
                            <option value="" disabled selected>Fetching available sessions...</option>
                        </select>
                    </div>
                    <p><strong>Date:</strong> <span id="pg_selected_date"></span></p>
                    <p><strong>Time:</strong> <span id="pg_selected_time"></span></p>
                </div>

                <div class="uk-width-1-1" id="referralQuestion" style="display: none;">
                    <label class="uk-form-label" for="has_referral">
                        Do you have a doctor's referral?
                        <i class="fas fa-info-circle" uk-tooltip="A doctor's referral is required for Initial Evaluation. If you don't have one, provide proof of a scheduled referral appointment."></i>
                    </label>
                    <div class="uk-form-controls">
                        <select class="uk-select" id="has_referral">
                            <option value="" disabled selected>Select Answer</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>

                <div class="uk-width-1-1" id="referralUpload" style="display: none;">
                    <label class="uk-form-label" id="referralLabel">Doctor's Referral:</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" type="file" name="official_referral" id="official_referral" accept=".jpg, .jpeg, .png, .pdf">
                        <input class="uk-input" type="file" name="proof_of_booking" id="proof_of_booking" accept=".jpg, .jpeg, .png, .pdf">
                    </div>
                </div>

                <div class="uk-width-1-1 uk-flex uk-flex-right">
                    <button class="uk-button uk-button-primary uk-margin-top" type="submit" style="border-radius: 15px;">Book</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <div class="vertical-separator" style="border-left: 1px solid #ccc; height: auto; margin: 0 20px;"></div>

        <div class="patient-details-container">
            <div id="patientDetails" class="uk-margin uk-card uk-card-default uk-card-body" style="display: none; box-shadow: none;">
                <h4 style="font-weight: bold;">Patient Details</h4>
                <img id="patient_profile" src="" alt="Profile Picture" class="uk-border-rounded" style="width: 100px; height: 100px; display: none; margin: 0 auto;">
                <p><strong>Name:</strong> <span id="patient_name"></span></p>
                <p><strong>Birthday:</strong> <span id="patient_bday"></span></p>
                <p><strong>Age:</strong> <span id="patient_age"></span></p>
                <p><strong>Gender:</strong> <span id="patient_gender"></span></p>
                <p><strong>Service Type:</strong> <span id="patient_service"></span></p>
            </div>
        </div>
    </div>

    <script>
    // Function to switch the main page section (Keep this)
    function goToRegisterPatient() {
        parent.document.querySelectorAll('.section').forEach(section => {
            section.style.display = 'none';
        });
        parent.document.getElementById("register-patient").style.display = "block";
    }

    // Global settings variables (Keep these)
    let openOverrideDates = <?= json_encode($openOverrideDates) ?>;
    let closedDays = <?= json_encode($closedDays) ?>;
    let blockedDates = <?= json_encode($blockedDates) ?>;
    let minDaysAdvance = <?= $minDaysAdvance ?>;
    let maxDaysAdvance = <?= $maxDaysAdvance ?>;

    console.log("closedDays", closedDays);

    // ==================================================
    // MAIN DOMContentLoaded Event Listener
    // ==================================================
    document.addEventListener("DOMContentLoaded", function () {

        // --- Element References ---
        const patientDropdown = document.getElementById("patient_id");
        const appointmentTypeDropdown = document.getElementById("appointment_type");
        const appointmentDateInput = document.getElementById("appointment_date");
        const timeSelect = document.getElementById("appointment_time");
        const dateTimeContainer = document.getElementById("date_time_container");
        const playgroupSessionContainer = document.getElementById("playgroup_session_container");
        const playgroupSessionDropdown = document.getElementById("pg_session_id");
        const selectedDateDisplay = document.getElementById("pg_selected_date");
        const selectedTimeDisplay = document.getElementById("pg_selected_time");
        const referralQuestion = document.getElementById("referralQuestion");
        const referralUpload = document.getElementById("referralUpload");
        const hasReferral = document.getElementById("has_referral");
        const referralLabel = document.getElementById("referralLabel");
        const officialReferralInput = document.getElementById("official_referral");
        const proofOfBookingInput = document.getElementById("proof_of_booking");
        const patientDetailsDiv = document.getElementById("patientDetails");
        const patientNameSpan = document.getElementById("patient_name");
        const patientBdaySpan = document.getElementById("patient_bday");
        const patientAgeSpan = document.getElementById("patient_age");
        const patientGenderSpan = document.getElementById("patient_gender");
        const patientServiceSpan = document.getElementById("patient_service");
        const patientProfileImg = document.getElementById("patient_profile");
        const patientNameHiddenInput = document.getElementById("patient_name_hidden");
        const form = document.getElementById("appointmentForm");

        // --- Initialize Flatpickr (but keep date input disabled initially if needed) ---
        let today = new Date();
        let minDate = new Date(today);
        minDate.setDate(today.getDate() + minDaysAdvance);
        minDate.setHours(0, 0, 0, 0);
        let maxDate = new Date();
        maxDate.setDate(today.getDate() + maxDaysAdvance);
        maxDate.setHours(0, 0, 0, 0);

        const fpInstance = flatpickr("#appointment_date", { // Store instance
            minDate: minDate.toISOString().split("T")[0],
            maxDate: maxDate.toISOString().split("T")[0],
            dateFormat: "Y-m-d",
            disable: [ /* ... disable logic kept same ... */
                function(date) {
                    const isoDate = date.toISOString().split('T')[0];
                    const dayOfWeek = date.toLocaleDateString('en-US', { weekday: 'long' });
                    if (blockedDates.includes(isoDate)) return true;
                    if (openOverrideDates.includes(isoDate)) return false;
                    if (closedDays.includes(dayOfWeek)) return true;
                    return false;
                }
            ],
            onChange: function (selectedDates, dateStr) {
                updateAvailableTimes(dateStr); // This only runs if date input is enabled
            }
            // Note: Flatpickr might need to be enabled/disabled programmatically
        });

        // --- Initial Form State ---
        dateTimeContainer.style.display = 'none'; // Hide date/time initially
        referralQuestion.style.display = 'none';
        referralUpload.style.display = 'none';
        playgroupSessionContainer.style.display = 'none';

        // --- Function: Check Referral Requirements and Control Date/Time Visibility ---
        function checkAndControlDateTimeVisibility() {
            const selectedType = appointmentTypeDropdown.value;

            if (selectedType === 'Initial Evaluation') {
                const hasReferralAnswer = hasReferral.value; // "yes" or "no"
                const officialFileExists = officialReferralInput.files.length > 0;
                const proofFileExists = proofOfBookingInput.files.length > 0;

                let requirementMet = false;
                if (hasReferralAnswer === 'yes' && officialFileExists) {
                    requirementMet = true;
                } else if (hasReferralAnswer === 'no' && proofFileExists) {
                    requirementMet = true;
                }

                if (requirementMet) {
                    dateTimeContainer.style.display = 'block'; // Show date/time
                    appointmentDateInput.disabled = false; // Ensure date input is enabled
                    // We might need to explicitly tell flatpickr to enable if it was disabled
                     if (fpInstance && fpInstance.input.disabled) {
                         // This might not directly work, depends on flatpickr version.
                         // Alternative: Destroy and re-initialize, or find enable method.
                         // Simpler: Control via the input's disabled property before initializing?
                         // Or just rely on the input's disabled state.
                         console.log("Enabling date input (already done via property)");
                     }

                } else {
                    dateTimeContainer.style.display = 'none'; // Hide date/time
                    appointmentDateInput.disabled = true;  // Disable date input
                    appointmentDateInput.value = ''; // Clear date
                    timeSelect.innerHTML = '';       // Clear time
                    timeSelect.disabled = true;
                    // fpInstance?.input?.setAttribute('disabled', 'disabled'); // Ensure flatpickr visually disabled?
                }
            } else {
                // If not Initial Evaluation, hide referral and date/time (unless it's Playgroup)
                referralQuestion.style.display = 'none';
                referralUpload.style.display = 'none';
                if (selectedType !== 'Playgroup') {
                     dateTimeContainer.style.display = 'none'; // Hide if not PG either
                }
            }
        }


        // --- Function: Update Available Time Slots (Modified - No referral logic here) ---
        async function updateAvailableTimes(dateStr) {
            // This function now assumes that if it's called, the date input is visible/enabled,
            // meaning referral requirements (if IE) were already met.
            timeSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
            timeSelect.disabled = true;
            const selectedType = appointmentTypeDropdown.value; // Should be IE if this runs

            if (!dateStr || !selectedType || selectedType === "Playgroup") { // Should not be PG here
                 return; // Exit if called inappropriately
            }

            // Referral visibility is handled elsewhere now

            try {
                const response = await fetch(`app_data/get_available_slots_for_day.php?date=${dateStr}&appointment_type=${encodeURIComponent(selectedType)}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                // EXPECTING { status: "success", available_slots: ["09:00:00", "10:00:00", ...] }
                const data = await response.json();
                timeSelect.innerHTML = '';

                if (data.status === "success" && data.available_slots && data.available_slots.length > 0) {
                    timeSelect.innerHTML = '<option value="" disabled selected>Select a Time</option>';
                    data.available_slots.forEach(slot_hhmmss => { // slot_hhmmss is like "09:00:00"
                        const [hours, minutes, seconds] = slot_hhmmss.split(':');

                        // Format for DISPLAY (AM/PM)
                        const hoursInt = parseInt(hours);
                        const ampm = hoursInt >= 12 ? 'PM' : 'AM';
                        const formattedHour = hoursInt % 12 === 0 ? 12 : hoursInt % 12;
                        const displayTime = `${formattedHour}:${minutes} ${ampm}`; // Show AM/PM to user

                        const option = document.createElement("option");
                        // **** STORE the HH:MM:SS string as the VALUE ****
                        option.value = slot_hhmmss; // Use the value PHP expects for the DB
                        option.textContent = displayTime; // Show the user-friendly format
                        timeSelect.appendChild(option);
                    });
                    timeSelect.disabled = false;
                } else if (data.status === "fully_booked") {
                    timeSelect.innerHTML = '<option value="" disabled selected>Fully Booked</option>';
                    timeSelect.disabled = true;
                    if (selectedType === 'Initial Evaluation') { // Still check type for safety
                        showWaitlistPopup(dateStr, selectedType); // Call simplified waitlist popup
                    } else { /* Handle other types if necessary */ }
                } else if (data.status === "closed") {
                    timeSelect.innerHTML = '<option value="" disabled selected>Center Closed</option>';
                    timeSelect.disabled = true;
                } else { /* Handle errors */
                    timeSelect.innerHTML = '<option value="" disabled selected>Error loading times</option>';
                    timeSelect.disabled = true;
                    console.error("Error fetching slots:", data.message);
                     Swal.fire("Error", data.message || "Could not load available times.", "error");
                }
            } catch (error) { /* Handle fetch errors */
                 console.error("Fetch error:", error);
                 timeSelect.innerHTML = '<option value="" disabled selected>Network Error</option>';
                 timeSelect.disabled = true;
                 Swal.fire("Error", "Could not connect to server.", "error");
            }
        }

        // --- Function: Show Waitlist Popup (Simplified - Referral check removed) ---
        function showWaitlistPopup(dateStr, appointmentType) {
            // Assumes referral requirement was met before date was selected if type is IE
            const patientId = patientDropdown.value;
            const patientName = patientDropdown.options[patientDropdown.selectedIndex]?.text || 'Selected Patient';

            if (!patientId) { /* ... handle missing patient ... */
                 Swal.fire("Select Patient", "Please select a patient first.", "warning");
                 return;
             }

            // No need to check referral details *here* anymore

            Swal.fire({
                title: 'Date Fully Booked',
                html: `The date <b>${dateStr}</b> is fully booked for <b>${appointmentType}</b> for <b>${patientName}</b>.<br><br>Would you like to join the waitlist?`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: `Waitlist for ${dateStr}`,
                confirmButtonColor: '#3085d6',
                cancelButtonText: 'Cancel',
                showDenyButton: true,
                denyButtonText: 'Waitlist for Any Day',
                denyButtonColor: '#5cb85c',
            }).then((result) => {
                if (result.isConfirmed) {
                    submitWaitlistRequest('specific_date', dateStr);
                } else if (result.isDenied) {
                    submitWaitlistRequest('any_day', null);
                } else { // User cancelled
                    appointmentDateInput.value = ''; // Clear date
                    timeSelect.innerHTML = '';
                    timeSelect.disabled = true;
                }
            });
        }

        // --- Function: Submit Waitlist Request (Keep As Is) ---
        async function submitWaitlistRequest(waitlistType, specificDate) {
            // ... (This function remains the same - it just sends form data) ...
            const formData = new FormData(form);
            formData.append('waitlist_type', waitlistType);
            if (specificDate) formData.append('specific_date', specificDate);
            formData.append('action', 'request_waitlist');
            const endpoint = "app_process/book_appointment_process.php";
             Swal.fire({ title: 'Submitting Waitlist Request...', text: 'Please wait.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const response = await fetch(endpoint, { method: 'POST', body: formData });
                const result = await response.json();
                Swal.close();
                if (result.status === 'success') {
                    Swal.fire({ title: 'Success!', text: result.message || 'Added to waitlist.', icon: 'success' }).then(() => {
                        form.reset(); // Reset entire form might be best here
                        // Manually hide sections again after reset if needed
                        dateTimeContainer.style.display = 'none';
                        referralQuestion.style.display = 'none';
                        referralUpload.style.display = 'none';
                        playgroupSessionContainer.style.display = 'none';
                        patientDetailsDiv.style.display = 'none'; // Hide patient details after reset too
                    });
                } else { Swal.fire('Error', result.message || 'Failed to add to waitlist.', 'error'); }
            } catch (error) { Swal.close(); console.error("Waitlist submission error:", error); Swal.fire('Error', 'Network error.', 'error'); }
        }


        // --- Function: Fetch and Display Patient Details (Keep As Is) ---
        let isFetchingPatientDetails = false;
        function fetchPatientDetails(patientID) { /* ... Keep implementation ... */
            if (!patientID || isFetchingPatientDetails) { patientDetailsDiv.style.display = "none"; return; }
            isFetchingPatientDetails = true; patientDetailsDiv.style.display = "none";
            fetch("patient/patient_data/fetch_patient_details.php?patient_id=" + patientID)
            .then(response => { if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`); return response.text(); })
            .then(text => { try { return JSON.parse(text); } catch (e) { console.error("Invalid JSON:", text); throw new Error("Bad data."); }})
            .then(data => {
                if (data.status === "success" && data.patient) { /* ... populate fields ... */
                    let fullName = data.patient.first_name + " " + data.patient.last_name;
                    patientNameSpan.textContent = fullName; patientNameHiddenInput.value = fullName;
                    patientGenderSpan.textContent = data.patient.gender || "N/A"; patientServiceSpan.textContent = data.patient.service_type || "N/A";
                    let birthdate = data.patient.bday; let age = "N/A"; if (birthdate) { try { /* ... age calc ... */ let birthDateObj = new Date(birthdate); let today = new Date(); age = today.getFullYear() - birthDateObj.getFullYear(); let m = today.getMonth() - birthDateObj.getMonth(); if (m < 0 || (m === 0 && today.getDate() < birthDateObj.getDate())) age--; } catch(e){} }
                    patientBdaySpan.textContent = birthdate || "N/A"; patientAgeSpan.textContent = age;
                    if (data.patient.profile_picture) { patientProfileImg.src = "../uploads/profile_pictures/" + data.patient.profile_picture; patientProfileImg.style.display = "block"; } else { patientProfileImg.style.display = "none"; patientProfileImg.src = ""; }
                    patientDetailsDiv.style.display = "block";
                } else { console.error("Failed fetch:", data.message); }})
            .catch(error => { console.error("Fetch error:", error); })
            .finally(() => { isFetchingPatientDetails = false; });
        }

        // --- Function: Check Patient History (Keep As Is) ---
        function checkPatientHistory(patientID) { /* ... Keep implementation ... */
             if (!patientID) return;
             fetch(`patient/patient_data/check_patient_history.php?patient_id=${patientID}`)
             .then(response => { if (!response.ok) throw new Error('Network fail'); return response.json(); })
             .then(data => { let ieOption = appointmentTypeDropdown.querySelector("option[value='Initial Evaluation']"); let pgOption = appointmentTypeDropdown.querySelector("option[value='Playgroup']"); if (ieOption) { ieOption.disabled = data.completed_ie; if (data.completed_ie && appointmentTypeDropdown.value === "Initial Evaluation") { if (pgOption && !pgOption.disabled) { appointmentTypeDropdown.value = "Playgroup"; appointmentTypeDropdown.dispatchEvent(new Event('change')); } else { appointmentTypeDropdown.value = ""; } } } })
             .catch(error => { console.error("History fetch error:", error); });
        }

        // --- Function: Check Existing Bookings (Keep As Is) ---
        let isCheckingExisting = false;
        function checkExistingAppointment() { /* ... Keep implementation ... */
            let patientID = patientDropdown.value; let selectedAppointmentType = appointmentTypeDropdown.value; if (!patientID || !selectedAppointmentType || isCheckingExisting) return; isCheckingExisting = true;
            fetch(`app_data/check_existing_appointment.php?patient_id=${patientID}&appointment_type=${selectedAppointmentType}`)
            .then(response => response.json()).then(data => { if (data.status === "error") { Swal.fire({ /* ... Swal config ... */ title: "Booking Not Allowed", html: `<p>${data.message}</p><p><strong>Existing:</strong> ${data.existing_session_type} (${data.existing_status}) on ${data.existing_date || ''} at ${data.existing_time || 'N/A'}</p>`, icon: "warning" }).then(() => { /* ... reset fields ... */ appointmentTypeDropdown.value = ""; appointmentDateInput.value = ''; timeSelect.innerHTML = ""; timeSelect.disabled = true; referralQuestion.style.display = "none"; referralUpload.style.display = "none"; hasReferral.value = ""; officialReferralInput.value = ""; proofOfBookingInput.value = ""; playgroupSessionContainer.style.display = 'none'; dateTimeContainer.style.display = 'none'; }); } })
            .catch(error => { console.error("Check existing error:", error); Swal.fire("Error", "Check failed.", "error"); })
            .finally(() => { isCheckingExisting = false; });
        }

        // --- Function: Fetch Playgroup Sessions (Keep As Is) ---
        function fetchOpenPlaygroupSessions() { /* ... Keep implementation ... */
             playgroupSessionDropdown.innerHTML = '<option value="" disabled selected>Fetching...</option>';
             fetch("app_data/get_open_playgroup_sessions.php").then(r => r.json()).then(d => { playgroupSessionDropdown.innerHTML = ""; if (d.status === "success" && d.sessions.length > 0) { d.sessions.forEach(s => { let o = document.createElement("option"); o.value = s.pg_session_id; o.textContent = `${s.date} at ${s.time} (${s.current_count}/${s.max_capacity})`; playgroupSessionDropdown.appendChild(o); }); if (d.sessions.length > 0) { playgroupSessionDropdown.value = d.sessions[0].pg_session_id; playgroupSessionDropdown.dispatchEvent(new Event('change')); } } else { playgroupSessionDropdown.innerHTML = '<option value="" disabled selected>No open sessions</option>'; selectedDateDisplay.textContent = ''; selectedTimeDisplay.textContent = ''; } }).catch(e => { console.error("PG fetch error:", e); playgroupSessionDropdown.innerHTML = '<option value="" disabled>Error</option>'; });
         }


        // --- Event Listeners ---

        // ** Patient Dropdown Change **
        patientDropdown.addEventListener("change", function () {
            const patientID = this.value;
            fetchPatientDetails(patientID); // Fetch details
            checkPatientHistory(patientID); // Check history (may disable IE)
            appointmentTypeDropdown.value = ""; // Reset appointment type
            // Hide all conditional sections
            dateTimeContainer.style.display = 'none';
            playgroupSessionContainer.style.display = 'none';
            referralQuestion.style.display = 'none';
            referralUpload.style.display = 'none';
            // Clear specific inputs
            appointmentDateInput.value = '';
            timeSelect.innerHTML = '';
            timeSelect.disabled = true;
            hasReferral.value = '';
            officialReferralInput.value = '';
            proofOfBookingInput.value = '';
            checkExistingAppointment(); // Check if *new* selection allows anything
        });

        // ** Appointment Type Dropdown Change ** (MODIFIED)
        appointmentTypeDropdown.addEventListener("change", function () {
            const selectedType = this.value;

            // Reset dependent sections first
            dateTimeContainer.style.display = 'none';
            appointmentDateInput.value = ''; appointmentDateInput.disabled = true; // Keep disabled
            timeSelect.innerHTML = ''; timeSelect.disabled = true;
            playgroupSessionContainer.style.display = 'none';
            referralQuestion.style.display = 'none';
            referralUpload.style.display = 'none';
            hasReferral.value = '';
            officialReferralInput.value = '';
            proofOfBookingInput.value = '';

            if (selectedType === "Playgroup") {
                playgroupSessionContainer.style.display = "block";
                playgroupSessionDropdown.required = true; // Make required
                 dateTimeContainer.style.display = "none"; // Ensure hidden
                 appointmentDateInput.required = false; // Not required
                 timeSelect.required = false; // Not required
                fetchOpenPlaygroupSessions();
            } else if (selectedType === "Initial Evaluation") {
                playgroupSessionDropdown.required = false; // Not required
                appointmentDateInput.required = true; // Required
                timeSelect.required = true; // Required
                referralQuestion.style.display = "block"; // Show referral Q first
                // Keep date/time hidden until referral is handled via checkAndControl...
                checkAndControlDateTimeVisibility(); // Check if already met
            } else {
                 // No type selected or another type without special handling
                 playgroupSessionDropdown.required = false;
                 appointmentDateInput.required = false;
                 timeSelect.required = false;
            }
            checkExistingAppointment(); // Check for conflicts with the new type
        });

        // ** Referral Question Change ** (MODIFIED)
        hasReferral.addEventListener("change", function () {
            // Show/hide correct upload input
            if (hasReferral.value === "yes") {
                referralUpload.style.display = "block";
                referralLabel.textContent = "Upload Doctor's Referral:";
                officialReferralInput.style.display = "block";
                proofOfBookingInput.style.display = "none";
                proofOfBookingInput.value = ''; // Clear other file input
            } else if (hasReferral.value === "no") {
                referralUpload.style.display = "block";
                referralLabel.textContent = "Upload Proof of Booking:";
                officialReferralInput.style.display = "none";
                officialReferralInput.value = ''; // Clear other file input
                proofOfBookingInput.style.display = "block";
            } else {
                referralUpload.style.display = "none";
                 officialReferralInput.value = '';
                 proofOfBookingInput.value = '';
            }
            // Check if date/time can now be shown/enabled
            checkAndControlDateTimeVisibility();
        });

         // ** File Input Changes ** (NEW LISTENERS)
         officialReferralInput.addEventListener('change', checkAndControlDateTimeVisibility);
         proofOfBookingInput.addEventListener('change', checkAndControlDateTimeVisibility);


        // ** Playgroup Session Dropdown Change ** (Keep As Is)
        playgroupSessionDropdown.addEventListener("change", function () { /* ... keep implementation ... */
             let selectedOption = this.options[this.selectedIndex]; let sessionText = selectedOption.textContent.match(/(\d{4}-\d{2}-\d{2}) at (\d{2}:\d{2}:\d{2})/); if (sessionText) { selectedDateDisplay.textContent = sessionText[1]; selectedTimeDisplay.textContent = sessionText[2]; } else { selectedDateDisplay.textContent = 'N/A'; selectedTimeDisplay.textContent = 'N/A'; }
         });


        // ** Form Submit Validation (Keep final checks, ensure inputs are enabled) **
        form.addEventListener("submit", function (e) {
            const appointmentType = appointmentTypeDropdown.value;
            const isPlaygroup = appointmentType === 'Playgroup';

            if (isPlaygroup) {
                 // Check if a session is selected for Playgroup
                 if (!playgroupSessionDropdown.value) {
                     e.preventDefault();
                     Swal.fire("Incomplete", "Please select a Playgroup session.", "warning");
                     return;
                 }
            } else { // If not Playgroup (must be IE based on current types)
                 // Check if date/time inputs are enabled and have values
                 if (appointmentDateInput.disabled || !appointmentDateInput.value || timeSelect.disabled || !timeSelect.value) {
                    e.preventDefault();
                    Swal.fire("Incomplete", "Please complete the referral information and select an available date and time.", "warning");
                    return;
                 }

                // Re-validate referral for safety net
                const hasReferralSelected = hasReferral.value;
                const officialReferralFile = officialReferralInput.files[0];
                const proofOfBookingFile = proofOfBookingInput.files[0];
                if (!hasReferralSelected) { e.preventDefault(); Swal.fire("Missing Info", "Referral answer missing.", "warning"); return; }
                if (hasReferralSelected === "yes" && !officialReferralFile) { e.preventDefault(); Swal.fire("Missing Upload", "Referral file missing.", "warning"); return; }
                if (hasReferralSelected === "no" && !proofOfBookingFile) { e.preventDefault(); Swal.fire("Missing Upload", "Proof of booking missing.", "warning"); return; }
            }

            // If all checks pass, show loading and submit
             Swal.fire({ title: 'Submitting Request...', text: 'Please wait.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        });

    }); // End DOMContentLoaded

    </script>

</body>

</html>
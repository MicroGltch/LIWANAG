<?php
    require_once "../dbconfig.php"; // Adjust path if needed
    session_start();

    // --- Authentication and Authorization ---
    if (!isset($_SESSION['account_ID'])) {
        header("Location: ../Accounts/loginpage.php"); // Adjust path
        exit();
    }
    if (strtolower($_SESSION['account_Type']) !== "client") {
        // Redirect non-clients or show an error
        header("Location: ../dashboard.php"); // Adjust path to a suitable page
        exit();
    }

    $account_id = $_SESSION['account_ID'];
    $role = strtolower(trim($_SESSION['account_Type'])); // Should be 'client'

    // --- Fetch Settings ---
    // Using prepared statement for safety, although less critical here
    $settings = null;
    $stmt_settings = $connection->prepare("SELECT max_days_advance, min_days_advance, blocked_dates, initial_eval_duration, playgroup_duration FROM settings LIMIT 1");
    if ($stmt_settings && $stmt_settings->execute()) {
        $settingsResult = $stmt_settings->get_result();
        $settings = $settingsResult->fetch_assoc();
        $stmt_settings->close();
    }
    if (!$settings) {
        // Handle error fetching settings (e.g., log, show error message)
        die("Error: Could not load application settings.");
    }

    $maxDaysAdvance = $settings["max_days_advance"] ?? 30;
    $minDaysAdvance = $settings["min_days_advance"] ?? 3;
    $blockedDates = !empty($settings["blocked_dates"]) ? json_decode($settings["blocked_dates"], true) : [];
    // Durations are used more in the backend slot calculation now

    // --- Fetch Business Hours & Exceptions ---
    $bizHoursByDay = [];
    $closedDays = [];
    $openOverrideDates = [];

    try {
        // Get per-day hours
        $resultHours = $connection->query("SELECT day_name, start_time, end_time FROM business_hours_by_day");
        while ($row = $resultHours->fetch_assoc()) {
            $bizHoursByDay[strtolower($row['day_name'])] = [ // Use lowercase day names consistently
                'start' => $row['start_time'],
                'end'   => $row['end_time']
            ];
            if (is_null($row['start_time']) || is_null($row['end_time'])) {
                $closedDays[] = strtolower($row['day_name']);
            }
        }

        // Get open exceptions (dates when the clinic IS open even if normally closed)
        $exceptions = $connection->query("SELECT exception_date FROM business_hours_exceptions WHERE start_time IS NOT NULL AND end_time IS NOT NULL");
        while ($row = $exceptions->fetch_assoc()) {
            $openOverrideDates[] = $row['exception_date'];
        }

         // Get closed exceptions (dates when clinic is closed even if normally open)
         // Combine these with blockedDates for flatpickr disable logic
         $closedExceptionDates = [];
         $closedExQuery = "SELECT exception_date FROM business_hours_exceptions WHERE start_time IS NULL OR end_time IS NULL";
         $closedExResult = $connection->query($closedExQuery);
         while ($row = $closedExResult->fetch_assoc()) {
            $closedExceptionDates[] = $row['exception_date'];
         }
         $blockedDates = array_unique(array_merge($blockedDates, $closedExceptionDates));


    } catch (Exception $e) {
        // Log error and handle
        error_log("Error fetching business hours: " . $e->getMessage());
        die("Error loading business hours configuration.");
    }


    // --- Fetch Registered Patients for this Client ---
    $patients = [];
    $stmt_patients = $connection->prepare("SELECT patient_id, first_name, last_name, status FROM patients WHERE account_id = ? ORDER BY first_name, last_name");
    if ($stmt_patients) {
        $stmt_patients->bind_param("i", $account_id);
        if ($stmt_patients->execute()) {
            $resultPatients = $stmt_patients->get_result();
            $patients = $resultPatients->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("Error executing patients query: " . $stmt_patients->error);
        }
        $stmt_patients->close();
    } else {
        error_log("Error preparing patients query: " . $connection->error);
    }

    $connection->close(); // Close connection after fetching initial data
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment</title>
    <!-- UIkit CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.19.2/dist/css/uikit.min.css" />
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- FontAwesome -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script> <!-- Get your kit code -->
     <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">
    <!-- Your Custom CSS -->
    <link rel="stylesheet" href="../CSS/style.css" type="text/css" /> <!-- Adjust path -->

    <style>
        html, body { background-color: #ffffff !important; }
        .appointment-container { display: flex; gap: 30px; flex-wrap: wrap; }
        .appointment-form { flex: 2; min-width: 300px; }
        .patient-details-container { flex: 1; min-width: 250px; }
        .pending-slot-option { color: #cc7a00; font-style: italic; } /* Example style */
        label { font-weight: bold; margin-bottom: 5px; display: block; }
        .uk-select, .uk-input { margin-bottom: 15px; }
        .fa-info-circle { margin-left: 5px; color: #1e87f0; cursor: help; }
    </style>
</head>
<body>
    <div class="uk-container uk-margin-large-top appointment-container">

        <!-- Appointment Booking Form Section -->
        <div class="appointment-form uk-card uk-card-default uk-card-body">
            <h2 class="uk-card-title">Book an Appointment</h2>
            <!-- <p>Your Role: <strong><?= ucfirst($role); ?></strong></p> -->

            <?php if (empty($patients)): ?>
                <div class="uk-alert-warning" uk-alert>
                    <a class="uk-alert-close" uk-close></a>
                    <p>You currently have no registered patients eligible for booking. Please register a patient first.</p>
                     <!-- Button might link to a registration page or trigger a modal -->
                    <button class="uk-button uk-button-primary uk-button-small" onclick="goToRegisterPatient()">Register a Patient</button>
                </div>
            <?php else: ?>

            <form id="appointmentForm" action="app_process/book_appointment_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked">
                 <!-- Patient Selection -->
                <div class="uk-margin">
                    <label for="patient_id">Select Patient:</label>
                    <select class="uk-select" name="patient_id" id="patient_id" required>
                        <option value="" disabled selected>-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= htmlspecialchars($patient['patient_id']); ?>" data-status="<?= htmlspecialchars($patient['status']); ?>">
                                <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="patient_name" id="patient_name_hidden">
                </div>

                <!-- Appointment Type Selection -->
                <div class="uk-margin">
                    <label for="appointment_type">Appointment Type:</label>
                    <select class="uk-select" name="appointment_type" id="appointment_type" required>
                        <option value="" disabled selected>-- Select Type --</option>
                        <option value="IE-OT">Initial Evaluation - OT</option>
                        <option value="IE-BT">Initial Evaluation - BT</option>
                        <option value="Playgroup">Playgroup</option>
                    </select>
                </div>

                <!-- Playgroup Session Selection (Conditional) -->
                <div id="playgroup_session_container" class="uk-margin" style="display: none;">
                    <label for="pg_session_id">Select Playgroup Session:</label>
                    <select class="uk-select" name="pg_session_id" id="pg_session_id">
                        <option value="" disabled selected>Fetching available sessions...</option>
                    </select>
                    <p class="uk-text-small uk-margin-small-top"><strong>Date:</strong> <span id="pg_selected_date">N/A</span></p>
                    <p class="uk-text-small"><strong>Time:</strong> <span id="pg_selected_time">N/A</span></p>
                </div>

                 <!-- Referral Question (Conditional for IE-OT/BT) -->
                <div id="referralQuestion" class="uk-margin" style="display: none;">
                    <label for="has_referral">
                        Do you have a doctor's referral for this Initial Evaluation?
                        <i class="fas fa-info-circle" uk-tooltip="title: A doctor's referral is needed. If awaiting an appointment, upload proof of booking.; pos: right"></i>
                    </label>
                    <select class="uk-select" id="has_referral" name="has_referral"> <!-- Added name -->
                        <option value="" disabled selected>-- Select Answer --</option>
                        <option value="yes">Yes, I have the referral</option>
                        <option value="no">No, but I have proof of a scheduled appointment</option>
                    </select>
                </div>

                 <!-- Referral Upload (Conditional for IE-OT/BT) -->
                <div id="referralUpload" class="uk-margin" style="display: none;">
                    <label id="referralLabel" for="referral_file">Upload Document:</label> <!-- Changed label -->
                     <div uk-form-custom="target: true">
                         <input type="file" name="referral_file" id="referral_file" accept=".jpg, .jpeg, .png, .pdf"> <!-- Single input -->
                         <input class="uk-input uk-form-width-medium" type="text" placeholder="Select file" disabled>
                         <button class="uk-button uk-button-default" type="button" tabindex="-1">Select</button>
                    </div>
                     <p class="uk-text-meta">Allowed formats: JPG, PNG, PDF.</p>
                    <input type="hidden" name="referral_upload_type" id="referral_upload_type"> <!-- Hidden input to track type -->
                </div>

                <!-- Date and Time Selection (Conditional for IE-OT/BT) -->
                <div id="date_time_container" style="display: none;">
                    <div class="uk-margin">
                        <label for="appointment_date">Select Date:</label>
                        <input class="uk-input" type="text" name="appointment_date" id="appointment_date" placeholder="Select Date..." > <!-- Changed type to text for flatpickr -->
                    </div>
                    <div class="uk-margin">
                        <label for="appointment_time">Select Available Time:</label>
                        <select class="uk-select" name="appointment_time" id="appointment_time">
                           <option value="" disabled selected>-- Select Date First --</option>
                        </select>
                    </div>
                </div>

                 <!-- Submission Button -->
                <div class="uk-margin uk-text-right">
                    <button class="uk-button uk-button-primary" type="submit" id="submitButton">Request Appointment</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <!-- Patient Details Display Section -->
        <div class="patient-details-container">
            <div id="patientDetails" class="uk-card uk-card-default uk-card-body" style="display: none;">
                <h4 class="uk-card-title">Patient Details</h4>
                <img id="patient_profile" src="" alt="Profile Picture" class="uk-border-circle uk-align-right" style="width: 80px; height: 80px; object-fit: cover; display: none;">
                <p><strong>Name:</strong> <span id="patient_name"></span></p>
                <p><strong>Birthday:</strong> <span id="patient_bday"></span></p>
                <p><strong>Age:</strong> <span id="patient_age"></span></p>
                <p><strong>Gender:</strong> <span id="patient_gender"></span></p>
                <p><strong>Service Type:</strong> <span id="patient_service"></span></p>
                 <p><strong>Status:</strong> <span id="patient_status"></span></p>

            </div>
        </div>
    </div>


    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.19.2/dist/js/uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/uikit@3.19.2/dist/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


    <script>
    // --- Global Configuration (from PHP) ---
    const config = {
        apiEndpoints: {
            getSlots: 'app_data/get_available_slots_enhanced.php', // <-- NEW Endpoint
            getPlaygroupSessions: 'app_data/get_open_playgroup_sessions.php',
            checkExisting: 'app_data/check_existing_appointment.php',
            getPatientDetails: 'patient/patient_data/fetch_patient_details.php', // Adjust path
            checkPatientHistory: 'patient/patient_data/check_patient_history.php', // Adjust path
            submitForm: 'app_process/book_appointment_process.php' // Adjust path
        },
        flatpickr: {
            minDaysAdvance: <?= $minDaysAdvance; ?>,
            maxDaysAdvance: <?= $maxDaysAdvance; ?>,
            blockedDates: <?= json_encode($blockedDates); ?>,
            closedDays: <?= json_encode($closedDays); ?>, // Array of lowercase day names ['sunday', 'monday'...]
            openOverrideDates: <?= json_encode($openOverrideDates); ?>
        },
        debug: true // Set to false in production
    };

    // --- Utility Functions ---
    function log(...args) {
        if (config.debug) {
            console.log('[BookingForm]', ...args);
        }
    }

    function goToRegisterPatient() {
        // Assumes parent iframe structure, adjust if not used
        try {
             parent.document.querySelectorAll('.section').forEach(section => {
                 section.style.display = 'none';
             });
             parent.document.getElementById("register-patient").style.display = "block";
        } catch (e) {
            log("Could not switch section in parent:", e);
            // Fallback: Redirect if not in iframe?
            // window.location.href = '../Patients/registration_page.php'; // Adjust path
        }
    }

    function showLoading(message = 'Processing...') {
        Swal.fire({
            title: message,
            text: 'Please wait.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    }

    function hideLoading() {
        Swal.close();
    }

    // --- DOM Element References ---
    let patientDropdown, appointmentTypeDropdown, appointmentDateInput, timeSelect, dateTimeContainer,
        playgroupSessionContainer, playgroupSessionDropdown, selectedDateDisplay, selectedTimeDisplay,
        referralQuestion, referralUpload, hasReferral, referralLabel, referralFileInput, referralUploadTypeHidden,
        patientDetailsDiv, patientNameSpan, patientBdaySpan, patientAgeSpan, patientGenderSpan,
        patientServiceSpan, patientStatusSpan, patientProfileImg, patientNameHiddenInput, form, submitButton,
        fpInstance = null; // Flatpickr instance


    // --- Core Logic Functions ---

    function fetchPatientDetails(patientID) {
         // Clear previous details and hide section
         patientDetailsDiv.style.display = "none";
         ['patient_name', 'patient_bday', 'patient_age', 'patient_gender', 'patient_service', 'patient_status'].forEach(id => {
             document.getElementById(id).textContent = 'N/A';
         });
         patientProfileImg.style.display = "none";
         patientNameHiddenInput.value = '';

        if (!patientID) return;

        log(`Fetching details for patient ID: ${patientID}`);
        fetch(`${config.apiEndpoints.getPatientDetails}?patient_id=${patientID}`)
            .then(response => response.ok ? response.json() : Promise.reject(`HTTP error! Status: ${response.status}`))
            .then(data => {
                log("Patient details response:", data);
                if (data.status === "success" && data.patient) {
                    const p = data.patient;
                    let fullName = `${p.first_name || ''} ${p.last_name || ''}`.trim();
                    patientNameSpan.textContent = fullName || 'N/A';
                    patientNameHiddenInput.value = fullName;
                    patientBdaySpan.textContent = p.bday || 'N/A';
                    patientGenderSpan.textContent = p.gender || 'N/A';
                    patientServiceSpan.textContent = p.service_type || 'N/A';
                    patientStatusSpan.textContent = p.status ? p.status.charAt(0).toUpperCase() + p.status.slice(1) : 'N/A'; // Capitalize

                    // Calculate Age
                    let age = "N/A";
                    if (p.bday) {
                        try {
                            const birthDate = new Date(p.bday);
                            const today = new Date();
                            let years = today.getFullYear() - birthDate.getFullYear();
                            const m = today.getMonth() - birthDate.getMonth();
                            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                                years--;
                            }
                            age = years >= 0 ? `${years} years old` : 'N/A'; // Handle potential future date?
                        } catch (e) { log("Error calculating age:", e); }
                    }
                    patientAgeSpan.textContent = age;

                    // Profile Picture
                    if (p.profile_picture) {
                        patientProfileImg.src = `../uploads/profile_pictures/${p.profile_picture}`; // Adjust path
                        patientProfileImg.onerror = () => { patientProfileImg.style.display = 'none'; }; // Hide if broken
                        patientProfileImg.onload = () => { patientProfileImg.style.display = 'block'; };
                    } else {
                         patientProfileImg.style.display = 'none';
                    }

                    patientDetailsDiv.style.display = "block";
                } else {
                    log("Failed to fetch patient details:", data.message);
                }
            })
            .catch(error => {
                log("Fetch error (Patient Details):", error);
                // Optionally show error to user
            });
    }

     function checkPatientHistory(patientID) {
         if (!patientID) return;
         log(`Checking history for patient ID: ${patientID}`);
         // This endpoint should return { completed_ie_ot: true/false, completed_ie_bt: true/false }
         fetch(`${config.apiEndpoints.checkPatientHistory}?patient_id=${patientID}`)
             .then(response => response.ok ? response.json() : Promise.reject('Network error'))
             .then(data => {
                 log('Patient history data:', data);
                 let ieOtOption = appointmentTypeDropdown.querySelector("option[value='IE-OT']");
                 let ieBtOption = appointmentTypeDropdown.querySelector("option[value='IE-BT']");

                 if (ieOtOption) ieOtOption.disabled = data.completed_ie_ot || false;
                 if (ieBtOption) ieBtOption.disabled = data.completed_ie_bt || false;

                 // If the currently selected type becomes disabled, reset it
                 const currentType = appointmentTypeDropdown.value;
                 if ((currentType === 'IE-OT' && ieOtOption?.disabled) || (currentType === 'IE-BT' && ieBtOption?.disabled)) {
                     log(`Current selection ${currentType} disabled by history check, resetting type.`);
                     appointmentTypeDropdown.value = '';
                     appointmentTypeDropdown.dispatchEvent(new Event('change')); // Trigger reset of dependent fields
                 }
             })
             .catch(error => log("History check error:", error));
     }


     function checkExistingAppointment(patientID, selectedAppointmentType) {
        if (!patientID || !selectedAppointmentType) return;
        log(`Checking existing appointments for Patient ${patientID}, Type ${selectedAppointmentType}`);

        fetch(`${config.apiEndpoints.checkExisting}?patient_id=${patientID}&appointment_type=${encodeURIComponent(selectedAppointmentType)}`)
            .then(response => response.ok ? response.json() : Promise.reject('Network error'))
            .then(data => {
                log('Existing appointment check:', data);
                if (data.status === "error" && data.exists) { // Check for a specific flag indicating conflict
                    Swal.fire({
                        title: "Booking Conflict",
                        html: `<p>${data.message}</p>` +
                              (data.existing_session_type ? `<p><strong>Existing:</strong> ${data.existing_session_type} (${data.existing_status})` +
                              (data.existing_date ? ` on ${data.existing_date}` : '') +
                              (data.existing_time ? ` at ${data.existing_time}` : '') + `</p>` : ''),
                        icon: "warning",
                        confirmButtonText: 'Okay'
                    }).then(() => {
                        // Reset conflicting fields
                        appointmentTypeDropdown.value = "";
                        resetDateTimeAndReferral(); // Reset downstream fields
                    });
                }
            })
            .catch(error => log("Check existing appointment error:", error));
    }


    function fetchOpenPlaygroupSessions() {
        log("Fetching playgroup sessions...");
        playgroupSessionDropdown.innerHTML = '<option value="" disabled selected>Loading...</option>';
        playgroupSessionDropdown.disabled = true;

        fetch(config.apiEndpoints.getPlaygroupSessions)
            .then(response => response.ok ? response.json() : Promise.reject('Network error'))
            .then(data => {
                log("Playgroup sessions response:", data);
                playgroupSessionDropdown.innerHTML = ''; // Clear loading
                if (data.status === "success" && data.sessions.length > 0) {
                    playgroupSessionDropdown.appendChild(new Option('-- Select Session --', '', true, true)).disabled = true; // Add placeholder

                    data.sessions.forEach(s => {
                        // Format time for display (assuming H:i:s from DB)
                         let displayTime = 'N/A';
                         try {
                            const timeParts = s.time.split(':');
                            const hoursInt = parseInt(timeParts[0]);
                            const minutes = timeParts[1];
                            const ampm = hoursInt >= 12 ? 'PM' : 'AM';
                            const formattedHour = hoursInt % 12 === 0 ? 12 : hoursInt % 12;
                            displayTime = `${formattedHour}:${minutes} ${ampm}`;
                         } catch { /* ignore format error */ }

                        const text = `${s.date} at ${displayTime} (${s.current_count}/${s.max_capacity} filled)`;
                        const option = new Option(text, s.pg_session_id);
                        // Store date/time on the option for easy retrieval on change
                        option.dataset.date = s.date;
                        option.dataset.time = s.time; // Store original H:i:s
                        playgroupSessionDropdown.appendChild(option);
                    });
                    playgroupSessionDropdown.disabled = false;
                     // Auto-select first session? Optional.
                     // playgroupSessionDropdown.value = data.sessions[0].pg_session_id;
                     playgroupSessionDropdown.dispatchEvent(new Event('change')); // Update display fields

                } else {
                    playgroupSessionDropdown.innerHTML = '<option value="" disabled selected>No open sessions found</option>';
                    selectedDateDisplay.textContent = 'N/A';
                    selectedTimeDisplay.textContent = 'N/A';
                }
            })
            .catch(error => {
                log("Playgroup fetch error:", error);
                playgroupSessionDropdown.innerHTML = '<option value="" disabled selected>Error loading sessions</option>';
            });
    }

    // --- NEW: Enhanced Availability Fetching ---
    async function updateAvailableTimes(dateStr) {
        timeSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        timeSelect.disabled = true;
        const selectedType = appointmentTypeDropdown.value; // e.g., "IE-OT"

        if (!dateStr || !selectedType || selectedType === "Playgroup") {
            timeSelect.innerHTML = ''; // Clear if playgroup selected after IE
            return;
        }
         // Ensure referral requirements are met for IE types before fetching
        if (selectedType === 'IE-OT' || selectedType === 'IE-BT') {
             if (!isReferralRequirementMet()) {
                 log("Referral requirement not met, not fetching times.");
                 timeSelect.innerHTML = '<option value="" disabled selected>Complete Referral Info</option>';
                 return;
             }
        }

        log(`Fetching available slots for Type: ${selectedType}, Date: ${dateStr}`);
        try {
             const response = await fetch(`${config.apiEndpoints.getSlots}?date=${dateStr}&appointment_type=${encodeURIComponent(selectedType)}`);
             if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
             const data = await response.json();
             log("Slots response:", data);

             timeSelect.innerHTML = ''; // Clear loading/previous options

            if (data.status === "success") {
                let hasOptions = false;
                 timeSelect.appendChild(new Option('-- Select Time --', '', true, true)).disabled = true; // Add placeholder

                // Add fully available slots
                if (data.available_slots && data.available_slots.length > 0) {
                    hasOptions = true;
                    data.available_slots.forEach(slot_hhmmss => {
                        const option = createTimeOption(slot_hhmmss, false);
                        timeSelect.appendChild(option);
                    });
                }

                // Add pending slots
                if (data.pending_slots && data.pending_slots.length > 0) {
                    hasOptions = true;
                     data.pending_slots.forEach(slot_hhmmss => {
                         const option = createTimeOption(slot_hhmmss, true); // Mark as pending
                         timeSelect.appendChild(option);
                     });
                }

                if (hasOptions) {
                     timeSelect.disabled = false;
                } else {
                     // Success but no slots means fully booked
                     timeSelect.innerHTML = '<option value="" disabled selected>No Slots Available</option>';
                     if (selectedType === 'IE-OT' || selectedType === 'IE-BT') {
                         showWaitlistPopup(dateStr, selectedType);
                     }
                }

            } else if (data.status === "fully_booked" || data.status === "closed") {
                timeSelect.innerHTML = `<option value="" disabled selected>${data.message || 'Unavailable'}</option>`;
                 if (data.status === "fully_booked" && (selectedType === 'IE-OT' || selectedType === 'IE-BT')) {
                     showWaitlistPopup(dateStr, selectedType);
                 }
            } else { // Handle other errors reported by the server
                timeSelect.innerHTML = '<option value="" disabled selected>Error loading times</option>';
                 Swal.fire("Error", data.message || "Could not load available times.", "error");
            }

        } catch (error) {
             log("Fetch error (Slots):", error);
             timeSelect.innerHTML = '<option value="" disabled selected>Network Error</option>';
             Swal.fire("Error", "Could not connect to server to check availability.", "error");
        }
    }

    // Helper to create time option elements
    function createTimeOption(slot_hhmmss, isPending) {
        const [hours, minutes] = slot_hhmmss.split(':'); // Ignore seconds for display
        const hoursInt = parseInt(hours);
        const ampm = hoursInt >= 12 ? 'PM' : 'AM';
        const formattedHour = hoursInt % 12 === 0 ? 12 : hoursInt % 12;
        let displayTime = `${formattedHour}:${minutes} ${ampm}`;

        const option = new Option(); // Create empty option first
        option.value = slot_hhmmss; // Value is always HH:MM:SS
        option.dataset.isPending = isPending ? "true" : "false"; // Store pending state

        if (isPending) {
            option.textContent = `${displayTime} (Request Pending)`;
            option.classList.add('pending-slot-option'); // Add class for potential styling
        } else {
            option.textContent = displayTime;
        }
        return option;
    }

     // --- Waitlist Logic ---
     function showWaitlistPopup(dateStr, appointmentType) {
         const patientId = patientDropdown.value;
         const patientName = patientDropdown.options[patientDropdown.selectedIndex]?.text || 'Selected Patient';

         if (!patientId) {
             Swal.fire("Select Patient", "Please select a patient before joining the waitlist.", "warning");
             return;
         }

         // Check referral requirement again for IE types before showing waitlist option
         if ((appointmentType === 'IE-OT' || appointmentType === 'IE-BT') && !isReferralRequirementMet(true)) { // Pass true to allow alert
              return; // Stop if referral missing
         }

         log(`Showing waitlist popup for Type: ${appointmentType}, Date: ${dateStr}`);
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
                 log("Waitlist popup cancelled.");
                 appointmentDateInput.value = ''; // Clear date
                 timeSelect.innerHTML = '<option value="" disabled selected>-- Select Date First --</option>';
                 timeSelect.disabled = true;
                 fpInstance?.clear(); // Clear flatpickr
             }
         });
     }

     async function submitWaitlistRequest(waitlistType, specificDate) {
        log(`Submitting waitlist request. Type: ${waitlistType}, Date: ${specificDate || 'Any'}`);
        const formData = new FormData(form); // Get current form data (includes patient, type, referral if applicable)
        formData.append('waitlist_type', waitlistType);
        if (specificDate) formData.append('specific_date', specificDate);
        formData.append('action', 'request_waitlist'); // Add action flag

        // Append referral file data manually if needed for waitlist
        if ((formData.get('appointment_type') === 'IE-OT' || formData.get('appointment_type') === 'IE-BT') && referralFileInput.files.length > 0) {
             formData.append('referral_file', referralFileInput.files[0]); // Ensure file is included
             formData.append('referral_upload_type', referralUploadTypeHidden.value); // Include type ('official' or 'proof')
        } else if ((formData.get('appointment_type') === 'IE-OT' || formData.get('appointment_type') === 'IE-BT')) {
            // If IE and no file, the check in showWaitlistPopup should have caught this
            log("Attempting to waitlist IE without referral file - should not happen.");
            Swal.fire('Error', 'Referral document missing.', 'error');
            return;
        }


        showLoading('Adding to Waitlist...');
        try {
            const response = await fetch(config.apiEndpoints.submitForm, { method: 'POST', body: formData });
            const result = await response.json();
            hideLoading();
            log("Waitlist submission response:", result);

            if (result.status === 'success') {
                Swal.fire({ title: 'Success!', text: result.message || 'Added to waitlist successfully.', icon: 'success' }).then(() => {
                    form.reset();
                    resetFormDisplay(); // Reset visibility of sections
                    fetchPatientDetails(null); // Clear patient details display
                });
            } else {
                Swal.fire('Error', result.message || 'Failed to add to waitlist.', 'error');
            }
        } catch (error) {
            hideLoading();
            log("Waitlist submission error:", error);
            Swal.fire('Error', 'A network error occurred while submitting the waitlist request.', 'error');
        }
    }


    // --- Form State Management & Visibility ---

    function resetDateTimeAndReferral() {
        dateTimeContainer.style.display = 'none';
        appointmentDateInput.value = '';
        appointmentDateInput.disabled = true;
        fpInstance?.clear();
        timeSelect.innerHTML = '<option value="" disabled selected>-- Select Date First --</option>';
        timeSelect.disabled = true;

        referralQuestion.style.display = 'none';
        referralUpload.style.display = 'none';
        hasReferral.value = '';
        referralFileInput.value = ''; // Clear file input
        referralUploadTypeHidden.value = '';
        // Manually clear the text display for uk-form-custom
         const fileText = referralUpload.querySelector('.uk-input[type="text"]');
         if (fileText) fileText.value = '';

    }

    function resetFormDisplay() {
        resetDateTimeAndReferral();
        playgroupSessionContainer.style.display = 'none';
        playgroupSessionDropdown.innerHTML = '';
        playgroupSessionDropdown.required = false;
         appointmentDateInput.required = false;
         timeSelect.required = false;
         referralFileInput.required = false;

    }

    // Checks if referral question answered and file uploaded if IE type selected
    function isReferralRequirementMet(showAlert = false) {
         const selectedType = appointmentTypeDropdown.value;
         if (selectedType !== 'IE-OT' && selectedType !== 'IE-BT') {
             return true; // Not required for other types
         }

         const hasReferralAnswer = hasReferral.value; // "yes" or "no"
         const fileExists = referralFileInput.files.length > 0;

         if (!hasReferralAnswer) {
              if (showAlert) Swal.fire("Missing Information", "Please answer the referral question.", "warning");
              return false;
         }
         if (!fileExists) {
             if (showAlert) Swal.fire("Missing Document", `Please upload the ${hasReferralAnswer === 'yes' ? 'Doctor Referral' : 'Proof of Booking'}.`, "warning");
             return false;
         }
         return true; // All requirements met for IE
     }


    function checkAndControlDateTimeVisibility() {
        const selectedType = appointmentTypeDropdown.value;

        if (selectedType === 'IE-OT' || selectedType === 'IE-BT') {
            if (isReferralRequirementMet()) { // Check if reqs met (no alert needed here)
                dateTimeContainer.style.display = 'block';
                appointmentDateInput.disabled = false;
                // Fetch times ONLY if a date is already selected
                if (appointmentDateInput.value) {
                    updateAvailableTimes(appointmentDateInput.value);
                } else {
                     timeSelect.innerHTML = '<option value="" disabled selected>-- Select Date First --</option>';
                     timeSelect.disabled = true;
                }
            } else {
                // Keep date/time hidden/disabled if referral info incomplete
                dateTimeContainer.style.display = 'none';
                appointmentDateInput.disabled = true;
                timeSelect.innerHTML = '<option value="" disabled selected>Complete Referral Info</option>';
                timeSelect.disabled = true;
            }
        } else {
            // Hide date/time container if not an IE type
             dateTimeContainer.style.display = 'none';
             appointmentDateInput.disabled = true;
             timeSelect.innerHTML = '';
             timeSelect.disabled = true;
        }
    }


    // --- Event Listeners Setup ---
    function initializeEventListeners() {
        log("Initializing event listeners...");

        // ** Patient Dropdown Change **
        patientDropdown.addEventListener("change", function () {
            const patientID = this.value;
            log(`Patient selected: ID ${patientID}`);
            fetchPatientDetails(patientID);
            checkPatientHistory(patientID); // May disable IE options

             // Reset subsequent fields
            appointmentTypeDropdown.value = ''; // Reset type
             resetFormDisplay(); // Hide/clear all conditional sections

            // Re-check for existing appointments based *only* on patient (since type is reset)
             // checkExistingAppointment(patientID, null); // Or maybe wait for type selection
        });

        // ** Appointment Type Dropdown Change **
        appointmentTypeDropdown.addEventListener("change", async function () { // Added async
    const selectedType = this.value;
    const patientID = patientDropdown.value;
    log(`Appointment type selected: ${selectedType}`);

    // --- Reset downstream fields immediately when type changes ---
    // This prevents stale info if user changes type back and forth
    resetDateTimeAndReferral();
    playgroupSessionContainer.style.display = 'none';
    playgroupSessionDropdown.required = false;
    // ... other field resets ...

    // If no patient selected or type deselected, do nothing further
    if (!patientID && selectedType) {
        Swal.fire("Select Patient", "Please select a patient first.", "warning");
        this.value = ''; // Reset type dropdown
        return;
    }
    if (!selectedType) {
        return; // Do nothing if type is empty
    }

    // --- Perform Immediate Check for Pending/Approved Duplicates ---
    // Only check if it's not Playgroup (Playgroup might allow multiple pending?)
    if (selectedType !== 'Playgroup') {
        try {
            showLoading('Checking existing appointments...'); // Optional: Indicate check
            log(`Checking existing pending/approved for Patient ${patientID}, Type ${selectedType}`);
            // Add a flag to tell the backend script which specific check we want
            const checkUrl = `${config.apiEndpoints.checkExisting}?patient_id=${patientID}&appointment_type=${encodeURIComponent(selectedType)}&check_status=pending_approved`;
            const response = await fetch(checkUrl);

             // Check response status first
             if (!response.ok) {
                 const errorText = await response.text(); // Get potential HTML error page content
                 throw new Error(`Network error during check (${response.status}): ${errorText}`);
             }

            const data = await response.json();
            hideLoading(); // Hide loading indicator
            log('Immediate duplicate check response:', data);

            if (data.exists === true) {
                // ----- CONFLICT FOUND -----
                await Swal.fire({ // await the Swal dismissal
                    title: "Existing Appointment Found",
                    html: `<p>${data.message || 'This patient already has a similar appointment request.'}</p>` +
                          (data.existing_session_type ? `<p><strong>Details:</strong> ${data.existing_session_type} (${ucfirst(data.existing_status)})` + // Capitalize status
                          (data.existing_date ? ` on ${data.existing_date}` : '') +
                          (data.existing_time ? ` at ${data.existing_time}` : '') + `</p>` : ''),
                    icon: "warning",
                    confirmButtonText: 'Okay'
                });
                // Reset the appointment type dropdown AFTER user clicks OK
                appointmentTypeDropdown.value = "";
                // Note: Downstream fields (date/time/referral) were already reset at the start of the listener.
                return; // Stop further processing in this listener
                // ----- END CONFLICT HANDLING -----
            }
            // --- No Conflict Found - Proceed with normal logic for this type ---
            log('No immediate conflict found. Proceeding with UI setup.');

        } catch (error) {
             hideLoading(); // Hide loading indicator
             log("Error during immediate duplicate check:", error);
             Swal.fire("Error", "Could not check for existing appointments. Please try again. Details: " + error.message, "error");
             appointmentTypeDropdown.value = ""; // Reset type dropdown on error too
             return; // Stop processing
        }
    } 

            if (selectedType === "Playgroup") {
                playgroupSessionContainer.style.display = "block";
                playgroupSessionDropdown.required = true; // Make required
                fetchOpenPlaygroupSessions();
            } else if (selectedType === "IE-OT" || selectedType === "IE-BT") {
                referralQuestion.style.display = "block"; // Show referral Q
                 appointmentDateInput.required = true;
                 timeSelect.required = true;
                // Referral Upload visibility is handled by the 'has_referral' change listener
                 // Date/Time visibility is handled by checkAndControlDateTimeVisibility
                 checkAndControlDateTimeVisibility(); // Check if referral already met & show date/time if so
            }

            // Check for existing appointments for this specific patient/type combination
            checkExistingAppointment(patientID, selectedType);
        });

        // ** Referral Question Change **
        hasReferral.addEventListener("change", function () {
             const answer = this.value;
             log(`Referral question answered: ${answer}`);
             if (answer === "yes") {
                 referralUpload.style.display = "block";
                 referralLabel.textContent = "Upload Doctor's Referral:";
                 referralFileInput.required = true;
                 referralUploadTypeHidden.value = 'official'; // Set type for backend
             } else if (answer === "no") {
                 referralUpload.style.display = "block";
                 referralLabel.textContent = "Upload Proof of Booking:";
                 referralFileInput.required = true;
                 referralUploadTypeHidden.value = 'proof_of_booking'; // Set type for backend
             } else {
                 referralUpload.style.display = "none";
                 referralFileInput.required = false;
                 referralFileInput.value = ''; // Clear file
                 referralUploadTypeHidden.value = '';
                 // Manually clear the text display for uk-form-custom
                 const fileText = referralUpload.querySelector('.uk-input[type="text"]');
                 if (fileText) fileText.value = '';
             }
             // After answering, check if date/time can be shown/enabled
             checkAndControlDateTimeVisibility();
         });

         // ** Referral File Input Change **
         referralFileInput.addEventListener('change', function() {
            log("Referral file selected.");
            // Check again if requirements met (file now exists) to enable date/time
             checkAndControlDateTimeVisibility();
         });


        // ** Playgroup Session Dropdown Change **
        playgroupSessionDropdown.addEventListener("change", function () {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.value) {
                log(`Playgroup session selected: ID ${selectedOption.value}`);
                selectedDateDisplay.textContent = selectedOption.dataset.date || 'N/A';
                // Format time for display
                let displayTime = 'N/A';
                try {
                   const timeParts = selectedOption.dataset.time.split(':');
                   const hoursInt = parseInt(timeParts[0]);
                   const minutes = timeParts[1];
                   const ampm = hoursInt >= 12 ? 'PM' : 'AM';
                   const formattedHour = hoursInt % 12 === 0 ? 12 : hoursInt % 12;
                   displayTime = `${formattedHour}:${minutes} ${ampm}`;
                } catch { /* ignore error */ }
                selectedTimeDisplay.textContent = displayTime;
            } else {
                selectedDateDisplay.textContent = 'N/A';
                selectedTimeDisplay.textContent = 'N/A';
            }
        });

        // ** Form Submit Validation and AJAX Handling ** // MODIFIED
        form.addEventListener("submit", async function (e) { // Added async for await potential
            e.preventDefault(); // PREVENT standard form submission for ALL cases now
            log("Form submit initiated (AJAX)...");

            const appointmentType = appointmentTypeDropdown.value;
            const isPlaygroup = appointmentType === 'Playgroup';
            const isIE = appointmentType === 'IE-OT' || appointmentType === 'IE-BT';

            // --- Playgroup Validation ---
            if (isPlaygroup) {
                if (!playgroupSessionDropdown.value) {
                    Swal.fire("Incomplete", "Please select a Playgroup session.", "warning");
                    return; // Stop submission
                }
            }
            // --- IE Validation ---
            else if (isIE) {
                // Check referral requirements first
                if (!isReferralRequirementMet(true)) { // Show alert if missing
                    return; // Stop submission
                }
                // Check if date and time are selected
                if (appointmentDateInput.disabled || !appointmentDateInput.value || timeSelect.disabled || !timeSelect.value) {
                    Swal.fire("Incomplete", "Please select an available date and time.", "warning");
                    return; // Stop submission
                }

                // Check for Pending Slot Confirmation (using await for cleaner flow)
                const selectedTimeOption = timeSelect.options[timeSelect.selectedIndex];
                const isPendingSlot = selectedTimeOption?.dataset.isPending === "true";

                if (isPendingSlot) {
                    log("Pending slot selected, asking for confirmation.");
                    try {
                        const confirmation = await Swal.fire({ // Use await here
                            title: 'Confirm Booking Request',
                            html: "Another request is already pending for this time slot (<b>" + selectedTimeOption.textContent.replace(' (Request Pending)', '') + "</b>). <br><br>Your request will be added after the existing one. Do you want to proceed?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Proceed',
                            cancelButtonText: 'No, Choose Another Time',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33'
                        });

                        if (!confirmation.isConfirmed) {
                            log("User cancelled pending slot submission.");
                            return; // Stop submission if user cancels
                        }
                        // If confirmed, proceed to submit below
                        log("User confirmed pending slot.");
                    } catch (error) {
                        log("Error during pending slot confirmation:", error);
                        return; // Stop if Swal fails
                    }
                }
            }
            // --- Other/No Type Validation ---
            else {
                Swal.fire("Incomplete", "Please select a valid appointment type.", "warning");
                return; // Stop submission
            }

            // --- If all validation passed, proceed with AJAX submission ---
            log("Validation passed, submitting form data via AJAX.");
            showLoading('Submitting Request...'); // Show loading indicator

            const formData = new FormData(form);
            // Add action=book_appointment if not already present (waitlist adds its own)
            if (!formData.has('action')) {
                formData.append('action', 'book_appointment');
            }

            try {
                const response = await fetch(form.action, { // form.action gets URL from form element
                    method: 'POST',
                    body: formData
                });

                // Check if response is ok (status 200-299)
                if (!response.ok) {
                    // Try to get text for non-JSON errors (like HTML error pages)
                    const errorText = await response.text();
                    throw new Error(`Network response was not ok. Status: ${response.status}. Body: ${errorText}`);
                }

                const result = await response.json(); // Parse the JSON response from PHP
                hideLoading(); // CLOSE the loading Swal FIRST
                log("AJAX submission response:", result);

                if (result.status === 'success' && result.swal) {
                    Swal.fire({ // Show the Swal defined in the PHP response
                        title: result.swal.title || 'Success!',
                        text: result.swal.text,
                        icon: result.swal.icon || 'success'
                    }).then(() => { // After user clicks OK on the success Swal
                        log("Resetting form after successful submission.");
                        form.reset();
                        resetFormDisplay(); // Reset visibility of sections
                        fetchPatientDetails(null); // Clear patient details display
                        // You might want to re-enable the submit button here if you disabled it
                    
                        try {
                            log("Navigating parent window to dashboard default.");
                            window.parent.location.href = '../Dashboards/clientdashboard.php'; 
                        } catch (e) {
                            console.error("Error accessing parent window. Potentially a cross-origin issue?", e);
                            // Fallback if parent access fails (e.g., just alert user)
                            alert("Booking successful! Please refresh your dashboard to see the changes.");
                        }
                                    
                    });
                } else if (result.status === 'error' && result.swal) {
                    Swal.fire({ // Show the error Swal defined in PHP
                        title: result.swal.title || 'Error!',
                        text: result.swal.text || result.message, // Use message if text missing
                        icon: result.swal.icon || 'error'
                    });
                    // Consider if form reset is needed on error
                } else {
                    // Handle cases where JSON is valid but status is unexpected or swal is missing
                    throw new Error("Received an unexpected response format from the server.");
                }

            } catch (error) {
                hideLoading(); // Ensure loading Swal is closed on error too
                log("AJAX submission failed:", error);
                Swal.fire(
                    'Submission Error',
                    'An error occurred while submitting your request. Please check your connection or contact support. Details: ' + error.message,
                    'error'
                );
                // Re-enable submit button if disabled
            }
});

    }

    // --- Initialization ---
    document.addEventListener("DOMContentLoaded", function () {
        log("DOM Loaded. Initializing...");

        // Assign Element References
        patientDropdown = document.getElementById("patient_id");
        appointmentTypeDropdown = document.getElementById("appointment_type");
        appointmentDateInput = document.getElementById("appointment_date");
        timeSelect = document.getElementById("appointment_time");
        dateTimeContainer = document.getElementById("date_time_container");
        playgroupSessionContainer = document.getElementById("playgroup_session_container");
        playgroupSessionDropdown = document.getElementById("pg_session_id");
        selectedDateDisplay = document.getElementById("pg_selected_date");
        selectedTimeDisplay = document.getElementById("pg_selected_time");
        referralQuestion = document.getElementById("referralQuestion");
        referralUpload = document.getElementById("referralUpload");
        hasReferral = document.getElementById("has_referral");
        referralLabel = document.getElementById("referralLabel");
        referralFileInput = document.getElementById("referral_file"); // Single file input
        referralUploadTypeHidden = document.getElementById("referral_upload_type"); // Hidden type tracker
        patientDetailsDiv = document.getElementById("patientDetails");
        patientNameSpan = document.getElementById("patient_name");
        patientBdaySpan = document.getElementById("patient_bday");
        patientAgeSpan = document.getElementById("patient_age");
        patientGenderSpan = document.getElementById("patient_gender");
        patientServiceSpan = document.getElementById("patient_service");
        patientStatusSpan = document.getElementById("patient_status");
        patientProfileImg = document.getElementById("patient_profile");
        patientNameHiddenInput = document.getElementById("patient_name_hidden");
        form = document.getElementById("appointmentForm");
        submitButton = document.getElementById("submitButton");

        // Initialize Flatpickr
        const today = new Date();
        const minDate = new Date(today);
        minDate.setDate(today.getDate() + config.flatpickr.minDaysAdvance);
        minDate.setHours(0, 0, 0, 0);

        const maxDate = new Date(today);
        maxDate.setDate(today.getDate() + config.flatpickr.maxDaysAdvance);
        maxDate.setHours(0, 0, 0, 0);

        fpInstance = flatpickr("#appointment_date", {
            altInput: true, // Show user-friendly format
            altFormat: "F j, Y", // e.g., April 15, 2024
            dateFormat: "Y-m-d", // Send YYYY-MM-DD to server
            minDate: minDate.toISOString().split("T")[0],
            maxDate: maxDate.toISOString().split("T")[0],
            disable: [
                 function(date) {
                     const isoDate = date.toISOString().split('T')[0];
                     const dayOfWeek = date.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase(); // lowercase day

                     // 1. Check globally blocked dates (includes closed exceptions)
                     if (config.flatpickr.blockedDates.includes(isoDate)) return true;

                      // 2. If it's an 'open override' date, it's available regardless of default day closure
                     if (config.flatpickr.openOverrideDates.includes(isoDate)) return false;

                     // 3. Check default closed days
                     if (config.flatpickr.closedDays.includes(dayOfWeek)) return true;

                     // 4. If none of the above, it's open by default
                     return false;
                 }
            ],
            onChange: function (selectedDates, dateStr) {
                 log(`Date selected via Flatpickr: ${dateStr}`);
                 updateAvailableTimes(dateStr); // Trigger time slot fetching/update
            },
            onClose: function(selectedDates, dateStr, instance) {
                 // Optional: Re-validate if needed when picker closes?
            }
        });

        // Initial state setup
        appointmentDateInput.disabled = true; // Start disabled
        timeSelect.disabled = true;

        // Initialize Event Listeners
        initializeEventListeners();

        log("Initialization complete.");
         // Trigger change on patient dropdown if a patient is pre-selected (e.g., from previous state)
         if (patientDropdown.value) {
             patientDropdown.dispatchEvent(new Event('change'));
         }

    }); // End DOMContentLoaded


    function ucfirst(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

    </script>

</body>
</html>
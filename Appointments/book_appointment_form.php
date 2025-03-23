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
    $settingsQuery = "SELECT business_hours_start, business_hours_end, max_days_advance, min_days_advance, blocked_dates, initial_eval_duration, playgroup_duration 
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
    $overrideStmt = $connection->prepare("SELECT start_time, end_time FROM business_hours_exceptions WHERE exception_date = ?");
    $overrideStmt->bind_param("s", $date);
    $overrideStmt->execute();
    $overrideResult = $overrideStmt->get_result();
    $override = $overrideResult->fetch_assoc();

    if ($override) {
        $start = $override['start_time'];
        $end = $override['end_time'];
    } else {
        $dayOfWeek = date("l", strtotime($date));
        $start = $bizHoursByDay[$dayOfWeek]['start'];
        $end = $bizHoursByDay[$dayOfWeek]['end'];
    }


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
        html, body {
            background-color: #ffffff !important;
        }

        .appointment-container {
            display: flex;
            gap: 20px; /* Adjust as needed for spacing */
        }

        .appointment-form {
            flex: 1; /* Take up available space */
        }

        .patient-details-container {
            flex: 1; /* Take up available space */
        }
    </style>
</head>
<body>
    <div class="uk-container uk-margin-top appointment-container">
        <div class="appointment-form">
            <h2>Book an Appointment</h2>
            <p>Your Role: <strong><?= ucfirst($role); ?></strong></p>

            <?php if (empty($patients)): ?>
                <div class="uk-alert-warning" uk-alert>
                    <p>Please Register a Patient before Booking an Appointment.</p>
                    <button class="uk-button uk-button-primary" onclick="goToRegisterPatient()">Register a Patient</button>
                </div>
            <?php else: ?>

            <form id="appointmentForm" action="app_process/book_appointment_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked">
                <label>Select Patient:</label>
                <select class="uk-select" name="patient_id" id="patient_id" required>
                    <option value="" disabled selected>Select a Patient</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?= $patient['patient_id']; ?>">
                            <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="patient_name" id="patient_name_hidden">

                <label>Appointment Type:</label>
                <select class="uk-select" name="appointment_type" id="appointment_type" required>
                    <option value="" disabled selected>Select Appointment Type</option>
                    <option value="Initial Evaluation">Initial Evaluation</option>
                    <option value="Playgroup">Playgroup</option>
                </select>

                <div id="date_time_container">
                    <label>Date:</label>
                    <input class="uk-input" type="date" name="appointment_date" id="appointment_date" required>

                    <label>Time:</label>
                    <select class="uk-select" name="appointment_time" id="appointment_time" required></select>
                </div>

                <div id="playgroup_session_container" style="display: none;">
                    <label>Select Playgroup Session:</label>
                    <select class="uk-select" name="pg_session_id" id="pg_session_id" required>
                        <option value="" disabled selected>Fetching available sessions...</option>
                    </select>
                    <p><strong>Date:</strong> <span id="pg_selected_date"></span></p>
                    <p><strong>Time:</strong> <span id="pg_selected_time"></span></p>
                </div>

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

                    <input class="uk-input" type="file" name="official_referral" id="official_referral" accept=".jpg, .jpeg, .png, .pdf">
                    <input class="uk-input" type="file" name="proof_of_booking" id="proof_of_booking" accept=".jpg, .jpeg, .png, .pdf">
                </div>

                <button class="uk-button uk-button-primary uk-margin-top" type="submit">Book</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="patient-details-container">
            <div id="patientDetails" class="uk-margin uk-card uk-card-default uk-card-body" style="display: none;">
                <h4>Patient Details</h4>
                <p><strong>Name:</strong> <span id="patient_name"></span></p>
                <p><strong>Birthday:</strong> <span id="patient_bday"></span></p>
                <p><strong>Age:</strong> <span id="patient_age"></span></p>
                <p><strong>Gender:</strong> <span id="patient_gender"></span></p>
                <p><strong>Service Type:</strong> <span id="patient_service"></span></p>
                <img id="patient_profile" src="" alt="Profile Picture" class="uk-border-rounded" style="width: 100px; height: 100px; display: none;">
            </div>
        </div>
    </div>

    <script>

    // Function to switch the main page section
    function goToRegisterPatient() {
        parent.document.querySelectorAll('.section').forEach(section => {
            section.style.display = 'none'; // Hide other sections
        });

        parent.document.getElementById("register-patient").style.display = "block"; // Show Register Patient section
    }

    let openOverrideDates = <?= json_encode($openOverrideDates) ?>;
    let closedDays = <?= json_encode($closedDays) ?>;

    console.log("closedDays", closedDays);

    
    // Function to switch the main page section
    function goToRegisterPatient() {
        parent.document.querySelectorAll('.section').forEach(section => {
            section.style.display = 'none'; // Hide other sections
        });

        parent.document.getElementById("register-patient").style.display = "block"; // Show Register Patient section
    }

    //for time table
    document.addEventListener("DOMContentLoaded", function () {
        let blockedDates = <?= json_encode($blockedDates) ?>;
        let minDaysAdvance = <?= $minDaysAdvance ?>;
        let maxDaysAdvance = <?= $maxDaysAdvance ?>;

        let today = new Date();
        let minDate = new Date(today);
        minDate.setDate(today.getDate() + minDaysAdvance - 1); // ← Show March 26 properly


        let maxDate = new Date();
        maxDate.setDate(today.getDate() + maxDaysAdvance);

        console.log("minDate", minDate.toISOString().split("T")[0]);
console.log("maxDate", maxDate.toISOString().split("T")[0]);
console.log("Today is", today.toISOString().split("T")[0]);

        flatpickr("#appointment_date", {
            minDate: minDate.toISOString().split("T")[0],
            maxDate: maxDate.toISOString().split("T")[0],
            dateFormat: "Y-m-d",
            disable: [
            function(date) {
                const iso = date.toLocaleDateString("en-CA"); // ✅ local ISO date
                const weekdayMap = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
                const weekday = weekdayMap[date.getDay()];

                const actualMinDate = new Date();
                actualMinDate.setDate(actualMinDate.getDate() + <?= $minDaysAdvance ?>);
                actualMinDate.setHours(0, 0, 0, 0);

                if (date < actualMinDate) return true;
                if (openOverrideDates.includes(iso)) return false;
                if (closedDays.includes(weekday)) return true;

                return false;
            }
        ],
            onChange: function (selectedDates, dateStr) {
                updateAvailableTimes(dateStr);
            }
        });


        const timeInput = document.getElementById("appointment_time");
        const appointmentType = document.getElementById("appointment_type");

        async function updateAvailableTimes(dateStr) {
            timeInput.innerHTML = "";
            const selectedType = appointmentType.value;
            const interval = selectedType === "Playgroup" ? <?= $pgDuration ?> : <?= $ieDuration ?>;

            const res = await fetch("app_data/get_available_hours.php?date=" + dateStr);
            const data = await res.json();

            if (data.status !== "open") {
                const option = document.createElement("option");
                option.value = "";
                option.textContent = "Date is closed";
                timeInput.appendChild(option);
                timeInput.disabled = true;
                return;
            }

            const startHour = parseInt(data.start.split(":")[0]);
            const startMinute = parseInt(data.start.split(":")[1]);
            const endHour = parseInt(data.end.split(":")[0]);
            const endMinute = parseInt(data.end.split(":")[1]);

            let start = new Date();
            start.setHours(startHour, startMinute, 0, 0);

            let end = new Date();
            end.setHours(endHour, endMinute, 0, 0);

            while (start < end) {
                const hours = start.getHours();
                const minutes = start.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                const formattedHour = hours % 12 === 0 ? 12 : hours % 12;
                const formattedTime = `${formattedHour}:${minutes.toString().padStart(2, '0')} ${ampm}`;

                const option = document.createElement("option"); // ✅ THIS LINE WAS MISSING
                option.value = formattedTime;
                option.textContent = formattedTime;

                timeInput.appendChild(option);

                start.setMinutes(start.getMinutes() + interval);
            }


            timeInput.disabled = false;
        }

        appointmentType.addEventListener("change", function () {
            const selectedDate = document.getElementById("appointment_date").value;
            if (selectedDate) {
                updateAvailableTimes(selectedDate);
            }
        });
    });


    //if the selected patient has a pending appointment, then it will return to "select appointment type".
        document.addEventListener("DOMContentLoaded", function () {
        let appointmentType = document.getElementById("appointment_type");
        let referralQuestion = document.getElementById("referralQuestion");
        let referralSection = document.getElementById("referralUpload");
        let referralLabel = document.getElementById("referralLabel");
        let hasReferral = document.getElementById("has_referral");
        let doctorsReferral = document.getElementById("doctors_referral");
        let appointmentTypeDropdown = document.getElementById("appointment_type");
        let patientDropdown = document.getElementById("patient_id");
        let submitButton = document.querySelector("button[type='submit']");
        let isChecking = false; // ✅ Prevent multiple alerts
        let officialReferralInput = document.getElementById("official_referral");
        let proofOfBookingInput = document.getElementById("proof_of_booking");


        // ✅ Show/Hide Doctor's Referral Question Based on Appointment Type
        appointmentType.addEventListener("change", function () {
            if (appointmentType.value === "Initial Evaluation") {
                referralQuestion.style.display = "block";
                referralSection.style.display = "none";
            } else {
                referralQuestion.style.display = "none";
                referralSection.style.display = "none";
            }

            // ✅ Check Pending Appointments on Change
            checkExistingAppointment();
        });

        // ✅ Show Referral Upload If Answer is "Yes" or "No"
        hasReferral.addEventListener("change", function () {
            if (hasReferral.value === "yes") {
                referralUpload.style.display = "block";
                referralLabel.textContent = "Upload Doctor's Referral:";
                officialReferralInput.style.display = "block";
                proofOfBookingInput.style.display = "none"; // Hide Proof of Booking
            } else if (hasReferral.value === "no") {
                referralUpload.style.display = "block";
                referralLabel.textContent = "Upload Proof of Booking for Doctor's Referral:";
                officialReferralInput.style.display = "none"; // Hide Doctor's Referral
                proofOfBookingInput.style.display = "block";
            } else {
                referralUpload.style.display = "none"; // Hide both if no selection
            }
        });

        function checkExistingAppointment() {
            let patientID = patientDropdown.value;
            let appointmentType = appointmentTypeDropdown.value;

            if (!patientID || !appointmentType || isChecking) return;

            isChecking = true; // ✅ Prevent multiple calls until this completes

            fetch(`app_data/check_existing_appointment.php?patient_id=${patientID}&appointment_type=${appointmentType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "error") {
                        Swal.fire({
                            title: "Booking Not Allowed",
                            html: `
                                <p>${data.message}</p>
                                <p><strong>Existing Session:</strong> ${data.existing_session_type}</p>
                                <p><strong>Status:</strong> ${data.existing_status}</p>
                                <p><strong>Date:</strong> ${data.existing_date}</p>
                                <p><strong>Time:</strong> ${data.existing_time}</p>
                                <p>Your selections will be cleared.</p>
                            `,
                            icon: "warning"
                        }).then(() => {
                            // ✅ Reset the entire form
                            document.getElementById("appointmentForm").reset();

                            // ✅ Clear patient selection
                            let patientDropdown = document.getElementById("patient_id");
                            patientDropdown.value = "";

                            // ✅ Hide and reset patient details
                            let patientDetailsDiv = document.getElementById("patientDetails");
                            let patientName = document.getElementById("patient_name");
                            let patientAge = document.getElementById("patient_age");
                            let patientGender = document.getElementById("patient_gender");
                            let patientService = document.getElementById("patient_service");
                            let patientProfile = document.getElementById("patient_profile");
                            let editPatientBtn = document.getElementById("editPatientBtn");

                            patientDetailsDiv.style.display = "none";
                            patientName.textContent = "";
                            patientAge.textContent = "";
                            patientGender.textContent = "";
                            patientService.textContent = "";
                            patientProfile.src = "";
                            patientProfile.style.display = "none";
                            editPatientBtn.style.display = "none";

                            // ✅ Hide dependent fields
                            document.getElementById("referralQuestion").style.display = "none";
                            document.getElementById("referralUpload").style.display = "none";
                            document.getElementById("appointment_date").value = ""; 
                            document.getElementById("appointment_time").innerHTML = "";
                        });
                    }
                })
                .catch(error => {
                    console.error("Error checking existing appointments:", error);
                    Swal.fire("Error", "An error occurred while checking for existing appointments.", "error");
                })
                .finally(() => {
                    isChecking = false; // ✅ Allow new checks
                });
        }

        // ✅ Ensure only **one check at a time**
        appointmentTypeDropdown.addEventListener("change", checkExistingAppointment);
        patientDropdown.addEventListener("change", checkExistingAppointment);
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
        let appointmentTypeDropdown = document.getElementById("appointment_type");
        let isFetching = false;

        patientDropdown.addEventListener("change", function () {
            let patientID = this.value;

            if (!patientID) {
                if (patientDetailsDiv) {
                    patientDetailsDiv.style.display = "none";
                }
                return;
            }

            if (isFetching) {
                return;
            }

            isFetching = true;

            // Fetch patient details
            fetch("patient/patient_data/fetch_patient_details.php?patient_id=" + patientID)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log(text);
                    return JSON.parse(text);
                })
                .then(data => {
                        if (data.status === "success") {
                        let fullName = data.patient.first_name + " " + data.patient.last_name;
                        patientName.textContent = fullName;
                        document.getElementById("patient_name_hidden").value = fullName;
                        patientGender.textContent = data.patient.gender;
                        patientService.textContent = data.patient.service_type ?? "Not Found";

                        // Calculate age from birthdate
                        let birthdate = data.patient.bday; // Access birthday from response
                        let age = "N/A"; // Default if no birthday

                        if (birthdate) {
                            let birthDateObj = new Date(birthdate);
                            let today = new Date();
                            age = today.getFullYear() - birthDateObj.getFullYear();
                            let monthDiff = today.getMonth() - birthDateObj.getMonth();

                            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDateObj.getDate())) {
                                age--;
                            }
                        }

                        // Display birthday and age
                        document.getElementById("patient_bday").textContent = birthdate || "N/A";
                        patientAge.textContent = age;

                        if (patientDetailsDiv) {
                            patientDetailsDiv.style.display = "block";
                        } else {
                            console.error("patientDetails element not found!");
                        }

                        if (data.patient.profile_picture) {
                            patientProfile.src = "../uploads/profile_pictures/" + data.patient.profile_picture;
                            if (patientProfile) { // Defensive check here
                                patientProfile.style.display = "block";
                            }
                        } else {
                            if (patientProfile) { // Defensive check here
                                patientProfile.style.display = "none";
                            }
                        }

                        if (editPatientBtn) { // Defensive check here
                            editPatientBtn.style.display = "inline-block";
                        }
                        if (patientDetailsDiv) { // Defensive check here
                            patientDetailsDiv.style.display = "block";
                        }
                    } else {
                        if (patientDetailsDiv) {
                            patientDetailsDiv.style.display = "none";
                        }
                        console.error("Failed to fetch patient details:", data.message);
                    }
                })
                .catch(error => {
                    console.error("Error fetching patient details:", error);
                    Swal.fire("Error", "Failed to load patient details. Please try again.", "error");
                })
                .finally(() => {
                    isFetching = false;
                });

            // Check if the selected patient has completed Initial Evaluation
            fetch(`patient/patient_data/check_patient_history.php?patient_id=${patientID}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    let ieOption = appointmentTypeDropdown.querySelector("option[value='Initial Evaluation']");
                    let playgroupOption = appointmentTypeDropdown.querySelector("option[value='Playgroup']");

                    if (data.completed_ie) {
                        if (ieOption) ieOption.disabled = true;
                        if (playgroupOption) appointmentTypeDropdown.value = "Playgroup";
                    } else {
                        if (ieOption) ieOption.disabled = false;
                    }
                })
                .catch(error => {
                    console.error("Error fetching patient history:", error);
                });
        });
    });

    document.addEventListener("DOMContentLoaded", function () {
    let appointmentTypeDropdown = document.getElementById("appointment_type");
    let dateTimeContainer = document.getElementById("date_time_container");
    let playgroupSessionContainer = document.getElementById("playgroup_session_container");
    let playgroupSessionDropdown = document.getElementById("pg_session_id");
    let selectedDateDisplay = document.getElementById("pg_selected_date");
    let selectedTimeDisplay = document.getElementById("pg_selected_time");

    // ✅ When the appointment type changes
    appointmentTypeDropdown.addEventListener("change", function () {
        if (this.value === "Playgroup") {
            fetchOpenPlaygroupSessions(); // Fetch available sessions
            dateTimeContainer.style.display = "none"; // Hide manual date/time selection
            playgroupSessionContainer.style.display = "block"; // Show session dropdown
            playgroupSessionDropdown.required = true;
        } else {
            dateTimeContainer.style.display = "block"; // Show date/time selection for other types
            playgroupSessionContainer.style.display = "none"; // Hide session selection
            playgroupSessionDropdown.required = false;
        }
    });

    // ✅ Fetch only OPEN Playgroup sessions
    function fetchOpenPlaygroupSessions() {
        fetch("app_data/get_open_playgroup_sessions.php") // Fetch open sessions
            .then(response => response.json())
            .then(data => {
                playgroupSessionDropdown.innerHTML = ""; // Clear existing options

                if (data.status === "success" && data.sessions.length > 0) {
                    data.sessions.forEach(session => {
                        let option = document.createElement("option");
                        option.value = session.pg_session_id;
                        option.textContent = `${session.date} at ${session.time} (${session.current_count}/${session.max_capacity})`;
                        playgroupSessionDropdown.appendChild(option);
                    });

                    // ✅ Auto-fill the first session's date/time
                    let firstSession = data.sessions[0];
                    playgroupSessionDropdown.value = firstSession.pg_session_id;
                    selectedDateDisplay.textContent = firstSession.date;
                    selectedTimeDisplay.textContent = firstSession.time;
                } else {
                    let option = document.createElement("option");
                    option.value = "";
                    option.disabled = true;
                    option.selected = true;
                    option.textContent = "No open playgroup sessions available";
                    playgroupSessionDropdown.appendChild(option);
                }
            })
            .catch(error => {
                console.error("Error fetching playgroup sessions:", error);
            });
    }

    // ✅ Update selected date/time when a different session is chosen
    playgroupSessionDropdown.addEventListener("change", function () {
        let selectedOption = this.options[this.selectedIndex];
        let sessionText = selectedOption.textContent.match(/(\d{4}-\d{2}-\d{2}) at (\d{2}:\d{2}:\d{2})/);
        if (sessionText) {
            selectedDateDisplay.textContent = sessionText[1];
            selectedTimeDisplay.textContent = sessionText[2];
        }
    });

});


</script>

</body>
</html>
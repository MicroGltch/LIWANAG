    <?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

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
// Fetch system settings
$settingsQuery = "SELECT min_days_advance, max_days_advance FROM settings LIMIT 1";
$settingsResult = $connection->query($settingsQuery);
$settings = $settingsResult->fetch_assoc();

$minDaysAdvance = $settings['min_days_advance'] ?? 3;
$maxDaysAdvance = $settings['max_days_advance'] ?? 30;

// Per-day business hours
$bizHoursByDay = [];
$dayQuery = $connection->query("SELECT day_name, start_time, end_time FROM business_hours_by_day");
while ($row = $dayQuery->fetch_assoc()) {
    $bizHoursByDay[$row['day_name']] = [
        'start' => $row['start_time'],
        'end'   => $row['end_time']
    ];
}

$closedDays = array_keys(array_filter($bizHoursByDay, fn($v) => is_null($v['start']) || is_null($v['end'])));

// Open override exceptions
$openOverrideDates = [];
$exceptions = $connection->query("SELECT DATE(exception_date) as exception_date FROM business_hours_exceptions WHERE start_time IS NOT NULL AND end_time IS NOT NULL");
while ($row = $exceptions->fetch_assoc()) {
    $openOverrideDates[] = $row['exception_date'];
}

// Get original appointment date/time
$originalQuery = "SELECT date, time FROM appointments WHERE appointment_id = ?";
$originalStmt = $connection->prepare($originalQuery);
$originalStmt->bind_param("i", $appointmentID);
$originalStmt->execute();
$originalResult = $originalStmt->get_result();
$originalAppointment = $originalResult->fetch_assoc();

$nextWeekDate = null;
$originalTime = null;

if ($originalAppointment) {
    $originalDate = $originalAppointment['date'];
    $originalTime = $originalAppointment['time'];
    $nextWeekDate = date('Y-m-d', strtotime($originalDate . ' +7 days'));
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


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        html, body {
        background-color: #ffffff !important;
    }

    </style>
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
            <input class="uk-input" type="text" name="new_date" id="new_date" required>

            <label>Time for next session:</label>
            <select class="uk-select" name="new_time" id="new_time" required></select>



            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Rebook Appointment</button>
            <a href="../app_manage/upcoming_appointments.php" class="uk-button uk-button-default">Cancel</a>
            </form>
            <?php if ($nextWeekDate && $originalTime): ?>
                <hr class="uk-margin">
                <p class="uk-text-muted">Or fill in the same schedule as this week:</p>

                <button type="button"
                    id="fillSameDateBtn"
                    class="uk-button uk-button-secondary uk-margin-small-top"
                    data-date="<?= $nextWeekDate; ?>"
                    data-time="<?= $originalTime; ?>">
                    Rebook Same Day & Time for Next Week (<?= date("F j, Y", strtotime($nextWeekDate)); ?> at <?= date("g:i A", strtotime($originalTime)); ?>)
                </button>

                <p id="quickRebookInvalidMsg" class="uk-text-danger" style="display: none;">
                    ⚠️ Cannot prefill <?= date("F j, Y", strtotime($nextWeekDate)); ?> — schedule not available.
                </p>
            <?php endif; ?>



    </div>

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
                Swal.fire({
                    title: "Success!",
                    text: data.message,
                    icon: "success",
                    confirmButtonText: "OK"
                }).then(() => {
                    window.location.href = "../app_manage/upcoming_appointments.php";
                });
            } else {
                Swal.fire({
                    title: "Error!",
                    text: data.message,
                    icon: "error"
                });
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            Swal.fire({
                title: "Error!",
                text: "Something went wrong. Please try again.",
                icon: "error"
            });
        });
    });

    document.addEventListener("DOMContentLoaded", () => {
        const closedDays = <?= json_encode($closedDays) ?>;
const openOverrideDates = <?= json_encode($openOverrideDates) ?>;
const minDaysAdvance = <?= $minDaysAdvance ?>;
const maxDaysAdvance = <?= $maxDaysAdvance ?>;

const dateInput = document.getElementById("new_date");
const timeInput = document.getElementById("new_time");

// Flatpickr to handle weekday hours + override
const today = new Date();
const minDate = new Date();
minDate.setDate(today.getDate() + minDaysAdvance);
const maxDate = new Date();
maxDate.setDate(today.getDate() + maxDaysAdvance);

flatpickr(dateInput, {
    minDate: minDate.toISOString().split("T")[0],
    maxDate: maxDate.toISOString().split("T")[0],
    dateFormat: "Y-m-d",
    disable: [
        function(date) {
            const iso = date.toLocaleDateString("en-CA"); // YYYY-MM-DD
            const weekdayMap = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
            const weekday = weekdayMap[date.getDay()];
            if (openOverrideDates.includes(iso)) return false;
            return closedDays.includes(weekday);
        }
    ],
    onChange: function () {
        updateAvailableTimes();
    }
});

// Update time options based on selected date
function updateAvailableTimes(callback = null) {
    const selectedDate = dateInput.value;
    if (!selectedDate) return;

    fetch(`../app_data/get_available_hours.php?date=${selectedDate}`)
        .then(res => res.json())
        .then(data => {
            timeInput.innerHTML = "";

            if (data.status !== "open") {
                const option = document.createElement("option");
                option.value = "";
                option.textContent = "Closed";
                timeInput.appendChild(option);
                timeInput.disabled = true;

                if (callback) callback(false);
                return;
            }

            const start = data.start;
            const end = data.end;
            const interval = 60;

            const [startHour, startMin] = start.split(":").map(Number);
            const [endHour, endMin] = end.split(":").map(Number);

            let current = new Date();
            current.setHours(startHour, startMin, 0, 0);

            const endTime = new Date();
            endTime.setHours(endHour, endMin, 0, 0);

            while (current < endTime) {
                const hours = current.getHours();
                const minutes = current.getMinutes();
                const ampm = hours >= 12 ? "PM" : "AM";
                const formattedHour = hours % 12 === 0 ? 12 : hours % 12;
                const formattedTime = `${formattedHour}:${minutes.toString().padStart(2, '0')} ${ampm}`;
                const value24 = current.toTimeString().slice(0, 5); // "HH:MM"

                const option = document.createElement("option");
                option.value = value24;
                option.textContent = formattedTime;
                timeInput.appendChild(option);

                current.setMinutes(current.getMinutes() + interval);
            }

            timeInput.disabled = false;

            if (callback) callback(true); // ✅ let caller know it's done
        });
}


    const btn = document.getElementById("fillSameDateBtn");
    if (!btn) return;

    const nextWeekDate = btn.dataset.date;
    const originalTime = btn.dataset.time;

    fetch(`../app_data/get_available_hours.php?date=${nextWeekDate}`)
    .then(response => response.json())
    .then(data => {
        if (data.status === "open") {
            const start = data.start;
            const end = data.end;

            const originalTrimmed = originalTime.slice(0, 5); // "HH:MM"

            if (originalTrimmed >= start && originalTrimmed < end) {
                btn.style.display = "inline-block";
            } else {
                document.getElementById("quickRebookInvalidMsg").style.display = "block";
                btn.style.display = "none";
            }
        } else {
            document.getElementById("quickRebookInvalidMsg").style.display = "block";
            btn.style.display = "none";
        }
    })

        .catch(err => {
            console.error("Error checking availability:", err);
            document.getElementById("quickRebookInvalidMsg").style.display = "block";
            btn.style.display = "none";
        });

        btn.addEventListener("click", () => {
    // Set the date
    dateInput._flatpickr.setDate(nextWeekDate, true);

    // Wait for times to be populated
    updateAvailableTimes((success) => {
        if (!success) {
            Swal.fire("Date not available", "The selected date is closed or unavailable.", "warning");
            return;
        }

        const options = [...timeInput.options];
        const originalTrimmed = originalTime.slice(0, 5); // "14:00"
        const match = options.find(opt => opt.value === originalTrimmed);


        if (match) {
            timeInput.value = originalTrimmed;
        } else {
            Swal.fire("Time not available", "The original time isn't available next week.", "warning");
        }
    });
});

});

</script>
</body>
</html>

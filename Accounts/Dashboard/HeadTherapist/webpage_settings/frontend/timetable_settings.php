<?php
require_once "../../../../../dbconfig.php";
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['account_ID'])) {
    header("Location: ../../../loginpage.php");
    exit();
}

// ✅ Ensure only Admin/Head Therapist can access
if (!isset($_SESSION['account_Type']) || strtolower($_SESSION['account_Type']) !== "admin") {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: ../../../loginpage.php");
    exit();
}

// Fetch current settings
$query = "SELECT *, DATE_FORMAT(updated_at, '%M %d, %Y %h:%i %p') AS formatted_updated_at FROM settings LIMIT 1";
$result = $connection->query($query);
$settings = $result->fetch_assoc();
$blockedDates = json_decode($settings['blocked_dates'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- ✅ Flatpickr Library for Multi-Date Selection -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>System Settings</h2>
        
        <p><strong>Last Updated:</strong> <?= $settings['formatted_updated_at'] ?? 'Never' ?></p>

        <form id="settingsForm" method="POST" class="uk-form-stacked">
            <label>Business Hours Start:</label>
            <input class="uk-input" type="time" name="business_hours_start" value="<?= $settings['business_hours_start']; ?>" required>

            <br/>

            <label>Business Hours End:</label>
            <input class="uk-input" type="time" name="business_hours_end" value="<?= $settings['business_hours_end']; ?>" required>

            <br/>
            
            <label>Max Booking Days (Advance):</label>
            <input class="uk-input" type="number" name="max_days_advance" value="<?= $settings['max_days_advance']; ?>" min="1" max="60" required>

            <br/>
            
            <label>Min Days Before Appointment (Required Advance Booking):</label>
            <input class="uk-input" type="number" name="min_days_advance" value="<?= $settings['min_days_advance']; ?>" min="0" max="30" required>

            <br/>
            
            <label>Blocked Dates:</label>
            <input class="uk-input" type="text" id="blocked_dates" name="blocked_dates" placeholder="Select dates..." required>

            <br/>
            
            <label>Initial Evaluation Duration (Minutes):</label>
            <input class="uk-input" type="number" name="initial_eval_duration" value="<?= $settings['initial_eval_duration']; ?>" min="30" max="180" required>

            <br/>
            
            <label>Playgroup Duration (Minutes):</label>
            <input class="uk-input" type="number" name="playgroup_duration" value="<?= $settings['playgroup_duration']; ?>" min="60" max="240" required>

            <br/>
            
            <label>Occupational Therapy Session Duration (Minutes):</label>
            <input class="uk-input" type="number" name="service_ot_duration" value="<?= $settings['service_ot_duration']; ?>" min="30" max="180" required>

            <br/>
            
            <label>Behavioral Therapy Session Duration (Minutes):</label>
            <input class="uk-input" type="number" name="service_bt_duration" value="<?= $settings['service_bt_duration']; ?>" min="30" max="180" required>

            <br/>
            
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Settings</button>
            <a href="../../frontend/headtherapist_dashboard.php">BACK TO DASHBOARD</a>
        </form>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // ✅ Blocked Dates Multi-Date Picker
        flatpickr("#blocked_dates", {
            minDate: "today",
            altInput: true,
            mode: "multiple",
            dateFormat: "Y-m-d",
            defaultDate: <?= json_encode($blockedDates) ?> // Load existing blocked dates
        });

        // ✅ Save Settings with Fetch
        document.getElementById("settingsForm").addEventListener("submit", function (event) {
            event.preventDefault();
            let formData = new FormData(this);

            fetch("../backend/update_timetable_settings.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.fire({
                    title: data.status === "success" ? "Success!" : "Error!",
                    text: data.message,
                    icon: data.status === "success" ? "success" : "error",
                    confirmButtonText: "OK"
                });

                // ✅ Update Last Updated Time without refreshing the page
                if (data.updated_at) {
                    document.querySelector("p strong").nextSibling.textContent = " " + data.updated_at;
                }
            })
            .catch(error => {
                console.error("Fetch Error:", error);
                Swal.fire("Error!", "Something went wrong. Check the console.", "error");
            });
        });
    });
    </script>
</body>
</html>

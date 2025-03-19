<?php
require_once "../../../dbconfig.php";
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['account_ID'])) {
    header("Location: ../../../Accounts/loginpage.php");
    exit();
}

// ✅ Ensure only Admin/Head Therapist can access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: ../../../Accounts/loginpage.php");
    exit();
}

$userid = $_SESSION['account_ID'];

$stmt = $connection->prepare("SELECT account_FName, account_LName, account_Email, account_PNum, profile_picture FROM users WHERE account_ID = ?");
$stmt->bind_param("s", $userid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $firstName = $userData['account_FName'];
    $lastName = $userData['account_LName'];
    $email = $userData['account_Email'];
    $phoneNumber = $userData['account_PNum'];

    if ($userData['profile_picture']) {
        $profilePicture = '../uploads/client_profile_pictures/' . $userData['profile_picture'];
    } else {
        $profilePicture = '../CSS/default.jpg';
    }
} else {
    echo "No Data Found.";
}

$stmt->close();

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

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../../CSS/style.css" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!--SWAL-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ✅ Flatpickr Library for Multi-Date Selection -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        html,
        body {
            background-color: #ffffff !important;
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <h2>System Settings</h2>

    <p><strong>Last Updated:</strong> <?= $settings['formatted_updated_at'] ?? 'Never' ?></p>

    <form id="settingsForm" method="POST" class="uk-form-stacked">
        <div class="uk-margin">
            <label class="uk-form-label">Business Hours Start:</label>
            <div class="uk-form-controls">
                <input class="uk-input uk-width-1-1" type="time" name="business_hours_start" value="<?= $settings['business_hours_start']; ?>" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Business Hours End:</label>
            <div class="uk-form-controls">
                <input class="uk-input uk-width-1-1" type="time" name="business_hours_end" value="<?= $settings['business_hours_end']; ?>" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Max Booking Days (Advance):</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="number" name="max_days_advance" value="<?= $settings['max_days_advance']; ?>" min="1" max="60" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Min Days Before Appointment (Required Advance Booking):</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="number" name="min_days_advance" value="<?= $settings['min_days_advance']; ?>" min="0" max="30" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Blocked Dates:</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="text" id="blocked_dates" name="blocked_dates" placeholder="Select dates..." required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Initial Evaluation Duration (Minutes):</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="number" name="initial_eval_duration" value="<?= $settings['initial_eval_duration']; ?>" min="30" max="180" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Playgroup Duration (Minutes):</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="number" name="playgroup_duration" value="<?= $settings['playgroup_duration']; ?>" min="60" max="240" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Occupational Therapy Session Duration (Minutes):</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="number" name="service_ot_duration" value="<?= $settings['service_ot_duration']; ?>" min="30" max="180" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label">Behavioral Therapy Session Duration (Minutes):</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="number" name="service_bt_duration" value="<?= $settings['service_bt_duration']; ?>" min="30" max="180" required>
            </div>
        </div>

        <div class="uk-text-right">
            <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Settings</button>
        </div>

    </form>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // ✅ Blocked Dates Multi-Date Picker
            flatpickr("#blocked_dates", {
                minDate: "today",
                altInput: true,
                mode: "multiple",
                dateFormat: "Y-m-d",
                defaultDate: <?= json_encode($blockedDates) ?> // Load existing blocked dates
            });

            // ✅ Save Settings with Fetch
            document.getElementById("settingsForm").addEventListener("submit", function(event) {
                event.preventDefault();
                let formData = new FormData(this);

                fetch("update_timetable_settings.php", {
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
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

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../../../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../../../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../../../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../../../../CSS/style.css" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!--SWAL-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ✅ Flatpickr Library for Multi-Date Selection -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>

<body>
    <script>
        console.log('Session Username:', <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>);
    </script>
    <!-- Navbar -->
    <nav class="uk-navbar-container logged-in">
        <div class="uk-container">
            <div uk-navbar>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li>
                            <a href="#" class="uk-navbar-item">
                                <img class="profile-image" src="../CSS/default.jpg" alt="Profile Image" uk-img>
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;"> <?php echo $_SESSION['username']; ?>
                        </li>
                        <li><a href="../Accounts/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <hr class="solid">

    <!-- Main Content -->
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-height-viewport">
        <!--Sidebar-->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
            <button class="uk-button uk-button-default uk-hidden@m uk-width-1-1 uk-margin-bottom sidebar-toggle" type="button">
                Menu <span uk-navbar-toggle-icon></span>
            </button>
            <div class="sidebar-nav">
                <ul class="uk-nav uk-nav-default">
                    <li><a href="../../../HeadTherapist/frontend/headtherapist_dashboard.php">Dashboard</a></li>
                    <li class="uk-active"><a href="timetable_settings.php">Manage Timetable Settings</a></li>
                    <li><a href="../../../appointments/frontend/manage_appointments.php">View & Manage Appointments</a></li>
                    <li><a href="../../../HeadTherapist/frontend/view_all_appointments.php">View All Appointments</a></li>
                    <li><a href="">Manage Therapists [NOT IMPLEMENTED YET]</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-4-5@m uk-padding">
            <div class="uk-card uk-card-default uk-card-body form-card">
                <h2>System Settings</h2>

                <p><strong>Last Updated:</strong> <?= $settings['formatted_updated_at'] ?? 'Never' ?></p>

                <form id="settingsForm" method="POST" class="uk-form-stacked">
                    <label>Business Hours Start:</label>
                    <input class="uk-input" type="time" name="business_hours_start" value="<?= $settings['business_hours_start']; ?>" required>

                    <br />

                    <label>Business Hours End:</label>
                    <input class="uk-input" type="time" name="business_hours_end" value="<?= $settings['business_hours_end']; ?>" required>

                    <br />

                    <label>Max Booking Days (Advance):</label>
                    <input class="uk-input" type="number" name="max_days_advance" value="<?= $settings['max_days_advance']; ?>" min="1" max="60" required>

                    <br />

                    <label>Min Days Before Appointment (Required Advance Booking):</label>
                    <input class="uk-input" type="number" name="min_days_advance" value="<?= $settings['min_days_advance']; ?>" min="0" max="30" required>

                    <br />

                    <label>Blocked Dates:</label>
                    <input class="uk-input" type="text" id="blocked_dates" name="blocked_dates" placeholder="Select dates..." required>

                    <br />

                    <label>Initial Evaluation Duration (Minutes):</label>
                    <input class="uk-input" type="number" name="initial_eval_duration" value="<?= $settings['initial_eval_duration']; ?>" min="30" max="180" required>

                    <br />

                    <label>Playgroup Duration (Minutes):</label>
                    <input class="uk-input" type="number" name="playgroup_duration" value="<?= $settings['playgroup_duration']; ?>" min="60" max="240" required>

                    <br />

                    <label>Occupational Therapy Session Duration (Minutes):</label>
                    <input class="uk-input" type="number" name="service_ot_duration" value="<?= $settings['service_ot_duration']; ?>" min="30" max="180" required>

                    <br />

                    <label>Behavioral Therapy Session Duration (Minutes):</label>
                    <input class="uk-input" type="number" name="service_bt_duration" value="<?= $settings['service_bt_duration']; ?>" min="30" max="180" required>

                    <br />

                    <div class="uk-text-right">
                        <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
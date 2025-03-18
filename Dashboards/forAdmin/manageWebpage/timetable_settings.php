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
                                <img class="profile-image" src="../../../CSS/default.jpg" alt="Profile Image" uk-img>
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;"> <?php echo $_SESSION['username']; ?>
                        </li>
                        <li><a href="../../../Accounts/logout.php">Logout</a></li>
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
                    <li><a href="../../admindashboard.php">Dashboard</a></li>
                    <li><a href="#accounts" onclick="showSection('accounts')">Accounts</a></li>
                    <li class="uk-active"><a href="timetable_settings.php">Manage Timetable Settings</a></li>
                    <li><a href="../../../Appointments/app_manage/view_all_appointments.php">View All Appointments</a></li>
                    <li><a href="">Manage Therapists [NOT IMPLEMENTED YET]</a></li>
                    <li><a href="">System Analytics</a></li>
                    <li><a href="">Manage Website Contents</a></li>
                    <li><a href="#account-details" onclick="showSection('account-details')">Profile</a></li>
                    <li><a href="#settings" onclick="showSection('settings')">Settings</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-4-5@m uk-padding">
            <div id="system-settings" class="section">
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
            <!-- Accounts Section -->
            <div id="accounts" class="section" style="display: none;">
                <h1 class="uk-text-bold">Accounts</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <div class="uk-overflow-auto">
                        <table id="accountsTable" class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th class="uk-table-shrink">Name <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Service Type <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Assigned Therapist <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Guardian <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Content -->
                            </tbody>
                        </table>

                        <script>
                            $(document).ready(function() {
                                $('#accountsTable').DataTable({
                                    pageLength: 10,
                                    lengthMenu: [10, 25, 50],
                                    order: [
                                        [0, 'asc']
                                    ], // Sort by name column by default
                                    language: {
                                        lengthMenu: "Show _MENU_ entries per page",
                                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                                        search: "Search:",
                                        paginate: {
                                            first: "First",
                                            last: "Last",
                                            next: "Next",
                                            previous: "Previous"
                                        }
                                    },
                                    columnDefs: [{
                                            orderable: true,
                                            targets: '_all'
                                        },
                                        {
                                            orderable: false,
                                            targets: 4
                                        }
                                    ]
                                });
                            });
                        </script>
                    </div>
                </div>
            </div>

            <!-- Manage Therapists -->

            <div id="manage-therapist" class="section" style="display: none;">
                <h1 class="uk-text-bold">Manage Therapists</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <div class="uk-overflow-auto">
                        <table id="managetherapistTable" class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th class="uk-table-shrink">First Name<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Last Name<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Email<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Phone Number<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Account Status<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Appointments<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Content -->
                            </tbody>
                        </table>

                        <script>
                            $(document).ready(function() {
                                $('#managetherapistTable').DataTable({
                                    pageLength: 10,
                                    lengthMenu: [10, 25, 50],
                                    order: [
                                        [0, 'asc']
                                    ], // Sort by name column by default
                                    language: {
                                        lengthMenu: "Show _MENU_ entries per page",
                                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                                        search: "Search:",
                                        paginate: {
                                            first: "First",
                                            last: "Last",
                                            next: "Next",
                                            previous: "Previous"
                                        }
                                    },
                                    columnDefs: [{
                                            orderable: true,
                                            targets: '_all'
                                        } // Make all columns sortable
                                    ]
                                });
                            });
                        </script>
                    </div>
                </div>
            </div>

            <!-- Account Details Section -->
            <div id="account-details" class="section" style="display: none;">
                <h1 class="uk-text-bold">Account Details</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <div class="uk-flex uk-flex-center">
                        <div class="uk-width-1-4">
                            <img class="uk-border-circle" src="<?php echo $profilePicture; ?>" alt="Profile Photo">
                        </div>
                    </div>
                </div>

                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title uk-text-bold">User Details</h3>
                    <form class="uk-grid-small" uk-grid>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" value="<?php echo $firstName; ?>" disabled>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" value="<?php echo $lastName; ?>" disabled>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" value="<?php echo $email; ?>" disabled>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel" value="<?php echo $phoneNumber; ?>" disabled>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Settings Section -->
            <div id="settings" class="section" style="display: none;">
                <h1 class="uk-text-bold">Settings</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <form action="settings.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_profile_picture">
                        <div class="uk-flex uk-flex-middle">
                            <div class="profile-upload-container">
                                <img class="uk-border-circle profile-preview" src="<?php echo $profilePicture; ?>" alt="Profile Photo">
                                <div class="uk-flex uk-flex-column uk-margin-left">
                                    <input type="file" name="profile_picture" id="profileUpload" class="uk-hidden" onchange="previewProfilePhoto(event)">
                                    <button class="uk-button uk-button-primary uk-margin-small-bottom" onclick="document.getElementById('profileUpload').click();">Upload Photo</button>
                                    <div class="uk-text-center">
                                        <a href="#" class="uk-link-muted" onclick="removeProfilePhoto();">remove</a>
                                    </div>
                                </div>
                                <div class="uk-margin-large-left">
                                    <h4>Image requirements:</h4>
                                    <ul class="uk-list">
                                        <li>1. Min. 400 x 400px</li>
                                        <li>2. Max. 2MB</li>
                                        <li>3. Your face</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="uk-button uk-button-primary uk-margin-top">Upload</button>
                    </form>
                </div>

                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title uk-text-bold">User Details</h3>
                    <form id="settingsvalidate" action="../Accounts/manageaccount/updateinfo.php" method="post" class="uk-grid-small" uk-grid>
                        <input type="hidden" name="action" value="update_user_details">
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" name="firstName" id="firstName" value="<?php echo $firstName; ?>">
                            <small style="color: red;" class="error-message" data-error="firstName"></small>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" name="lastName" id="lastName" value="<?php echo $lastName; ?>">
                            <small style="color: red;" class="error-message" data-error="lastName"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" name="email" id="email" value="<?php echo $email; ?>">
                            <small style="color: red;" class="error-message" data-error="email"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel" name="phoneNumber" id="mobileNumber"
                                value="<?= htmlspecialchars($_SESSION['phoneNumber'] ?? $phoneNumber, ENT_QUOTES, 'UTF-8') ?>">
                            <small style="color: red;" class="error-message" data-error="phoneNumber"></small>
                        </div>
                        <small style="color: red;" class="error-message" data-error="duplicate"></small>
                        <small style="color: green;" class="error-message" id="successMessage"></small>
                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button class="uk-button uk-button-primary" type="submit">Save Changes</button>
                        </div>
                    </form>
                    <?php unset($_SESSION['update_errors']); // Clear errors after displaying 
                    ?>
                    <?php unset($_SESSION['update_success']); // Clear success message 
                    ?>
                </div>
            </div>
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

        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar-nav').classList.toggle('uk-open');
        });

        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById(sectionId).style.display = 'block';
        }

        function previewProfilePhoto(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const preview = document.querySelector('.profile-preview');
                preview.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        function removeProfilePhoto() {
            document.querySelector('.profile-preview').src = '../CSS/default.jpg';
        }
    </script>
</body>

</html>
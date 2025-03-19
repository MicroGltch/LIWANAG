<?php
require_once "../dbconfig.php";
session_start();

// ✅ Ensure only Admins & Head Therapists can access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["head therapist"])) {
    header("Location: ../Accounts/loginpage.php");
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

// Define all possible status types
$allStatuses = ['pending', 'approved', 'waitlisted', 'completed', 'cancelled', 'declined', 'others'];

// Initialize counts for all status types with 0
$appointmentCounts = array_fill_keys($allStatuses, 0);

// ✅ Query to count appointments by type
$countQuery = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$result = $connection->query($countQuery);
while ($row = $result->fetch_assoc()) {
    $status = strtolower($row['status']);
    if (in_array($status, $allStatuses)) {
        $appointmentCounts[$status] = $row['count'];
    } else {
        $appointmentCounts['others'] += $row['count'];
    }
}

// ✅ Query to get all appointments
$appointmentQuery = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.status, 
                            p.first_name, p.last_name,
                            u.account_FName AS client_firstname, u.account_LName AS client_lastname 
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.patient_id
                    JOIN users u ON a.account_id = u.account_ID
                    ORDER BY a.date ASC, a.time ASC";
$appointments = $connection->query($appointmentQuery)->fetch_all(MYSQLI_ASSOC);

// Get total count of all appointments
$totalQuery = "SELECT COUNT(*) as total FROM appointments";
$totalResult = $connection->query($totalQuery);
$totalAppointments = $totalResult->fetch_assoc()['total'];

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Appointment Overview</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../CSS/style.css" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!--SWAL-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                    <a class="uk-navbar-item uk-logo" href="../homepage.php">Little Wanderer's Therapy Center</a>
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
                    <!--<li class="uk-active"><a href="headtherapistdashboard.php">Dashboard</a></li>-->
                    <li class="uk-active"><a href="#dashboard" onclick="showSection('dashboard')">Dashboard</a></li>

                    <!--<li><a href="../Appointments/app_manage/manage_appointments.php">View & Manage Appointments</a></li>-->
                    <li><a href="#view-manage-appointments" onclick="showSection('view-manage-appointments')">View & Manage Appointments</a></li>

                    <!--<li><a href="../Appointments/app_manage/view_all_appointments.php">View All Appointments</a></li>-->
                    <li><a href="#view-appointments" onclick="showSection('view-appointments')">View All Appointments</a></li>

                    <li><a href="#account-details" onclick="showSection('account-details')">Account Details</a></li>
                    <li><a href="#settings" onclick="showSection('settings')">Settings</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">

            <!-- Dashboard Section 📑 -->
            <div id="dashboard" class="section">
                <h1 class="uk-text-bold">Head Therapist Panel</h1>

                <!-- ✅ Total Appointments Card -->
                <div class="uk-margin-bottom">
                    <div class="uk-card uk-card-primary uk-card-body">
                        <h3 class="uk-card-title">Total Appointments</h3>
                        <p>Total: <?= $totalAppointments ?></p>
                    </div>
                </div>

                <!-- ✅ Appointment Summary Cards -->
                <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                    <?php foreach ($appointmentCounts as $status => $count): ?>
                        <div>
                            <div class="uk-card uk-card-default uk-card-body">
                                <h3 class="uk-card-title"><?= ucfirst($status) ?></h3>
                                <p>Total: <?= $count ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr>

                <!-- ✅ Appointments List -->
                <h3>All Appointments</h3>
                <table id="appointmentsTable" class="uk-table uk-table-striped uk-table-hover">
                    <thead>
                        <tr>
                            <th class="uk-table-shrink">Patient <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Client <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Date <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Time <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Status <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                                <td><?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?></td>
                                <td><?= htmlspecialchars($appointment['date']); ?></td>
                                <td><?= htmlspecialchars($appointment['time']); ?></td>
                                <td><?= htmlspecialchars(ucfirst($appointment['status'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <script>
                    $(document).ready(function() {
                        $('#appointmentsTable').DataTable({
                            pageLength: 10,
                            lengthMenu: [10, 25, 50],
                            order: [
                                [2, 'asc']
                            ], // Sort by date column by default
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
                                }, // Make all columns sortable
                                {
                                    type: 'date',
                                    targets: 2
                                } // Specify date type for date column
                            ]
                        });
                    });
                </script>
            </div>

            <!-- View and Manage Appointments Section 📑 -->
            <div id="view-manage-appointments" class="section" style="display: none;">
                <h1 class="uk-text-bold">View & Manage Appointments</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="viewManageAppointmentsFrame" src="../Appointments/app_manage/manage_appointments.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>

            <!-- View All appointments Section 📑 -->
            <div id="view-appointments" class="section" style="display: none;">
                <h1 class="uk-text-bold">View All Appointments</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="viewAppointmentsFrame" src="../Appointments/app_manage/view_all_appointments.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>

            <!-- Account Details Section 📑 -->
            <div id="account-details" class="section" style="display: none;">
                <h1 class="uk-text-bold">Account Details</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <div class="uk-flex uk-flex-center">
                        <div class="uk-width-1-4">
                            <img class="uk-border-circle" src="../CSS/default.jpg" alt="Profile Photo">
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

            <!-- Settings Section 📑 -->
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
                                    <input type="file" name="profile_picture" id="profileUpload" class="uk-hidden">
                                    <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" id="uploadButton">
                                        Upload Photo
                                    </button>
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
                    </form>
                </div>

                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title uk-text-bold">User Details</h3>
                    <form id="settingsvalidate" action="../Accounts/manageaccount/updateinfo.php" method="post" class="uk-grid-small" uk-grid>
                        <input type="hidden" name="action" value="update_user_details">

                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" name="firstName" id="firstName" value="<?php echo $firstName; ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="firstName"></small>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" name="lastName" id="lastName" value="<?php echo $lastName; ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="lastName"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" name="email" id="email" value="<?php echo $email; ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="email"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel" name="phoneNumber" id="mobileNumber"
                                value="<?= htmlspecialchars($_SESSION['phoneNumber'] ?? $phoneNumber, ENT_QUOTES, 'UTF-8') ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="phoneNumber"></small>
                        </div>

                        <small style="color: red;" class="error-message" data-error="duplicate"></small>
                        <small style="color: green;" class="error-message" id="successMessage"></small>

                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button type="button" class="uk-button uk-button-secondary" id="editButton">Edit</button>
                            <button class="uk-button uk-button-primary" type="submit" id="saveButton" disabled>Save Changes</button>
                        </div>
                    </form>
                    <?php unset($_SESSION['update_errors']); ?>
                    <?php unset($_SESSION['update_success']); ?>
                </div>
            </div>

            <script>
                // Resize iframe
                function resizeIframe(iframe) {
                    iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 'px';
                }

                // Sidebar Toggle
                document.querySelector('.sidebar-toggle').addEventListener('click', function() {
                    document.querySelector('.sidebar-nav').classList.toggle('uk-open');
                });

                // Show Section
                function showSection(sectionId) {
                    document.querySelectorAll('.section').forEach(section => {
                        section.style.display = 'none';
                    });
                    const section = document.getElementById(sectionId);
                    section.style.display = 'block';

                    // Resize iframe if present in the section
                    const iframe = section.querySelector('iframe');
                    if (iframe) {
                        resizeIframe(iframe);
                    }

                    // Set active class on the sidebar link
                    document.querySelectorAll('.sidebar-nav a').forEach(link => {
                        link.parentElement.classList.remove('uk-active');
                    });
                    document.querySelector(`.sidebar-nav a[href="#${sectionId}"]`).parentElement.classList.add('uk-active');
                }

                // Preview Profile Photo
                function previewProfilePhoto(event) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        const preview = document.querySelector('.profile-preview');
                        preview.src = reader.result;
                    }
                    reader.readAsDataURL(event.target.files[0]);
                }

                // Remove Profile Photo
                function removeProfilePhoto() {
                    document.querySelector('.profile-preview').src = '../CSS/default.jpg';
                }

                // View and manage appointments iframe
                let viewManageAppointmentsFrame = document.getElementById('viewManageAppointmentsFrame');

                viewManageAppointmentsFrame.onload = function() {
                    resizeIframe(viewManageAppointmentsFrame);
                    let viewManageAppointmentsForm = viewManageAppointmentsFrame.contentDocument.getElementById("viewManageAppointmentsForm");

                    if (viewManageAppointmentsForm) {
                        viewManageAppointmentsForm.addEventListener('submit', function(e) {
                            e.preventDefault();

                            let formData = new FormData(this);

                            fetch("../Appointments/app_manage/manage_appointments.php", {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.swal) {
                                        Swal.fire({
                                            title: data.swal.title,
                                            text: data.swal.text,
                                            icon: data.swal.icon,
                                        }).then(() => {
                                            if (data.reload) {
                                                window.location.reload(true); // Hard reload the page
                                            }
                                        });
                                    }
                                })
                                .catch(error => console.error("Error:", error));
                        });
                    }
                }

                // view all appointments iframe
                let viewAppointmentsFrame = document.getElementById("viewAppointmentsFrame");

                viewAppointmentsFrame.onload = function() {
                    resizeIframe(viewAppointmentsFrame);
                    let viewAppointmentsForm = viewAppointmentsFrame.contentDocument.getElementById("viewAppointmentsForm");

                    if (viewAppointmentsForm) {
                        viewAppointmentsForm.addEventListener("submit", function(e) {
                            e.preventDefault();

                            let formData = new FormData(this);

                            fetch("../Appointments/app_manage/view_all_appointments.php", {
                                    method: "POST",
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.swal) {
                                        Swal.fire({
                                            title: data.swal.title,
                                            text: data.swal.text,
                                            icon: data.swal.icon,
                                        }).then(() => {
                                            if (data.reload) {
                                                window.location.reload(true); // Hard reload the page
                                            }
                                        });
                                    }
                                })
                                .catch(error => console.error("Error:", error));
                        });
                    }
                };
            </script>
        </div>



        <script>

        </script>

</body>

</html>
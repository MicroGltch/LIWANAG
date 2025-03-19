<?php
require_once "../dbconfig.php";
session_start();

// ✅ Ensure only Admins & Head Therapists can access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../Accounts/loginpage.php");
    exit();
}

$userid = $_SESSION['account_ID'];

// ✅ Fetch user profile data (admin/head therapist)
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
    $profilePicture = $userData['profile_picture'] ? '../uploads/client_profile_pictures/' . $userData['profile_picture'] : '../CSS/default.jpg';
} else {
    echo "No Data Found.";
}

$stmt->close();

// ✅ Fetch clients data for the table
try {
    $stmt = $connection->prepare("
        SELECT account_ID, account_FName, account_LName, account_Email, account_PNum, account_status
        FROM users WHERE account_Type = 'client'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $clients = $result->fetch_all(MYSQLI_ASSOC);

    // Fetch appointment counts for each client
    foreach ($clients as &$client) {
        $clientId = $client['account_ID'];
        $countStmt = $connection->prepare("
            SELECT COUNT(*) AS appointment_count
            FROM appointments
            WHERE account_id = ?
        ");
        $countStmt->bind_param("s", $clientId);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countRow = $countResult->fetch_assoc();
        $client['appointment_count'] = $countRow['appointment_count'];
        $countStmt->close();
    }
} catch (Exception $e) {
    $client_error = $e->getMessage();
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}

// ✅ Fetch patients data for the table
try {
    $stmt = $connection->prepare(" SELECT p.patient_id, p.account_id, 
        p.first_name AS patient_fname, p.last_name AS patient_lname, 
        p.bday, p.gender, p.profile_picture, 
        u.account_FName AS client_fname, u.account_LName AS client_lname
        FROM patients p
        INNER JOIN users u ON p.account_id = u.account_ID;
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $patients = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $patient_error = $e->getMessage();
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}

// ✅ Fetch therapists data for the table
try {
    $stmt = $connection->prepare(" SELECT account_FName, account_LName, account_Email, account_PNum, account_status
        FROM users WHERE account_Type = 'therapist'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $therapists = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $therapist_error = $e->getMessage();
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
}

// ✅ Appointment summary (Dashboard)
$allStatuses = ['pending', 'approved', 'waitlisted', 'completed', 'cancelled', 'declined', 'others'];
$appointmentCounts = array_fill_keys($allStatuses, 0);

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

// ✅ Get all appointments (Dashboard)
$appointmentQuery = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.status, 
    p.first_name, p.last_name,
    u.account_FName AS client_firstname, u.account_LName AS client_lastname 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON a.account_id = u.account_ID
    ORDER BY a.date ASC, a.time ASC";
$appointments = $connection->query($appointmentQuery)->fetch_all(MYSQLI_ASSOC);

// ✅ Get total count of all appointments (Dashboard)
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
                    <li class="uk-active"><a href="#dashboard" onclick="showSection('dashboard')">Dashboard</a></li>
                    
                    <li><a href="#view-appointments" onclick="showSection('view-appointments')">View All Appointments</a></li>
                    
                    <li class="uk-parent">
                        <a>Accounts</a>
                        <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                            <li style="padding:0px 0px 15px 0px"><a href="#clients" onclick="showSection('clients')"> Clients</a></li>
                            <li style="padding:0px 0px 15px 0px"><a href="#patients" onclick="showSection('patients')"> Patients</a></li>
                        </ul>
                    </li>

                    <li class="uk-parent">
                        <a href="#manage-therapist" onclick="showSection('manage-therapist')"> Manage Therapists</a>
                            <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                            <li style="padding:0px 0px 15px 0px"><a href="#add-therapist" onclick="showSection('add-therapist')"> Add Therapist</a></li>
                        </ul>
                    </li>


                    <li><a href="#system-analytics" onclick="showSection('system-analytics')">System Analytics</a></li>
                    
                    <li><a href="#timetable-settings" onclick="showSection('timetable-settings')">Manage Timetable Settings</a></li>

                    <li><a href="#manage-content" onclick="showSection('manage-content')">Manage Webpage Contents</a></li>

                    <!-- To follow & confirm-->
                    <li><a href="#account-details" onclick="showSection('account-details')">Account Details</a></li>
                    <li><a href="#settings" onclick="showSection('settings')">Settings</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">

            <!-- Dashboard Section 📑 -->
            <div id="dashboard" class="section">
                <h1 class="uk-text-bold">Admin Panel</h1>

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
            </div>

            <!-- View All Appointments Section 📑-->
            <div id="view-appointments" class="section" style="display: none;">
                <h1 class="uk-text-bold">View All Appointments</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="viewAppointmentsFrame" src="../Appointments/app_manage/view_all_appointments.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>


            <!-- Accounts Section 📑 -->
            <!-- Clients -->
            <div id="clients" class="section" style="display: none;">
                <h1 class="uk-text-bold">Clients</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <div class="uk-overflow-auto">
                        <table id="clientsTable" class="uk-table uk-table-striped uk-table-hover">
                        <thead>
                            <tr>
                                <th class="uk-table-shrink">Account ID<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="uk-table-shrink">First Name<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="uk-table-shrink">Last Name<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="uk-table-shrink">Email<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="uk-table-shrink">Phone Number<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="uk-table-shrink">Account Status<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="uk-table-shrink">Appointments<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="uk-table-shrink">Actions<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($clients) && !empty($clients)) : ?>
                                <?php foreach ($clients as $client) : ?>
                                    <tr>
                                    <td><?= htmlspecialchars($client['account_ID']); ?></td>
                                    <td><?= htmlspecialchars($client['account_FName']); ?></td>
                                    <td><?= htmlspecialchars($client['account_LName']); ?></td>
                                    <td><?= htmlspecialchars($client['account_Email']); ?></td>
                                    <td><?= htmlspecialchars($client['account_PNum']); ?></td>
                                    <td><?= htmlspecialchars($client['account_status']); ?></td>
                                    <td><?= htmlspecialchars($client['appointment_count']); ?></td>
                                    <td><?php if ($client['account_status'] != 'Archived') { ?>
                                        <button class="uk-button uk-button-danger archive-user" data-account-id="<?= $client['account_ID']; ?>">Archive</button>
                                        <?php } ?>
                                        </td>
                                        <!-- Activate account logic to follow
                                        <button class="uk-button uk-button-danger activate-user" data-account-id=">Activate</button>
                                         -->
                                        
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif (isset($client_error)) : ?>
                                <tr><td colspan="7"><?= htmlspecialchars($client_error); ?></td></tr>
                            <?php else : ?>
                                <tr><td colspan="7">No clients found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        </table>

                        <script>
                            $(document).ready(function() {
                                $('#clientsTable').DataTable({
                                    pageLength: 10,
                                    lengthMenu: [10, 25, 50],
                                    order: [[0, 'asc']],
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
                                    columnDefs: [{ orderable: true, targets: '_all' }]
                                });
                            });
                        </script>
                    </div>
                </div>
            </div>

            <!-- Patients -->
            <div id="patients" class="section" style="display: none;">
                <h1 class="uk-text-bold">Patients</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <div class="uk-overflow-auto">
                        <table id="patientsTable" class="uk-table uk-table-striped uk-table-hover">
                            <thead>
                                <tr>
                                    <th class="uk-table-shrink">First Name<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Last Name<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Birthday<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Gender<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Parent/Guardian<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Profile Picture<span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($patients) && !empty($patients)) : ?>
                                    <?php foreach ($patients as $patient) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($patient['patient_fname']); ?></td>
                                            <td><?= htmlspecialchars($patient['patient_lname']); ?></td>
                                            <td><?= htmlspecialchars($patient['bday']); ?></td>
                                            <td><?= htmlspecialchars($patient['gender']); ?></td>
                                            <td><?= htmlspecialchars($patient['client_fname'] . ' ' . $patient['client_lname']); ?></td>
                                            <td>
                                                <?php if (!empty($patient['profile_picture'])) : ?>
                                                    <img src="../uploads/profile_pictures/<?= htmlspecialchars($patient['profile_picture']); ?>" alt="Patient Profile" style="max-width: 50px; max-height: 50px;">
                                                <?php else : ?>
                                                    No Picture
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php elseif (isset($patient_error)) : ?>
                                    <tr><td colspan="6"><?= htmlspecialchars($patient_error); ?></td></tr>
                                <?php else : ?>
                                    <tr><td colspan="6">No patients found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <script>
                            $(document).ready(function() {
                                $('#patientsTable').DataTable({
                                    pageLength: 10,
                                    lengthMenu: [10, 25, 50],
                                    order: [[0, 'asc']],
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
                                    columnDefs: [{ orderable: true, targets: '_all' }]
                                });
                            });
                        </script>
                    </div>
                </div>
            </div>

            <!-- Manage Therapists Section 📑-->
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
                                <?php if (isset($therapists) && !empty($therapists)) : ?>
                                    <?php foreach ($therapists as $therapist) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($therapist['account_FName']); ?></td>
                                            <td><?= htmlspecialchars($therapist['account_LName']); ?></td>
                                            <td><?= htmlspecialchars($therapist['account_Email']); ?></td>
                                            <td><?= htmlspecialchars($therapist['account_PNum']); ?></td>
                                            <td><?= htmlspecialchars($therapist['account_status']); ?></td>
                                            <td>
                                                <?php
                                                // Add logic here to count and display appointments
                                                // Example: You'll need to fetch appointment counts based on therapist IDs
                                                // $appointmentCount = getAppointmentCount($therapist['account_ID']);
                                                // echo htmlspecialchars($appointmentCount);
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php elseif (isset($therapist_error)) : ?>
                                    <tr><td colspan="6"><?= htmlspecialchars($therapist_error); ?></td></tr>
                                <?php else : ?>
                                    <tr><td colspan="6">No therapists found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <script>
                            $(document).ready(function() {
                                $('#managetherapistTable').DataTable({
                                    pageLength: 10,
                                    lengthMenu: [10, 25, 50],
                                    order: [[0, 'asc']],
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
                                    columnDefs: [{ orderable: true, targets: '_all' }]
                                });
                            });
                        </script>
                    </div>
                </div>
            
            </div>

            <!-- Add Therapist -->
            <div id="add-therapist" class="section" style="display: none;">
                <h1 class="uk-text-bold">Add Therapist</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="addTherapistFrame" src="forAdmin/add_therapist.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>


            <!-- System Analytics Section 📑-->
            <div id="system-analytics" class="section" style="display: none;">
                <h1 class="uk-text-bold">System Analytics</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="systemAnalyticsFrame" src="forAdmin/systemAnalytics/system_analytics.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>

            <!-- Manage Timetable Settings Section 📑-->
            <div id="timetable-settings" class="section" style="display: none;">
                <h1 class="uk-text-bold">Manage Timetable Settings</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="manageTimetableSettingsFrame" src="forAdmin/manageWebpage/timetable_settings.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>

            <!-- Manage Website Contents Section 📑-->
            <div id="manage-content" class="section" style="display: none;">
                <h1 class="uk-text-bold">Manage Website Contents</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="manageWebsiteContentsFrame" src="forAdmin/manageWebpage/webpage_content.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>


            <!-- Account Details Section 📑-->
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

            <!-- Settings Section 📑-->
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

                // Archive User
                document.querySelectorAll('.archive-user').forEach(button => {
                    button.addEventListener('click', function() {
                        const accountId = this.dataset.accountId;

                        Swal.fire({
                            title: 'Are you sure?',
                            text: "This user will be archived and unable to access their Account!",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch('../Accounts/manageaccount/archive_account.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: 'account_id=' + accountId
                                }).then(response => {
                                    if (response.ok) {
                                        Swal.fire(
                                            'Archived!',
                                            'The account has been archived.',
                                            'success'
                                        ).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire(
                                            'Error!',
                                            'Failed to archive the account.',
                                            'error'
                                        );
                                    }
                                });
                            }
                        });
                    });
                });

                // Manage Timetable Settings Frame
                let manageTimetableSettingsFrame = document.getElementById("manageTimetableSettingsFrame");

                manageTimetableSettingsFrame.onload = function() {
                    resizeIframe(manageTimetableSettingsFrame);
                    let manageTimetableSettingsForm = manageTimetableSettingsFrame.contentDocument.getElementById("manageTimetableSettingsForm");

                    if (manageTimetableSettingsForm) {
                        manageTimetableSettingsForm.addEventListener("submit", function(e) {
                            e.preventDefault();

                            let formData = new FormData(this);

                            fetch("forAdmin/manageWebpage/timetable_settings.php", {
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

                // View Appointments Frame
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

                // Add Therapist Frame
                let addTherapistFrame = document.getElementById("addTherapistFrame");

                addTherapistFrame.onload = function() {
                    resizeIframe(addTherapistFrame);
                    let addTherapistForm = addTherapistFrame.contentDocument.getElementById("addTherapist"); // Match the form ID

                    if (addTherapistForm) {
                        addTherapistForm.addEventListener("submit", function(e) {
                            e.preventDefault();

                            let formData = new FormData(this);

                            fetch("forAdmin/add_therapist.php", { // Correct path
                                method: "POST",
                                body: formData
                            })
                            .then(response => response.text()) // Expecting HTML response
                            .then(data => {
                                addTherapistFrame.contentDocument.body.innerHTML = data; // Replace iframe content
                                resizeIframe(addTherapistFrame); // Resize after content change
                            })
                            .catch(error => console.error("Error:", error));
                        });
                    }
                };

                window.addEventListener('message', function(event) {
                    if (event.data.type === 'swal') {
                        console.log('Message received:', event.data); // ADD THIS LINE
                        Swal.fire({
                            title: event.data.title,
                            text: event.data.text,
                            icon: event.data.icon
                        });
                    }
                });


                // System Analytics Frame
                let systemAnalyticsFrame = document.getElementById("systemAnalyticsFrame");

                systemAnalyticsFrame.onload = function() {
                    resizeIframe(systemAnalyticsFrame);
                    let systemAnalyticsForm = systemAnalyticsFrame.contentDocument.getElementById("systemAnalyticsForm");

                    if (systemAnalyticsForm) {
                        systemAnalyticsForm.addEventListener("submit", function(e) {
                            e.preventDefault();

                            let formData = new FormData(this);

                            fetch("forAdmin/systemAnalytics/system_analytics.php", {
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

                // Settings
                document.addEventListener("DOMContentLoaded", function() {
                    const editButton = document.getElementById("editButton");
                    const saveButton = document.getElementById("saveButton");
                    const inputs = document.querySelectorAll("#settingsvalidate input:not([type=hidden])");

                    // Store initial values
                    let originalValues = {};
                    inputs.forEach(input => originalValues[input.id] = input.value);

                    editButton.addEventListener("click", function() {
                        const isDisabled = inputs[0].disabled;

                        if (isDisabled) {
                            // Enable inputs for editing
                            inputs.forEach(input => input.disabled = false);
                            saveButton.disabled = false;
                            editButton.textContent = "Cancel";
                        } else {
                            // Reset inputs to original values and disable editing
                            inputs.forEach(input => {
                                input.value = originalValues[input.id];
                                input.disabled = true;
                            });
                            saveButton.disabled = true;
                            editButton.textContent = "Edit";
                        }
                    });
                });

                function removeProfilePhoto() {
                    if (confirm("Are you sure you want to remove your profile picture?")) {
                        fetch("../Accounts/manageaccount/updateinfo.php", {
                                method: "POST",
                                body: JSON.stringify({
                                    action: "remove_profile_picture"
                                }),
                                headers: {
                                    "Content-Type": "application/json"
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.querySelector('.profile-preview').src = '../CSS/default.jpg'; // Set to default image
                                } else {
                                    alert("Error: " + data.error);
                                }
                            })
                            .catch(error => console.error("Error:", error));
                    }
                }


                document.addEventListener("DOMContentLoaded", function() {
                    let profileUploadInput = document.getElementById("profileUpload");
                    let uploadButton = document.getElementById("uploadButton");


                    // Click event to open file dialog
                    uploadButton.addEventListener("click", function() {
                        profileUploadInput.click();
                    });


                    // Auto-upload when a file is selected
                    profileUploadInput.addEventListener("change", function() {
                        let formData = new FormData();
                        formData.append("action", "upload_profile_picture");
                        formData.append("profile_picture", profileUploadInput.files[0]);


                        fetch("../Accounts/manageaccount/updateinfo.php", {
                                method: "POST",
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.querySelector(".profile-preview").src = data.imagePath; // Update profile image
                                } else {
                                    alert("Error: " + data.error);
                                }
                            })
                            .catch(error => console.error("Error:", error));
                    });
                });

                document.addEventListener("DOMContentLoaded", function() {
                    document.getElementById("settingsvalidate").addEventListener("submit", function(event) {
                        event.preventDefault(); // Prevent default form submission

                        let formData = new FormData(this);

                        fetch("../Accounts/manageaccount/updateinfo.php", {
                                method: "POST",
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                // Clear previous error messages
                                document.querySelectorAll(".error-message").forEach(el => el.textContent = "");

                                if (data.errors) {
                                    // Show errors under respective inputs
                                    Object.keys(data.errors).forEach(key => {
                                        let errorElement = document.querySelector(`small[data-error="${key}"]`);
                                        if (errorElement) {
                                            errorElement.textContent = data.errors[key];
                                        }
                                    });
                                } else if (data.success) {
                                    alert(data.success);
                                    location.reload(); // Reload page on success
                                }
                            })
                            .catch(error => console.error("Error:", error));
                    });
                });

                document.querySelector('.sidebar-toggle').addEventListener('click', function() {
                    document.querySelector('.sidebar-nav').classList.toggle('uk-open');
                });

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

                    // Add hash to URL if accounts section is shown
                    if (sectionId === 'accounts') {
                        window.location.hash = 'accounts';
                    } else {
                        // Remove hash if other sections are shown
                        if (window.location.hash) {
                            history.replaceState('', document.title, window.location.pathname + window.location.search);
                        }
                    }
                }

                window.onload = function() {
                    if (window.location.hash) {
                        const hash = window.location.hash.substring(1);
                        showSection(hash);
                    }
                };

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
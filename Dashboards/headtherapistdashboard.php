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
        $profilePicture = '/LIWANAG/uploads/profile_pictures/' . $userData['profile_picture']; // Corrected path
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

// Function to filter appointments by status
function filterAppointmentsByStatus($appointments, $status)
{
    if ($status === 'others') {
        $mainStatuses = ['pending', 'approved', 'waitlisted', 'completed', 'cancelled', 'declined'];
        return array_filter($appointments, function ($appointment) use ($mainStatuses) {
            return !in_array(strtolower($appointment['status']), $mainStatuses);
        });
    } else {
        return array_filter($appointments, function ($appointment) use ($status) {
            return strtolower($appointment['status']) === $status;
        });
    }
}

// ✅ Fetch therapists data for the table
try {
    $stmt = $connection->prepare(" SELECT account_FName, account_LName, account_Email, account_Address, account_PNum, account_status, service_Type, profile_picture
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

// Fetch All Patients with Linked User Information
$patientsStmt = $connection->prepare("SELECT 
        p.patient_id, 
        p.first_name AS patient_firstname, 
        p.last_name AS patient_lastname, 
        p.profile_picture AS patient_picture,
        p.bday, 
        p.gender, 
        p.service_type, 
        p.status,
        u.account_ID AS user_id,
        u.account_FName AS user_firstname,
        u.account_LName AS user_lastname,
        u.account_Email AS user_email,
        u.account_PNum AS user_phone,
        u.profile_picture AS user_picture
    FROM patients p
    LEFT JOIN users u ON p.account_id = u.account_ID
    ORDER BY p.last_name, p.first_name");

$patientsStmt->execute();
$patientsResult = $patientsStmt->get_result();
$patients = $patientsResult->fetch_all(MYSQLI_ASSOC);
$patientsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Head Therapist - Dashboard</title>

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

    <style>
        /* Adjust logo text size for medium screens */
        @media (max-width: 959px) {
            .uk-navbar-item.uk-logo {
                font-size: 20px !important;
            }
        }

        /* Adjust logo text size for small screens */
        @media (max-width: 640px) {
            .uk-navbar-item.uk-logo {
                font-size: 15px !important;
            }
        }

        /* Adjust grid layout for medium screens (tablets) */
        @media (max-width: 959px) {
            .uk-grid-small {
                margin: 0;
            }

            .uk-grid-small>* {
                width: calc(50% - 10px);
                /* 2 columns with gap */
                margin: 5px;
            }

            .uk-card {
                margin-bottom: 10px;
            }

            .uk-card-body button {
                font-size: 13px;
                padding: 8px 12px;
                border-radius: 15px;
            }

            .profile-photo {
                width: 120px;
                height: 120px;
            }
        }

        /* Adjust grid layout for small screens (mobile) */
        @media (max-width: 640px) {
            .uk-grid-small {
                margin: 0;
            }

            .uk-grid-small>* {
                width: calc(50% - 10px);
                margin: 5px;
            }

            .uk-card {
                padding: 12px;
                margin-bottom: 10px;
            }

            .uk-card-body h3 {
                font-size: 18px;
                margin-bottom: 8px;
            }

            .uk-card-body p {
                font-size: 14px;
                margin: 0;
            }

            .uk-card-body button {
                font-size: 12px;
                padding: 0;
                min-height: 30px;
            }

            .uk-card-body {
                padding: 15px;
            }

            .uk-card-body h3.uk-card-title {
                font-size: 16px;
                margin-bottom: 6px;
            }

            .uk-card-body p {
                font-size: 13px;
                margin-bottom: 8px;
            }

            .uk-card-body .uk-button {
                margin-top: 5px;
                border-radius: 15px;
            }

            .appointment-summary-cards {
                padding: 0 0 0 0;
            }

            .profile-photo {
                max-width: 120px;
                max-height: 120px;
            }
        }

        /* Mobile Sidebar Styles */
        .mobile-sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            background-color: #fff;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            transition: left 0.3s ease;
            z-index: 9999;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .mobile-sidebar.open {
            left: 0;
        }

        .mobile-sidebar-header {
            padding: 15px 15px 0 15px;
        }

        .mobile-sidebar-header h4 {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            animation: fadeIn 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        .mobile-menu-button {
            cursor: pointer;
            padding: 10px 10px 10px 0;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .close-sidebar {
            position: absolute;
            right: 10px;
            top: 10px;
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            z-index: 10000;
        }
    </style>
</head>

<body>
    <script>
        console.log('Session Username:', <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>);
    </script>
    <!-- Navbar -->
    <nav class="uk-navbar-container logged-in">
        <div class="uk-container">
            <div uk-navbar>
                <!--Mobile Menu-->
                <div class="uk-navbar-left uk-hidden@m">
                    <a href="#" class="mobile-menu-button" onclick="toggleSidebar(); return false;"">
                    <span uk-icon="menu" style="color: black; display: flex; justify-content: center; align-items: center; width: 30px; height: 30px;"></span>
                    </a>
                </div>

                <div class="uk-navbar-left uk-visible@m">
                    <ul class="uk-navbar-nav">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Services</a></li>
                    </ul>
                </div>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="../homepage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right uk-visible@m">
                    <ul class="uk-navbar-nav">
                        <li>
                            <a href="#" class="uk-navbar-item">
                                <img src="<?php echo $profilePicture . '?t=' . time(); ?>" alt="Profile" class="navbar-profile-pic profile-image">
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;">
                            <?php
                            if (isset($_SESSION['account_ID'])) {

                                $account_ID = $_SESSION['account_ID'];
                                $query = "SELECT account_FName FROM users WHERE account_ID = ?";
                                $stmt = $connection->prepare($query);
                                $stmt->bind_param("i", $account_ID);
                                $stmt->execute();
                                $stmt->bind_result($account_FN);
                                $stmt->fetch();
                                $stmt->close();
                                $connection->close();


                                echo htmlspecialchars($account_FN);
                            } else {
                                echo '<a href="../Accounts/loginpage.php">Login</a>';
                            }
                            ?>
                        </li>
                        <?php if (isset($_SESSION['account_ID'])): ?>
                            <li><a href="../Accounts/logout.php">Logout</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <hr class="solid">

    <!-- Main Content -->
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-height-viewport">
        <!--Sidebar-->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium uk-visible@m">
            <div class="sidebar-nav">
            <ul class="uk-nav uk-nav-default">
                <h4 style="font-weight: bold;">Head Therapist Dashboard</h4>
                <li class="uk-active"><a href="#dashboard" onclick="showSection('dashboard')"><span class="uk-margin-small-right" uk-icon="home"></span> Dashboard</a></li>
                <hr>
                <li class="uk-parent">
                <li>
                    <span>Appointments</span>
                </li>
                <li><a href="#view-appointments" onclick="showSection('view-appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> View All Appointments</a></li>
                <li><a href="#view-manage-appointments" onclick="showSection('view-manage-appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Manage Appointments</a></li>
                <li><a href="#playgroup" onclick="showSection('playgroup')"><span class="uk-margin-small-right" uk-icon="thumbnails"></span> Playgroup Sessions</a></li>
                </li>

                <hr>

                <li class="uk-parent">
                <li>
                    <span>Therapists</span>
                </li>
                <li>
                <li><a href="#view-therapist" onclick="showSection('view-therapist')"><span class="uk-margin-small-right" uk-icon="user"></span> View Therapists</a></li>
                </li>
                <li>
                <li><a href="#therapist-schedule" onclick="showSection('therapist-schedule')"><span class="uk-margin-small-right" uk-icon="calendar"></span>Therapist Schedules</a></li>
                </li>
                </li>

                <hr>

                <li class="uk-parent">
                <li>
                    <span>Patients</span>
                </li>
                <li>
                <li><a href="#view-patients" onclick="showSection('view-patients')"><span class="uk-margin-small-right" uk-icon="user"></span> View Patients</a></li>
                </li>
                </li>

                <hr>

                <li class="uk-parent">

                <li>
                    <span>Settings</span>
                </li>

                <li><a href="#timetable-settings" onclick="showSection('timetable-settings')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Manage Timetable Settings</a></li>
                <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Account Details</a></li>
            </ul>
        </div>
    </div>

    <!-- Mobile Sidebar -->
    <div class="mobile-sidebar uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium" id="mobileSidebar">
        <div class="mobile-sidebar-header">
            <h4>Hi, <span> <?php echo htmlspecialchars($account_FN); ?></span>!</h4>
            <button class="close-sidebar" onclick="toggleSidebar()"><span uk-icon="icon: close-circle;" style="padding: 16px;"></span></button>
        </div>
        <ul class="mobile-sidebar-menu uk-nav uk-nav-default" style="padding: 15px;">
        <li class="uk-active"><a href="#dashboard" onclick="showSection('dashboard')"><span class="uk-margin-small-right" uk-icon="home"></span> Dashboard</a></li>
                <hr>
                <li class="uk-parent">
                <li>
                    <span>Appointments</span>
                </li>
                <li><a href="#view-appointments" onclick="showSection('view-appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> View All Appointments</a></li>
                <li><a href="#view-manage-appointments" onclick="showSection('view-manage-appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Manage Appointments</a></li>
                <li><a href="#playgroup" onclick="showSection('playgroup')"><span class="uk-margin-small-right" uk-icon="thumbnails"></span> Playgroup Sessions</a></li>
                </li>
                <hr>
                <li class="uk-parent">
                <li><span>Therapists</span></li>
                <li>
                <li><a href="#view-therapist" onclick="showSection('view-therapist')"><span class="uk-margin-small-right" uk-icon="user"></span> View Therapists</a></li>
                </li>
                <li>
                <li><a href="#therapist-schedule" onclick="showSection('therapist-schedule')"><span class="uk-margin-small-right" uk-icon="calendar"></span>Therapist Schedules</a></li>
                </li>
                </li>
                <hr>
                <li class="uk-parent">
                <li><span>Patients</span></li>
                <li>
                <li><a href="#view-patients" onclick="showSection('view-patients')"><span class="uk-margin-small-right" uk-icon="user"></span> View Patients</a></li>
                </li>
                </li>
                <hr>
                <li class="uk-parent">
                <li><span>Settings</span></li>
                <li><a href="#timetable-settings" onclick="showSection('timetable-settings')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Manage Timetable Settings</a></li>
                <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Account Details</a></li>
                <?php if (isset($_SESSION['account_ID'])): ?><li><a href="../Accounts/logout.php"><span class="uk-margin-small-right" uk-icon="sign-out"></span>Logout</a></li><?php endif; ?>            
            </ul>
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

            <!-- ✅ Clickable Appointment Summary Cards -->
            <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                <?php foreach ($appointmentCounts as $status => $count): ?>
                    <div class="appointment-summary-cards">
                        <div class="uk-card uk-card-default uk-card-body uk-card-hover" id="card-<?= strtolower($status) ?>">
                            <h3 class="uk-card-title"><?= ucwords($status) ?></h3>
                            <p>Total: <?= $count ?></p>
                            <button class="uk-button uk-button-primary uk-width-1-1"
                                uk-toggle="target: #modal-<?= strtolower($status) ?>">
                                View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Modal popups for each status with wider width and pagination -->
            <?php foreach ($appointmentCounts as $status => $count): ?>
                <!-- This creates a modal for each appointment status -->
                <div id="modal-<?= strtolower($status) ?>" class="uk-modal-container" uk-modal>
                    <div class="uk-modal-dialog uk-modal-body">
                        <button class="uk-modal-close-default" type="button" uk-close></button>
                        <h2 class="uk-modal-title"><?= ucwords($status) ?> Appointments</h2>
                        <p class="uk-text-meta">Total: <?= $count ?> appointments</p>

                        <div class="uk-overflow-auto">
                            <table id="table-<?= strtolower($status) ?>" class="uk-table uk-table-striped uk-table-hover uk-table-responsive">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient</th>
                                        <th>Client</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Filter appointments for current status
                                    $statusAppointments = filterAppointmentsByStatus($appointments, $status);

                                    if (!empty($statusAppointments)):
                                        foreach ($statusAppointments as $appointment):
                                    ?>
                                            <tr>
                                                <td><?= $appointment['appointment_id'] ?></td>
                                                <td><?= htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']) ?></td>
                                                <td><?= htmlspecialchars($appointment['client_firstname'] . ' ' . $appointment['client_lastname']) ?></td>
                                                <td><?= date('M d, Y', strtotime($appointment['date'])) ?></td>
                                                <td><?= date('h:i A', strtotime($appointment['time'])) ?></td>
                                                <td>
                                                    <span class="uk-label uk-label-<?= getStatusClass($appointment['status']) ?>">
                                                        <?= ucfirst($appointment['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php
                                        endforeach;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="7" class="uk-text-center">No <?= strtolower($status) ?> appointments found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="uk-modal-footer uk-text-right">
                            <button class="uk-button uk-button-default uk-modal-close" type="button">Close</button>
                        </div>
                    </div>
                </div>

                <!-- Initialize DataTables for each status table -->
                <script>
                    $(document).ready(function() {
                        $('#table-<?= strtolower($status) ?>').DataTable({
                            pageLength: 10,
                            lengthMenu: [10, 25, 50],
                            order: [
                                [3, 'asc']
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
                            }
                        });
                    });
                </script>
            <?php endforeach; ?>

            <?php
            // Helper function to determine the UIkit label class based on status
            function getStatusClass($status)
            {
                $status = strtolower($status);
                switch ($status) {
                    case 'pending':
                        return 'warning';
                    case 'approved':
                        return 'success';
                    case 'waitlisted':
                        return 'primary';
                    case 'completed':
                        return 'success';
                    case 'cancelled':
                        return 'danger';
                    case 'declined':
                        return 'danger';
                    default:
                        return 'default';
                }
            }
            ?>

            <hr>
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

        <!-- Playgroup Sessions Section 📑-->
        <div id="playgroup" class="section" style="display: none;">
            <h1 class="uk-text-bold">Playgroup Sessions</h1>
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <iframe id="playgroupDashboard" src="../Appointments/app_manage/playgroup_dashboard.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
            </div>
        </div>

        <div id="view-therapist" class="section" style="display: none;">
            <h1 class="uk-text-bold">View Therapists</h1>

            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <div class="uk-overflow-auto">
                    <table id="viewtherapistTable" class="uk-table uk-table-striped uk-table-hover uk-table-responsive uk-table-middle">
                        <thead>
                            <tr>
                                <th><span class="no-break">Therapist Name<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                                <th><span class="no-break">Email<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                                <th><span class="no-break">Phone</span></th>
                                <th><span class="no-break">Address</span></th>
                                <th><span class="no-break">Service<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                                <th><span class="no-break">Status<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($therapists) && !empty($therapists)) : ?>
                                <?php foreach ($therapists as $therapist) : ?>
                                    <?php
                                    // Set the correct path for profile picture with fallback
                                    $profilePicturePath = !empty($therapist['profile_picture'])
                                        ? "/LIWANAG/uploads/profile_pictures/" . $therapist['profile_picture']
                                        : '/LIWANAG/CSS/default.jpg';

                                    // Set the service type with fallback and capitalize first letter
                                    $service_Type = !empty($therapist['service_Type'])
                                        ? ucfirst(htmlspecialchars($therapist['service_Type']))
                                        : 'Not Set';

                                    // Prepare other variables
                                    $therapistFullName = htmlspecialchars($therapist['account_FName'] . ' ' . $therapist['account_LName']);
                                    $email = htmlspecialchars($therapist['account_Email']);
                                    $phone = htmlspecialchars($therapist['account_PNum']);
                                    $address = htmlspecialchars($therapist['account_Address']);
                                    $status = htmlspecialchars($therapist['account_status']);
                                    $statusClass = ($status === 'Active') ? 'success' : 'warning';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="uk-flex uk-flex-column">
                                                <img src="<?= htmlspecialchars($profilePicturePath); ?>"
                                                    alt="Pic" class="uk-border-circle uk-align-center" style="width: 60px; height: 60px; object-fit: cover; margin-bottom: 8px;">
                                                <span><?= $therapistFullName; ?></span>
                                            </div>
                                        </td>
                                        <td><?= $email; ?></td>
                                        <td>0<?= $phone; ?></td>
                                        <td><?= $address; ?></td>
                                        <td><?= $service_Type; ?></td>
                                        <td><span class="uk-label uk-label-<?= $statusClass; ?>"><?= $status; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif (isset($therapist_error)) : ?>
                                <tr>
                                    <td colspan="6"><?= htmlspecialchars($therapist_error); ?></td>
                                </tr>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6">No therapists found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <script>
                        $(document).ready(function() {
                            // Initialize DataTable
                            $('#viewtherapistTable').DataTable({
                                pageLength: 10,
                                lengthMenu: [10, 25, 50],
                                order: [
                                    [0, 'asc'] // Default sort: Order by the first column (Therapist Name) ascending
                                ],
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
                                columnDefs: [
                                    // Column Index | Property | Value | Comment
                                    //--------------------------------------------------------------
                                    {
                                        orderable: true,
                                        targets: 0
                                    }, // Therapist (Name+Pic) - Sortable
                                    {
                                        orderable: true,
                                        targets: 1
                                    }, // Email - Sortable
                                    {
                                        orderable: false,
                                        targets: 2
                                    }, // Phone - Not Sortable
                                    {
                                        orderable: false,
                                        targets: 3
                                    }, // Address - Not Sortable
                                    {
                                        orderable: true,
                                        targets: 4
                                    }, // Service - Sortable
                                    {
                                        orderable: true,
                                        targets: 5
                                    } // Status - Sortable
                                ]
                            });
                        });
                    </script>

                    <style>
                        /* Custom styles for better appearance */
                        #viewtherapistTable th {
                            padding: 12px 8px;
                            /* Adjust padding */
                            background-color: #f8f8f8;
                            /* Light background for headers */
                            font-weight: 600;
                            /* Slightly bolder headers */
                            color: #555;
                            vertical-align: middle;
                            /* Ensure vertical alignment */
                        }

                        #viewtherapistTable td {
                            padding: 10px 8px;
                            /* Adjust padding */
                            vertical-align: middle;
                            /* Ensure vertical alignment */
                        }

                        /* Ensure profile images display correctly */
                        .uk-border-circle {
                            border: 1px solid #eaeaea;
                            background-color: #fff;
                            /* White background behind image if transparent */
                        }

                        /* Subtle hover effect for table rows */
                        #viewtherapistTable tbody tr:hover {
                            background-color: #f0f8ff;
                            /* Light blue hover, adjust as needed */
                        }

                        /* Vertical alignment for icons (like sort arrows) */
                        th>span[uk-icon] {
                            vertical-align: middle;
                            margin-left: 4px;
                            /* Space between text and icon */
                        }

                        /* Ensure status label aligns nicely */
                        #viewtherapistTable td .uk-label {
                            vertical-align: middle;
                        }

                        /* Prevent line breaks in specific columns if needed */
                        #viewtherapistTable td:nth-child(2),
                        /* Email */
                        #viewtherapistTable td:nth-child(3)

                        /* Phone */
                            {
                            white-space: nowrap;
                        }
                    </style>
                </div>
            </div>
        </div>



        <!-- View Therapists Schedule Section 📑-->
        <div id="therapist-schedule" class="section" style="display: none;">
            <h1 class="uk-text-bold">Therapist Schedules</h1>
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <iframe id="therapistScheduleFrame" src="forAdmin/schedule_head_therapist.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
            </div>
        </div>

        <!-- Patients Section -->
        <div id="view-patients" class="section" style="display: none;">
            <h1 class="uk-text-bold">Patients Masterlist</h1>
            <div class="uk-card uk-card-default uk-card-body uk-margin uk-width-1-1">
                <div class="uk-overflow-auto">
                    <table id="patientMasterTable" class="uk-table uk-table-striped uk-table-hover uk-table-responsive">
                        <thead>
                            <tr>
                                <th><span class="no-break">Patient Details<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                                <th><span class="no-break">Client Details<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                                <th><span class="no-break">Birthday<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                                <th><span class="no-break">Service Type<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                                <th><span class="no-break">Status<span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <!-- Patient Column -->
                                    <td>
                                        <div class="uk-flex uk-flex-column">
                                            <img src="<?= !empty($patient['patient_picture']) ? '../uploads/profile_pictures/' . $patient['patient_picture'] : '../CSS/default.jpg'; ?>"
                                                alt="Patient Picture" class="uk-border-circle uk-align-center" style="width: 60px; height: 60px; object-fit: cover; margin-bottom: 8px;">
                                            <div class="uk-text-center">
                                                <div class="uk-text-bold"><?= htmlspecialchars($patient['patient_firstname'] . ' ' . $patient['patient_lastname']) ?></div>
                                                <div class="uk-text-meta">
                                                    <?= !empty($patient['gender']) ? htmlspecialchars($patient['gender']) : 'Gender not specified' ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Linked User Column -->
                                    <td>
                                        <?php if (!empty($patient['user_id'])): ?>
                                            <div class="uk-flex uk-flex-column">
                                                <img src="<?= !empty($patient['user_picture']) ? '../uploads/profile_pictures/' . $patient['user_picture'] : '../CSS/default.jpg'; ?>"
                                                    alt="User Picture" class="uk-border-circle uk-align-center" style="width: 60px; height: 60px; object-fit: cover; margin-bottom: 8px;">
                                                <div class="uk-text-center">
                                                    <div class="uk-text-bold"><?= htmlspecialchars($patient['user_firstname'] . ' ' . $patient['user_lastname']) ?></div>
                                                    <div class="uk-text-meta">
                                                        <?= htmlspecialchars($patient['user_email']) ?><br>
                                                        <?= !empty($patient['user_phone']) ? htmlspecialchars($patient['user_phone']) : 'Phone not provided' ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="uk-text-center uk-text-meta">No Linked Client</div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Birthday Column -->
                                    <td>
                                        <?= !empty($patient['bday']) ? htmlspecialchars(date('M d, Y', strtotime($patient['bday']))) : 'Not specified' ?>
                                    </td>

                                    <!-- Service Type Column -->
                                    <td><?= htmlspecialchars($patient['service_type']) ?></td>

                                    <!-- Status Column -->
                                    <td>
                                        <span class="uk-label 
                                    <?= $patient['status'] == 'enrolled' ? 'uk-label-success' : ($patient['status'] == 'pending' ? 'uk-label-warning' : 'uk-label-danger') ?>">
                                            <?= htmlspecialchars(ucfirst($patient['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Initialize DataTable for Patients Masterlist
                $('#patientMasterTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [10, 25, 50],
                    order: [
                        [0, 'asc'] // Default sort by Patient Name ascending
                    ],
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
                            targets: 0
                        }, // Patient Details - Sortable
                        {
                            orderable: true,
                            targets: 1
                        }, // Client Details - Sortable
                        {
                            orderable: true,
                            targets: 2
                        }, // Birthday - Sortable
                        {
                            orderable: true,
                            targets: 3
                        }, // Service Type - Sortable
                        {
                            orderable: true,
                            targets: 4
                        } // Status - Sortable
                    ],
                    responsive: true
                });
            });
        </script>

        <!-- Manage Timetable Settings Section 📑-->
        <div id="timetable-settings" class="section" style="display: none;">
            <h1 class="uk-text-bold">Manage Timetable Settings</h1>
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <iframe id="manageTimetableSettingsFrame" src="forAdmin/manageWebpage/timetable_settings.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
            </div>
        </div>

        <!-- Account Details Card -->
        <div id="account-details" class="section" style="display: none;">
            <h1 class="uk-text-bold">Account Details</h1>
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                <form action="settings.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_profile_picture">
                    <div class="uk-flex uk-flex-middle">
                        <div class="profile-upload-container">
                            <img class="uk-border-circle profile-preview" src="<?php echo $profilePicture; ?>" alt="Profile Photo">
                            <div class="uk-flex uk-flex-column uk-margin-left">
                                <input type="file" name="profile_picture" id="profileUpload" class="uk-hidden">
                                <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" id="uploadButton" style="border-radius: 15px;" disabled>
                                    Upload Photo
                                </button>
                                <div class="uk-text-center">
                                    <a href="#" class="uk-link-muted" onclick="removeProfilePhoto();" id="removePhotoButton" style="pointer-events: none; color: grey;">remove</a>
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
                    <input type="hidden" name="action" id="formAction" value="update_user_details">

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
                        <input class="uk-input" type="tel" name="phoneNumber" id="mobileNumber" value="<?php echo '0' . $phoneNumber; ?>" disabled>
                        <small style="color: red;" class="error-message" data-error="phoneNumber"></small>
                    </div>

                    <small style="color: red;" class="error-message" data-error="duplicate"></small>
                    <small style="color: green;" class="error-message" id="successMessage"></small>

                    <div class="uk-width-1-1 uk-text-right uk-margin-top">
                        <button type="button" class="uk-button uk-button-secondary" id="editButton">Edit</button>
                        <button class="uk-button uk-button-primary" uk-toggle="target: #change-password-modal">Change Password</button>
                        <button class="uk-button uk-button-primary" type="submit" id="saveButton" disabled>Save Changes</button>
                    </div>

                    <div id="otpSection" class="uk-width-1-1" style="display: none;">
                        <h3 class="uk-card-title uk-text-bold">Enter OTP</h3>
                        <p class="uk-text-muted">A verification code has been sent to your new email address. Please enter it below to complete the change.</p>
                        <div class="uk-margin">
                            <input class="uk-input" type="text" name="otp" id="otp" placeholder="Enter OTP">
                            <small style="color: red;" class="error-message" data-error="otp"></small>
                        </div>
                        <!-- The buttons will be dynamically added here by JavaScript -->
                    </div>
            </div>
        </div>
    </div>
    </form>
    <?php unset($_SESSION['update_errors']); ?>
    <?php unset($_SESSION['update_success']); ?>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const editButton = document.getElementById("editButton");
            const saveButton = document.getElementById("saveButton");
            const form = document.getElementById("settingsvalidate");
            const inputs = document.querySelectorAll("#settingsvalidate input:not([type=hidden])");
            const otpSection = document.getElementById("otpSection");
            const otpInput = document.getElementById("otp");
            const successMessage = document.getElementById("successMessage");
            const profileUploadInput = document.getElementById("profileUpload");
            const uploadButton = document.getElementById("uploadButton");
            const emailInput = document.getElementById("email"); // Select the email input
            const removePhotoButton = document.getElementById("removePhotoButton");

            // Create all three buttons with the exact styling from Image 2
            const resendOtpButton = document.createElement("button");
            resendOtpButton.id = "resendOtpButton";
            resendOtpButton.textContent = "RESEND OTP";
            resendOtpButton.className = "uk-button";
            resendOtpButton.style.backgroundColor = "#1e88e5"; // Bright blue
            resendOtpButton.style.color = "white";
            resendOtpButton.style.fontWeight = "bold";
            resendOtpButton.style.padding = "8px 20px";
            resendOtpButton.style.margin = "0 10px 0 0";
            resendOtpButton.style.border = "none";
            resendOtpButton.style.borderRadius = "4px";
            resendOtpButton.style.textTransform = "uppercase";
            resendOtpButton.style.transition = ".1s ease-in-out";
            resendOtpButton.style.transitionProperty = "color, background-color, border-color";

            const editEmailButton = document.createElement("button");
            editEmailButton.id = "editEmailButton";
            editEmailButton.textContent = "EDIT EMAIL";
            editEmailButton.className = "uk-button";
            editEmailButton.style.backgroundColor = "#212121"; // Dark gray/black
            editEmailButton.style.color = "white";
            editEmailButton.style.fontWeight = "bold";
            editEmailButton.style.padding = "8px 20px";
            editEmailButton.style.margin = "0 10px 0 0";
            editEmailButton.style.border = "none";
            editEmailButton.style.borderRadius = "4px";

            const cancelVerificationButton = document.createElement("button");
            cancelVerificationButton.id = "cancelVerificationButton";
            cancelVerificationButton.textContent = "CANCEL VERIFICATION";
            cancelVerificationButton.className = "uk-button";
            cancelVerificationButton.style.backgroundColor = "#e91e63"; // Pink
            cancelVerificationButton.style.color = "white";
            cancelVerificationButton.style.fontWeight = "bold";
            cancelVerificationButton.style.padding = "8px 20px";
            cancelVerificationButton.style.margin = "0";
            cancelVerificationButton.style.border = "none";
            cancelVerificationButton.style.borderRadius = "4px";

            // Create a container for the buttons
            const buttonContainer = document.createElement("div");
            buttonContainer.className = "uk-margin-medium-top";
            buttonContainer.style.display = "flex";
            buttonContainer.style.justifyContent = "flex-start";
            buttonContainer.style.marginTop = "20px";

            // Add buttons to container in the correct order
            buttonContainer.appendChild(resendOtpButton);
            buttonContainer.appendChild(editEmailButton);
            buttonContainer.appendChild(cancelVerificationButton);
            // Insert these buttons after the OTP input
            if (otpSection) {
                // Append the button container to the OTP section (after all existing elements)
                otpSection.appendChild(buttonContainer);
            }

            // Store initial values
            let originalValues = {};
            inputs.forEach(input => originalValues[input.id] = input.value);
            // Original email value to restore if verification is canceled
            let originalEmail = emailInput ? emailInput.value : '';

            // Modify Edit Button Click Event
            if (editButton) {
                editButton.addEventListener("click", function() {
                    if (editButton.textContent === "Edit") {
                        inputs.forEach(input => input.disabled = false);
                        saveButton.disabled = false;
                        editButton.textContent = "Cancel";
                        uploadButton.disabled = false;
                        removePhotoButton.style.pointerEvents = "auto";
                        removePhotoButton.style.color = "";

                        // Enable Change Password button
                        if (changePasswordButton) {
                            changePasswordButton.disabled = false;
                        }

                        // Enable password form inputs
                        if (passwordForm) {
                            const passwordInputs = passwordForm.querySelectorAll("input");
                            passwordInputs.forEach(input => input.disabled = false);
                        }
                    } else {
                        inputs.forEach(input => {
                            input.value = originalValues[input.id];
                            input.disabled = true;
                        });
                        saveButton.disabled = true;
                        editButton.textContent = "Edit";
                        otpSection.style.display = "none";
                        uploadButton.disabled = true;
                        removePhotoButton.style.pointerEvents = "none";
                        removePhotoButton.style.color = "grey";

                        saveButton.textContent = "Save Changes";
                        saveButton.dataset.step = "";

                        // Disable Change Password button
                        if (changePasswordButton) {
                            changePasswordButton.disabled = true;
                        }

                        // Disable password form inputs
                        if (passwordForm) {
                            const passwordInputs = passwordForm.querySelectorAll("input");
                            passwordInputs.forEach(input => input.disabled = true);
                        }
                    }
                });
            }

            // Save Button Click Event
            if (saveButton) {
                saveButton.addEventListener("click", function(event) {
                    if (saveButton.dataset.step === "verify") {
                        event.preventDefault();
                        verifyOTP();
                    } else {
                        event.preventDefault();
                        saveChanges();
                    }
                });
            }

            // Edit Email Button Click Event - Allows user to go back and edit their email
            if (editEmailButton) {
                editEmailButton.addEventListener("click", function(event) {
                    event.preventDefault();

                    // Hide OTP section
                    otpSection.style.display = "none";

                    // Enable email input
                    emailInput.disabled = false;

                    // Reset save button state
                    saveButton.textContent = "Save";
                    saveButton.dataset.step = "";
                });
            }

            // Add event listener for Resend OTP button
            if (resendOtpButton) {
                resendOtpButton.addEventListener("click", function(event) {
                    event.preventDefault();
                    resendOtpButton.disabled = true;

                    // Make the button transparent
                    resendOtpButton.style.opacity = "0.4"; // Higher transparency (lower opacity)

                    // Add a countdown timer to prevent spam
                    let timeLeft = 60;
                    const originalText = resendOtpButton.textContent;
                    resendOtpButton.textContent = `WAIT (${timeLeft}s)`;

                    const countdownTimer = setInterval(() => {
                        timeLeft--;
                        resendOtpButton.textContent = `WAIT (${timeLeft}s)`;

                        if (timeLeft <= 0) {
                            clearInterval(countdownTimer);
                            resendOtpButton.textContent = originalText;
                            resendOtpButton.disabled = false;
                            resendOtpButton.style.opacity = "1"; // Restore full opacity
                        }
                    }, 1000);

                    // Send request to resend OTP
                    const email = document.getElementById("email").value.trim();

                    let formData = new URLSearchParams({
                        action: "resend_otp",
                        email: email
                    });

                    fetch("../Accounts/manageaccount/updateinfo.php", {
                            method: "POST",
                            body: formData,
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'OTP Resent',
                                    text: 'A new verification code has been sent to your email.',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                });
                                // Keep the button disabled and transparent during the countdown
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.error || 'Failed to resend OTP. Please try again.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });

                                // Reset the button immediately on error
                                clearInterval(countdownTimer);
                                resendOtpButton.textContent = originalText;
                                resendOtpButton.disabled = false;
                                resendOtpButton.style.opacity = "1"; // Restore full opacity
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred. Please try again.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });

                            // Reset the button immediately on error
                            clearInterval(countdownTimer);
                            resendOtpButton.textContent = originalText;
                            resendOtpButton.disabled = false;
                            resendOtpButton.style.opacity = "1"; // Restore full opacity
                        });
                });
            }

            // Cancel Verification Button Click Event - Cancels email verification and restores original email
            if (cancelVerificationButton) {
                cancelVerificationButton.addEventListener("click", function(event) {
                    event.preventDefault();

                    // Confirm cancellation with SweetAlert
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "Your email will not be changed.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, cancel verification'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Restore original email
                            emailInput.value = originalEmail;

                            // Hide OTP section
                            otpSection.style.display = "none";

                            // Reset save button state
                            saveButton.textContent = "Save";
                            saveButton.dataset.step = "";

                            // Disable inputs if we're not in edit mode
                            if (editButton.textContent === "Edit") {
                                inputs.forEach(input => input.disabled = true);
                            }

                            Swal.fire(
                                'Cancelled',
                                'Email verification has been cancelled.',
                                'info'
                            );
                        }
                    });
                });
            }

            // Function to Save Changes
            function saveChanges() {
                let firstName = document.getElementById("firstName").value.trim();
                let lastName = document.getElementById("lastName").value.trim();
                let email = emailInput.value.trim(); // Use the selected email input
                let phoneNumber = document.getElementById("mobileNumber").value.trim();

                document.querySelectorAll(".error-message").forEach(error => error.textContent = "");

                if (!firstName || !lastName || !email || !phoneNumber) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'All fields are required.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Store the original email in case user cancels verification later
                originalEmail = emailInput.value;

                let formData = new URLSearchParams({
                    action: "update_user_details",
                    firstName: firstName,
                    lastName: lastName,
                    email: email,
                    phoneNumber: phoneNumber
                });

                fetch("../Accounts/manageaccount/updateinfo.php", {
                        method: "POST",
                        body: formData,
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.errors) {
                            Object.entries(data.errors).forEach(([key, message]) => {
                                let errorElement = document.querySelector(`[data-error="${key}"]`);
                                if (errorElement) errorElement.textContent = message;
                            });

                            Swal.fire({
                                title: 'Validation Error',
                                text: 'Please check the form for errors.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        } else if (data.otp_required) {
                            Swal.fire({
                                title: 'OTP Sent',
                                text: 'OTP sent to your new email. Please enter the OTP to verify.',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });

                            otpSection.style.display = "block";
                            saveButton.textContent = "Verify OTP";
                            saveButton.dataset.step = "verify";
                        } else if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: data.success,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: 'Something went wrong.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            }
            // Function to Verify OTP
            function verifyOTP() {
                let otp = otpInput.value.trim();
                if (!otp) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Please enter OTP.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Get the user details to update along with the OTP
                let firstName = document.getElementById("firstName").value.trim();
                let lastName = document.getElementById("lastName").value.trim();
                let phoneNumber = document.getElementById("mobileNumber").value.trim();

                // Create form data with all necessary information
                let formData = new URLSearchParams({
                    action: "verify_otp",
                    otp: otp,
                    firstName: firstName,
                    lastName: lastName,
                    phoneNumber: phoneNumber
                });

                fetch("../Accounts/manageaccount/updateinfo.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Email updated successfully!',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else if (data.error) {
                            Swal.fire({
                                title: 'Error',
                                text: data.error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                title: 'Invalid OTP',
                                text: 'Invalid OTP. Please try again.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred during OTP verification.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            }

            // Profile Picture Upload Handling
            if (uploadButton && profileUploadInput) {
                uploadButton.addEventListener("click", function() {
                    profileUploadInput.click();
                });

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
                                document.querySelector(".profile-preview").src = data.imagePath;
                            } else {
                                alert("Error: " + data.error);
                            }
                        })
                        .catch(error => console.error("Error:", error));
                });
            }

            // Form Submission Event (For Validation)
            if (form) {
                form.addEventListener("submit", function(event) {
                    event.preventDefault();

                    let formData = new FormData(this);

                    fetch("../Accounts/manageaccount/updateinfo.php", {
                            method: "POST",
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            document.querySelectorAll(".error-message").forEach(el => el.textContent = "");

                            if (data.errors) {
                                Object.keys(data.errors).forEach(key => {
                                    let errorElement = document.querySelector(`small[data-error="${key}"]`);
                                    if (errorElement) {
                                        errorElement.textContent = data.errors[key];
                                    }
                                });
                            } else if (data.success) {
                                alert(data.success);
                                location.reload();
                            }
                        })
                        .catch(error => console.error("Error:", error));
                });
            }

            // Change Password Button
            const changePasswordButton = document.querySelector('[uk-toggle="target: #change-password-modal"]');

            if (changePasswordButton) {
                // Initially disable the Change Password button
                changePasswordButton.disabled = true;
            }

            // Change Password
            const passwordForm = document.getElementById("change-password-form");

            // Prevent default form submission and save actions
            changePasswordButton.addEventListener('click', function(event) {
                // Prevent any default behavior that might submit a form or trigger save actions
                event.preventDefault();

                // Create a function to show the password change form
                function showPasswordChangeForm(errorMessage = null) {
                    Swal.fire({
                        title: 'Change Password',
                        html: `
                <form id="swal-password-form" class="swal-form">
                    <div class="swal-input-group">
                        <label for="swal-current-password">Current Password</label>
                        <input type="password" id="swal-current-password" class="swal2-input" placeholder="Current password">
                    </div>
                    
                    <div class="swal-input-group">
                        <label for="swal-new-password">New Password</label>
                        <input type="password" id="swal-new-password" class="swal2-input" placeholder="New password">
                    </div>
                    
                    <div class="swal-input-group">
                        <label for="swal-confirm-password">Confirm New Password</label>
                        <input type="password" id="swal-confirm-password" class="swal2-input" placeholder="Confirm new password">
                    </div>
                </form>
            `,
                        showCancelButton: true,
                        confirmButtonText: 'Change Password',
                        cancelButtonText: 'Cancel',
                        focusConfirm: false,
                        showLoaderOnConfirm: true,
                        didOpen: () => {
                            // If there's an error message, show it as a validation message
                            if (errorMessage) {
                                Swal.showValidationMessage(errorMessage);
                            }
                        },
                        preConfirm: () => {
                            const currentPassword = document.getElementById('swal-current-password').value.trim();
                            const newPassword = document.getElementById('swal-new-password').value.trim();
                            const confirmPassword = document.getElementById('swal-confirm-password').value.trim();

                            // Frontend validation
                            if (!currentPassword || !newPassword || !confirmPassword) {
                                Swal.showValidationMessage('All fields are required');
                                return false;
                            }

                            if (newPassword !== confirmPassword) {
                                Swal.showValidationMessage('New passwords do not match');
                                return false;
                            }

                            if (newPassword.length < 8) {
                                Swal.showValidationMessage('Password must be at least 8 characters');
                                return false;
                            }

                            if (!/[A-Z]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one uppercase letter');
                                return false;
                            }

                            if (!/[a-z]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one lowercase letter');
                                return false;
                            }

                            if (!/[0-9]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one number');
                                return false;
                            }

                            if (!/[^A-Za-z0-9]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one special character');
                                return false;
                            }

                            // Return values for the next step
                            return {
                                currentPassword: currentPassword,
                                newPassword: newPassword,
                                confirmPassword: confirmPassword
                            };
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        // If user clicked "Change Password" and validation passed
                        if (result.isConfirmed) {
                            const {
                                currentPassword,
                                newPassword,
                                confirmPassword
                            } = result.value;

                            // Send password change request
                            fetch("../Accounts/manageaccount/updateinfo.php", {
                                    method: "POST",
                                    headers: {
                                        "Content-Type": "application/x-www-form-urlencoded"
                                    },
                                    body: new URLSearchParams({
                                        action: "change_password",
                                        current_password: currentPassword,
                                        new_password: newPassword,
                                        confirm_password: confirmPassword
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    console.log("Response received:", data);
                                    if (data.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Success!',
                                            text: data.success,
                                            timer: 2000,
                                            timerProgressBar: true
                                        });
                                    } else if (data.error) {
                                        // Reopen the form with the error message but no values preserved
                                        showPasswordChangeForm(data.error);
                                    }
                                })
                                .catch(error => {
                                    console.error("Error:", error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'An unexpected error occurred. Please try again.'
                                    });
                                });
                        } else {
                            // User clicked cancel or outside the modal, reset the page (reload)
                            location.reload();
                        }
                    });
                }

                // Show the initial password change form
                showPasswordChangeForm();
            });

            // Add some CSS to improve the SweetAlert form
            const style = document.createElement('style');
            style.textContent = `
.swal-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin: 15px auto;
}
.swal-input-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
}
.swal-input-group label {
    margin-bottom: 5px;
    font-weight: 500;
    text-align: left;
}
.swal2-input {
    width: 100%;
    margin: 0;
}
.swal-validation-error {
    color: #f27474;
    margin-top: 10px;
    text-align: left;
    font-size: 14px;
}
`;
            document.head.appendChild(style);
            // Initialize OTP section to be hidden
            otpSection.style.display = "none";
        });

        // Resize iframe
        function resizeIframe(iframe) {
            iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 'px';
        }

        // Mobile Sidebar Toggle
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.querySelector('.mobile-menu-button');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const body = document.body;

        function toggleSidebar() {
            if (mobileSidebar.classList.contains('open')) {
                // Close sidebar
                mobileSidebar.classList.remove('open');
                mobileSidebar.style.left = '-100%';

                // Remove overlay
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    overlay.remove();
                }

                // Enable scrolling
                body.style.overflow = '';
            } else {
                // Open sidebar
                mobileSidebar.classList.add('open');
                mobileSidebar.style.left = '0';

                // Add overlay
                const overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                body.appendChild(overlay);

                // Disable scrolling
                body.style.overflow = 'hidden';

                // Add click event to overlay to close sidebar
                overlay.addEventListener('click', toggleSidebar);
            }
        }

        // Attach click event to the mobile menu button
        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSidebar();
            });
        }

        // Close sidebar when clicking the close button
        const closeButton = document.querySelector('.close-sidebar');
        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSidebar();
            });
        }
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

        // view playgroup iframe
        let playgroupFrame = document.getElementById("playgroupDashboard");

        playgroupFrame.onload = function() {
            resizeIframe(viewAppointmentsFrame);
            let playgroupForm = playgroupFrame.contentDocument.getElementById("playgroupForm");

            if (playgroupForm) {
                playgroupForm.addEventListener("submit", function(e) {
                    e.preventDefault();

                    let formData = new FormData(this);

                    fetch("../Appointments/app_manage/playgroup_dashboard.php", {
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

        // therapist schedule frame
        let therapistSchedFrame = document.getElementById("therapistScheduleFrame");

        therapistSchedFrame.onload = function() {
            resizeIframe(therapistSchedFrame);
            let therapistSchedForm = therapistSchedFrame.contentDocument.getElementById("therapistForm");

            if (therapistSchedForm) {
                therapistSchedForm.addEventListener("submit", function(e) {
                    e.preventDefault();

                    let formData = new FormData(this);

                    fetch("forAdmin/schedule_head_therapist.php", {
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
                                        window.location.reload(true);
                                    }
                                });
                            }
                        })
                        .catch(error => console.error("Error:", error));
                });
            }
        };
    </script>
</body>

</html>
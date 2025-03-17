<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins & Head Therapists Only
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../../loginpage.php");
    exit();
}

// Fetch pending appointment count
$pendingCountQuery = "SELECT COUNT(*) as count FROM appointments WHERE status = 'Pending'";
$result = $connection->query($pendingCountQuery);
$pendingCount = $result->fetch_assoc()['count'];

// Fetch total appointment count
$totalCountQuery = "SELECT COUNT(*) as count FROM appointments";
$result = $connection->query($totalCountQuery);
$totalCount = $result->fetch_assoc()['count'];

// Fetch upcoming appointments
$upcomingQuery = "SELECT COUNT(*) as count FROM appointments WHERE date >= CURDATE()";
$result = $connection->query($upcomingQuery);
$upcomingCount = $result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css" />
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
                    <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li>
                            <a href="#" class="uk-navbar-item">
                                <img class="profile-image" src="../../CSS/default.jpg" alt="Profile Image" uk-img>
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;"> <?php echo $_SESSION['username']; ?>
                        </li>
                        <li><a href="../../Accounts/logout.php">Logout</a></li>
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
                    <li><a href="../../Dashboards/headtherapistdashboard.php">Dashboard</a></li>
                    <li class="uk-active"><a href="manage_appointments.php">View & Manage Appointments</a></li>
                    <li><a href="view_all_appointments.php">View All Appointments</a></li>
                    <li><a href="../../Dashboards/forAdmin/add_therapist.php">Manage Therapists (Adding Only)</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <!-- Dashboard Section -->
            <div id="dashboard" class="section">
                <h1 class="uk-text-bold">Appointment Management Dashboard</h1>
            </div>

            <!-- ✅ Quick Statistics -->
            <div class="uk-grid-small uk-child-width-1-3@s uk-text-center" uk-grid>
                <div>
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3><?= $totalCount; ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
                <div>
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3><?= $pendingCount; ?></h3>
                        <p>Pending Appointments</p>
                    </div>
                </div>
                <div>
                    <div class="uk-card uk-card-default uk-card-body">
                        <h3><?= $upcomingCount; ?></h3>
                        <p>Upcoming Appointments</p>
                    </div>
                </div>
            </div>

            <!-- ✅ Dashboard Navigation -->
            <div class="uk-margin-large-top">
                <h3>Manage Appointments</h3>
                <div class="uk-grid-small uk-child-width-1-1" uk-grid>
                    <div>
                        <a href="validate_appointments.php" class="uk-button uk-button-primary uk-width-1-1">
                            Validate Appointments (<?= $pendingCount; ?>)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
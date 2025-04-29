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

    <style>
        html,
        body {
            background-color: #ffffff !important;
        }

        @media (max-width: 640px) {
            body {
                font-size: 14px;
            }

            h1 {
                font-size: 20px;
            }

            .quick-stat-card h3{
                margin: 0;
            }

            .quick-stat-card p{
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <h1 class="uk-text-bold">Appointment Management Dashboard</h1>

    <!-- ✅ Quick Statistics -->
    <div class="uk-grid-small uk-child-width-1-3@s uk-text-center" uk-grid>
        <div>
            <div class="uk-card uk-card-default uk-card-body quick-stat-card">
                <h3><?= $totalCount; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        <div>
            <div class="uk-card uk-card-default uk-card-body quick-stat-card">
                <h3><?= $pendingCount; ?></h3>
                <p>Pending Appointments</p>
            </div>
        </div>
        <div>
            <div class="uk-card uk-card-default uk-card-body quick-stat-card">
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
                <a href="validate_appointments.php" class="uk-button uk-button-primary uk-width-1-1" style="border-radius: 15px;">
                    Validate Appointments (<?= $pendingCount; ?>)
                </a>
            </div>
        </div>
    </div>
    </div>
    </div>
</body>

</html>
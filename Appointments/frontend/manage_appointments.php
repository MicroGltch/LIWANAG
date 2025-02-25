<?php
    require_once "../../dbconfig.php";
    session_start();

    // ✅ Restrict Access to Admins & Head Therapists Only
    if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
        header("Location: ../../Accounts/loginpage.php");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Appointment Management Dashboard</h2>

        <!-- ✅ Quick Statistics -->
        <div class="uk-grid-small uk-child-width-1-3@s uk-text-center" uk-grid>
            <div>
                <div class="uk-card uk-card-default uk-card-body">
                    <h3><?= $totalCount; ?></h3>
                    <p>Total Appointments</p>
                </div>
            </div>
            <div>
                <div class="uk-card uk-card-primary uk-card-body">
                    <h3><?= $pendingCount; ?></h3>
                    <p>Pending Appointments</p>
                </div>
            </div>
            <div>
                <div class="uk-card uk-card-success uk-card-body">
                    <h3><?= $upcomingCount; ?></h3>
                    <p>Upcoming Appointments</p>
                </div>
            </div>
        </div>

        <!-- ✅ Dashboard Navigation -->
        <div class="uk-margin-large-top">
            <h3>Manage Appointments</h3>
            <div class="uk-grid-small uk-child-width-1-2@s" uk-grid>
                <div>
                    <a href="validate_appointments.php" class="uk-button uk-button-primary uk-width-1-1">
                        Validate Appointments (<?= $pendingCount; ?>)
                    </a>
                </div>
                <div>
                    <a href="view_all_appointments.php" class="uk-button uk-button-secondary uk-width-1-1">
                        View All Appointments
                    </a>
                </div>
                <div>
                    <a href="manage_timetable_settings.php" class="uk-button uk-button-default uk-width-1-1">
                        Timetable Settings
                    </a>
                </div>
                <div>
                    <a href="../dashboard/dashboard.php" class="uk-button uk-button-danger uk-width-1-1">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

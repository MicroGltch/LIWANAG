<!-- FRONT END made by Gracel -->

<?php
require_once "../../../../dbconfig.php";
session_start();

// ✅ Ensure only Admins & Head Therapists can access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../../loginpage.php");
    exit();
}

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
    <link rel="stylesheet" href="../../../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../../../CSS/style.css" type="text/css" />
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
                                    <img class="profile-image" src="../CSS/default.jpg" alt="Profile Image" uk-img>
                                </a>
                            </li>
                            <li style="display: flex; align-items: center;">  <?php echo $_SESSION['username'];?>
                            </li>
                            <li><a href="../../../logout.php">Logout</a></li>
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
                    <li class="uk-active"><a href="../../HeadTherapist/frontend/headtherapist_dashboard.php">Dashboard</a></li>
                    <li><a href="../webpage_settings/frontend/timetable_settings.php">Manage Timetable Settings</a></li>
                    <li><a href="../../../../appointments/frontend/manage_appointments.php">View & Manage Appointments</a></li>
                    <li><a href="view_all_appointments.php">View All Appointments</a></li>
                    <li><a href="">Manage Therapists [NOT IMPLEMENTED YET]</a></li>
                </ul>
            </div>
        </div>
    
        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <!-- Dashboard Section -->
            <div id="dashboard" class="section">
                <h1 class="uk-text-bold">Admin Panel</h1>
            </div>
        

     <!--   <div> 
        <h3>Admin Panel</h3>

            <ul class="uk-list uk-list-divider">
                <li><a href="../webpage_settings/frontend/timetable_settings.php">Manage Timetable Settings</a></li>
                <li><a href="../../../../appointments/frontend/manage_appointments.php">View & Manage Appointments</a></li>
                <li><a href="view_all_appointments.php">View All Appointments</a></li>
                <li><a href="">Manage Therapists [NOT IMPLEMENTED YET]</a></li>
            </ul>

        <a href="../../../logout.php" class="uk-button uk-button-danger uk-margin-top">Logout</a>
    </div>

    <div class="uk-container uk-margin-top">
        <h2>Appointment Overview</h2> -->

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
                order: [[2, 'asc']], // Sort by date column by default
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
                    { orderable: true, targets: '_all' }, // Make all columns sortable
                    { type: 'date', targets: 2 } // Specify date type for date column
                ]
            });
        });
        </script>

        <hr>

    </div>



<script>

</script>

</body>
</html>

<?php
require_once "../../../../dbconfig.php";
session_start();

// ✅ Ensure only Admins & Head Therapists can access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../../loginpage.php");
    exit();
}

// ✅ Query to count appointments by type
$countQuery = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$result = $connection->query($countQuery);
$appointmentCounts = [];
while ($row = $result->fetch_assoc()) {
    $appointmentCounts[$row['status']] = $row['count'];
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


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Appointment Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <div> 
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
        <h2>Appointment Overview</h2>

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
        <table class="uk-table uk-table-striped">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
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

        <hr>

    </div>



<script>

</script>

</body>
</html>


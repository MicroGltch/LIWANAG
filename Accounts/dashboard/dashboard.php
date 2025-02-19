<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['account_ID'])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// ✅ Use correct session variable for role
if (!isset($_SESSION['account_Type'])) {
    $_SESSION['error'] = "Session expired. Please log in again.";
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

$role = strtolower(trim($_SESSION['account_Type'])); // ✅ Convert to lowercase & remove spaces
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Welcome, <?= $_SESSION['username']; ?>!</h2>
        <p>Your Role: <strong><?= ucfirst($role); ?></strong></p>

        <?php if ($role === 'admin'): ?>
            <h3>Admin Panel</h3>
            <ul class="uk-list uk-list-divider">
                <li><a href="../../webpage_settings/frontend/timetable_settings.php">Manage Timetable Settings</a></li>
                <li><a href="../appointments/manage_appointments.php">View & Manage Appointments</a></li>
                <li><a href="../therapist/manage_therapists.php">Manage Therapists</a></li>
            </ul>
        <?php elseif ($role === 'therapist'): ?>
            <h3>Therapist Panel</h3>
            <ul class="uk-list uk-list-divider">
                <li><a href="../therapist/view_schedule.php">View Assigned Appointments</a></li>
                <li><a href="../therapist/update_availability.php">Set Availability</a></li>
            </ul>
        <?php else: ?>
            <h3>Client Panel</h3>
            <ul class="uk-list uk-list-divider">
                <li><a href="../../Appointments/patient/register_patient.php">Register a Patient</a></li>
                <li><a href="../../Appointments/frontend/book_appointment_form.php">Book an Appointment</a></li>
                <li><a href="../Appointments/view_appointments.php">View My Appointments</a></li>
            </ul>
        <?php endif; ?>

        <a href="../logout.php" class="uk-button uk-button-danger uk-margin-top">Logout</a>
    </div>
</body>
</html>

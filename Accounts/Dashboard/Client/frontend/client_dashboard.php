<?php
require_once "../../../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "client") {
    header("Location: ../../../loginpage.php");
    exit();
}
?>

<div class="uk-container uk-margin-top">
    <h2>Welcome, <?= htmlspecialchars($_SESSION['account_FName'] ?? "User"); ?>!</h2>

    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h3>Client Panel</h3>
        <ul class="uk-list uk-list-divider">
            <li><a href="../../../../Appointments/patient/frontend/register_patient_form.php">Register a Patient</a></li>
            <li><a href="../../../../Appointments/patient/frontend/edit_patient_form.php">View My Registered Patients</a></li>
            <li><a href="../../../../Appointments/frontend/book_appointment_form.php">Book an Appointment</a></li>
            <li><a href="client_view_appointments.php">View My Appointments</a></li>
        </ul>
    </div>

    <a href="../../../logout.php" class="uk-button uk-button-danger uk-margin-top">Logout</a>
</div>

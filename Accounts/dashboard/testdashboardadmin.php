<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || $_SESSION['account_Tyoe'] !== "Admin") {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Welcome, Admin!</h2>

        <ul class="uk-list uk-list-divider">
            <li><a href="../../webpage_settings/frontend/book_appointment_form">Manage Timetable Settings</a></li>
            <li><a href="../../Appointments/frontend/book_appointment_form.php">Book Appointment Form</a></li>
        </ul>

        <a href="../../Accounts/logout.php" class="uk-button uk-button-danger uk-margin-top">Logout</a>
    </div>
</body>
</html>

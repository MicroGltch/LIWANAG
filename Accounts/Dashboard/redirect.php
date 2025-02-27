<?php
session_start();

if (!isset($_SESSION['account_Type'])) {
    header("Location: ../loginpage.php");
    exit();
}

$role = strtolower($_SESSION['account_Type']);

switch ($role) {
    case 'admin':
        header("Location: headtherapist/headtherapist_dashboard.php");
        break;
    case 'head therapist':
        header("Location: headtherapist/headtherapist_dashboard.php");
        break;
    case 'therapist':
        header("Location: therapist/frontend/therapist_dashboard.php");
        break;
    case 'client':
        header("Location: client/frontend/client_dashboard.php");
        break;
    default:
        header("Location: ../loginpage.php");
}

exit();
?>

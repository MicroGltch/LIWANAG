<?php
require_once "../../dbconfig.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointmentID = $_POST['appointment_id'] ?? null;
    $pg_attendance = $_POST['pg_attendance'] ?? null;

    if (!$appointmentID || !$pg_attendance) {
        exit();
    }

    $updateQuery = "UPDATE appointments SET pg_attendance = ? WHERE appointment_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("si", $pg_attendance, $appointmentID);
    $stmt->execute();
}
?>

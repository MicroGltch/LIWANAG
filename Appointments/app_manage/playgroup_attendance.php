<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict to head therapist
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "head therapist") {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// ✅ Submit attendance
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_attendance"])) {
    $pg_session_id = $_POST["pg_session_id"];
    $attendances = $_POST["attendance"]; // patient_id => 'Present'/'Absent'

    foreach ($attendances as $patient_id => $status) {
        // ✅ Insert or update attendance
        $stmt = $connection->prepare("
            INSERT INTO playgroup_attendance (pg_session_id, patient_id, status) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->bind_param("sis", $pg_session_id, $patient_id, $status);
        $stmt->execute();
    }

    // ✅ Mark session as completed
    $update = $connection->prepare("UPDATE playgroup_sessions SET status = 'Completed' WHERE pg_session_id = ?");
    $update->bind_param("s", $pg_session_id);
    $update->execute();

    // ✅ Store success message in session and redirect back
    $_SESSION['playgroup_success'] = "Attendance recorded and session marked as completed.";
    header("Location: playgroup_dashboard.php");
    exit();
}

// 🚨 If accessed directly, redirect to dashboard
header("Location: playgroup_dashboard.php");
exit();
?>

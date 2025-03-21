<?php
require_once "../../dbconfig.php";
session_start();

// Restrict access to Head Therapist
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "head therapist") {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = $_POST["date"];
    $time = $_POST["time"];
    $max_capacity = $_POST["max_capacity"];

    // Fetch duration from settings
    $settingsQuery = "SELECT playgroup_duration FROM settings LIMIT 1";
    $settingsResult = $connection->query($settingsQuery);
    $settings = $settingsResult->fetch_assoc();
    $durationMinutes = $settings["playgroup_duration"];
    $end_time = date("H:i:s", strtotime($time) + ($durationMinutes * 60));

    $sessionID = uniqid("PG_");

    // Insert session
    $insert = $connection->prepare("INSERT INTO playgroup_sessions (pg_session_id, date, time, end_time, current_count, max_capacity, status, created_at)
                                    VALUES (?, ?, ?, ?, 0, ?, 'Open', NOW())");
    $insert->bind_param("ssssi", $sessionID, $date, $time, $end_time, $max_capacity);

    if ($insert->execute()) {
        $_SESSION['playgroup_success'] = "Playgroup session created successfully!";
    } else {
        $_SESSION['playgroup_error'] = "Error: " . $connection->error;
    }

    // Redirect back to dashboard
    header("Location: playgroup_dashboard.php");
    exit();
}
?>

<?php
require_once "../../../../dbconfig.php";
session_start();

// ✅ Ensure Therapist Access
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$therapistID = $_SESSION['account_ID'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = $_POST['override_date'];
    $status = $_POST['status'];
    $startTime = $_POST['start_time'] ?? null;
    $endTime = $_POST['end_time'] ?? null;

    // ✅ If Custom availability is selected, ensure time is provided
    if ($status === "Custom" && (empty($startTime) || empty($endTime))) {
        echo json_encode(["status" => "error", "message" => "Please provide start and end time for custom availability."]);
        exit();
    }

    // ✅ Insert or update override
    $query = "INSERT INTO therapist_overrides (therapist_id, date, status, start_time, end_time)
              VALUES (?, ?, ?, ?, ?) 
              ON DUPLICATE KEY UPDATE status = VALUES(status), start_time = VALUES(start_time), end_time = VALUES(end_time)";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("issss", $therapistID, $date, $status, $startTime, $endTime);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Override updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update override."]);
    }
}
?>

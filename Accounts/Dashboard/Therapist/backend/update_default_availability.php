<?php
require_once "../../../../dbconfig.php";
session_start();

// ✅ Ensure Therapist Access
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$therapistID = $_SESSION['account_ID'];
$daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ✅ Begin transaction
    $connection->begin_transaction();

    try {
        // ✅ Delete existing default availability
        $deleteQuery = "DELETE FROM therapist_default_availability WHERE therapist_id = ?";
        $stmt = $connection->prepare($deleteQuery);
        $stmt->bind_param("i", $therapistID);
        $stmt->execute();

        // ✅ Insert new availability for each selected day
        $insertQuery = "INSERT INTO therapist_default_availability (therapist_id, day, start_time, end_time) VALUES (?, ?, ?, ?)";
        $stmt = $connection->prepare($insertQuery);

        foreach ($daysOfWeek as $day) {
            if (!empty($_POST['start_time'][$day]) && !empty($_POST['end_time'][$day])) {
                $startTime = $_POST['start_time'][$day];
                $endTime = $_POST['end_time'][$day];
                $stmt->bind_param("isss", $therapistID, $day, $startTime, $endTime);
                $stmt->execute();
            }
        }

        // ✅ Commit transaction
        $connection->commit();
        echo json_encode(["status" => "success", "message" => "Default availability updated successfully."]);
    } catch (Exception $e) {
        $connection->rollback();
        echo json_encode(["status" => "error", "message" => "Failed to update availability."]);
    }
}
?>

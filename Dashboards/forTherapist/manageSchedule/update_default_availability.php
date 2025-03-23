<?php
require_once "../../../dbconfig.php";
session_start();

// ✅ Ensure Therapist Access
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$therapistID  = $_SESSION['account_ID'];
$daysOfWeek   = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $connection->begin_transaction();

    try {
        // ✅ Fetch global business hours from settings (only one row expected)
        $settingsQuery  = "SELECT business_hours_start, business_hours_end FROM settings LIMIT 1";
        $settingsResult = $connection->query($settingsQuery);
        if (!$settingsResult || $settingsResult->num_rows === 0) {
            throw new Exception("Business hours not configured.");
        }

        $settings = $settingsResult->fetch_assoc();
        $bizStart = $settings['business_hours_start']; // e.g., "08:00:00"
        $bizEnd   = $settings['business_hours_end'];   // e.g., "17:00:00"

        // ✅ Delete existing therapist availability
        $deleteQuery = "DELETE FROM therapist_default_availability WHERE therapist_id = ?";
        $stmt        = $connection->prepare($deleteQuery);
        $stmt->bind_param("i", $therapistID);
        $stmt->execute();

        // ✅ Prepare insert query
        $insertQuery = "INSERT INTO therapist_default_availability (therapist_id, day, start_time, end_time) VALUES (?, ?, ?, ?)";
        $stmt        = $connection->prepare($insertQuery);

        // ✅ Loop through each weekday and insert validated availability
        foreach ($daysOfWeek as $day) {
            if (!empty($_POST['start_time'][$day]) && !empty($_POST['end_time'][$day])) {
                $startTime = date("H:i:s", strtotime($_POST['start_time'][$day]));
                $endTime   = date("H:i:s", strtotime($_POST['end_time'][$day]));                

                // ✅ Validate: within business hours and logical range
                if ($startTime < $bizStart || $endTime > $bizEnd || $startTime >= $endTime) {
                    $rangeStart = date("g:i A", strtotime($bizStart));
                    $rangeEnd   = date("g:i A", strtotime($bizEnd));
                    throw new Exception("Invalid hours on {$day}. Must be between {$rangeStart} and {$rangeEnd}.");
                }

                $stmt->bind_param("isss", $therapistID, $day, $startTime, $endTime);
                $stmt->execute();
            }
        }

        // ✅ Commit changes
        $connection->commit();
        echo json_encode([
            "status"  => "success",
            "message" => "Default availability updated successfully."
        ]);

    } catch (Exception $e) {
        $connection->rollback();
        echo json_encode([
            "status"  => "error",
            "message" => $e->getMessage()
        ]);
    }
}
?>

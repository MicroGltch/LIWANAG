<?php
require_once "../../../dbconfig.php";
session_start();

// ✅ Use correct session variable for role
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "admin") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// ✅ Ensure POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $business_hours_start = $_POST['business_hours_start'];
    $business_hours_end = $_POST['business_hours_end'];
    $max_days_advance = $_POST['max_days_advance'];
    $min_days_advance = $_POST['min_days_advance'];
    $blocked_dates = isset($_POST['blocked_dates']) ? explode(",", str_replace(" ", "", $_POST['blocked_dates'])) : [];

    // ✅ Convert blocked dates to JSON format
    $blocked_dates_json = json_encode($blocked_dates);

    // ✅ Ensure settings table is updated
    $query = "UPDATE settings SET 
                business_hours_start = ?, 
                business_hours_end = ?, 
                max_days_advance = ?, 
                min_days_advance = ?, 
                blocked_dates = ?, 
                updated_at = NOW()
              WHERE setting_id = 1";

    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssiis", $business_hours_start, $business_hours_end, $max_days_advance, $min_days_advance, $blocked_dates_json);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Settings updated successfully.", "updated_at" => date("F d, Y h:i A")]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update settings. SQL Error: " . $stmt->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>

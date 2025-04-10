
<?php
    require_once "../../dbconfig.php";

    header("Content-Type: application/json");

    $query = "SELECT max_days_advance, min_days_advance, blocked_dates
            FROM settings LIMIT 1";
    $result = $connection->query($query);

    if ($result && $result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        $settings['blocked_dates'] = json_decode($settings['blocked_dates'], true); // Convert JSON

        echo json_encode(["status" => "success", "settings" => $settings]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to fetch timetable settings"]);
    }
?>



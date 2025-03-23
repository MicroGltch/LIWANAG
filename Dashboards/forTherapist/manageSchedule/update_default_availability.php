<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$therapistID  = $_SESSION['account_ID'];
$daysOfWeek   = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $connection->begin_transaction();

    try {
        // ✅ Fetch global business hours by day
        $globalHours = [];
        $query = "SELECT day_name, start_time, end_time FROM business_hours_by_day";
        $result = $connection->query($query);
        while ($row = $result->fetch_assoc()) {
            $globalHours[$row['day_name']] = [
                'start' => $row['start_time'],
                'end'   => $row['end_time']
            ];
        }

        // ✅ Delete existing therapist availability
        $deleteQuery = "DELETE FROM therapist_default_availability WHERE therapist_id = ?";
        $stmt        = $connection->prepare($deleteQuery);
        $stmt->bind_param("i", $therapistID);
        $stmt->execute();

        // ✅ Prepare insert query
        $insertQuery = "INSERT INTO therapist_default_availability (therapist_id, day, start_time, end_time) VALUES (?, ?, ?, ?)";
        $stmt        = $connection->prepare($insertQuery);

        $errorDays = [];
        $fieldErrors = [];

        foreach ($daysOfWeek as $day) {
            if (!empty($_POST['start_time'][$day]) && !empty($_POST['end_time'][$day])) {
                $startTime = date("H:i:s", strtotime($_POST['start_time'][$day]));
                $endTime   = date("H:i:s", strtotime($_POST['end_time'][$day]));

                $allowedStart = $globalHours[$day]['start'] ?? null;
                $allowedEnd   = $globalHours[$day]['end'] ?? null;

                // ❗ Day is closed globally
                if ($allowedStart === null || $allowedEnd === null) {
                    $errorDays[] = "$day is marked as closed.";
                    $fieldErrors[$day] = "$day is marked as closed by the center.";
                    continue;
                }

                // ❗ Time out of range or invalid
                if ($startTime < $allowedStart || $endTime > $allowedEnd || $startTime >= $endTime) {
                    $msg = "$day must be within " . date("g:i A", strtotime($allowedStart)) . " to " . date("g:i A", strtotime($allowedEnd)) . " and start time must be before end time.";
                    $errorDays[] = $msg;
                    $fieldErrors[$day] = $msg;
                    continue;
                }

                $stmt->bind_param("isss", $therapistID, $day, $startTime, $endTime);
                $stmt->execute();
            }
        }

        if (!empty($errorDays)) {
            throw new Exception(json_encode(["type" => "field", "messages" => $fieldErrors]));
        }

        $connection->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Default availability updated successfully."
        ]);

    } catch (Exception $e) {
        $connection->rollback();

        $msg = $e->getMessage();
        $decoded = json_decode($msg, true);

        if (isset($decoded['type']) && $decoded['type'] === 'field') {
            echo json_encode([
                "status"  => "field_error",
                "field_errors" => $decoded['messages']
            ]);
        } else {
            echo json_encode([
                "status"  => "error",
                "message" => $msg
            ]);
        }
    }
}
?>

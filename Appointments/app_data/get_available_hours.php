<?php
require_once "../../dbconfig.php";
header("Content-Type: application/json");

if (!isset($_GET['date'])) {
    echo json_encode(["status" => "error", "message" => "No date provided"]);
    exit;
}

$date = $_GET['date'];
$dayName = date("l", strtotime($date));

// ✅ Check if date is blocked
$settingsQuery = "SELECT blocked_dates FROM settings LIMIT 1";
$result = $connection->query($settingsQuery);
$row = $result->fetch_assoc();
$blocked = json_decode($row['blocked_dates'], true);
if (in_array($date, $blocked)) {
    echo json_encode(["status" => "closed", "message" => "Date is blocked"]);
    exit;
}

// ✅ Check for override
$stmt = $connection->prepare("SELECT start_time, end_time FROM business_hours_exceptions WHERE exception_date = ?");
$stmt->bind_param("s", $date);
$stmt->execute();
$res = $stmt->get_result();

if ($override = $res->fetch_assoc()) {
    if (is_null($override['start_time']) || is_null($override['end_time'])) {
        echo json_encode(["status" => "closed", "message" => "Date is marked as closed"]);
    } else {
        echo json_encode([
            "status" => "open",
            "start" => $override['start_time'],
            "end" => $override['end_time'],
            "start_ampm" => date("g:i A", strtotime($override['start_time'])),
            "end_ampm" => date("g:i A", strtotime($override['end_time']))
        ]);
    }
    exit;
}

// ✅ Fallback to business_hours_by_day
$stmt = $connection->prepare("SELECT start_time, end_time FROM business_hours_by_day WHERE day_name = ?");
$stmt->bind_param("s", $dayName);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row || is_null($row['start_time']) || is_null($row['end_time'])) {
    echo json_encode(["status" => "closed", "message" => "Center is closed on this day"]);
} else {
    echo json_encode([
        "status" => "open",
        "start" => $row['start_time'],
        "end" => $row['end_time'],
        "start_ampm" => date("g:i A", strtotime($row['start_time'])),
        "end_ampm" => date("g:i A", strtotime($row['end_time']))
    ]);
}
?>

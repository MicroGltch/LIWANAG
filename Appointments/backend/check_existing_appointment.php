<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit();
}

$patient_id = $_GET['patient_id'] ?? null;
$appointment_type = $_GET['appointment_type'] ?? null;

if (!$patient_id || !$appointment_type) {
    echo json_encode(["status" => "error", "message" => "Patient ID and Appointment Type are required."]);
    exit();
}

// ✅ Query: Check for any **Pending, Approved, or Waitlisted** appointment with the same session type
$query = "SELECT session_type, status, date, time 
          FROM appointments 
          WHERE patient_id = ? 
          AND status IN ('pending', 'approved', 'waitlisted')";

$stmt = $connection->prepare($query);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $connection->error]);
    exit();
}

$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$existing_sessions = [];

while ($row = $result->fetch_assoc()) {
    $existing_sessions[] = [
        "session_type" => $row['session_type'],
        "status" => $row['status'],
        "date" => $row['date'],
        "time" => $row['time']
    ];
}

$stmt->close();

// ✅ **Check Rules**
foreach ($existing_sessions as $existing) {
    // ❌ Rule 1: **Prevent duplicate Pending/Approved/Waitlisted session type**
    if ($existing['session_type'] === $appointment_type) {
        echo json_encode([
            "status" => "error",
            "message" => "This patient already has a <strong>{$existing['status']}</strong> appointment for <strong>{$existing['session_type']}</strong>.",
            "existing_session_type" => $existing['session_type'],
            "existing_status" => $existing['status'],
            "existing_date" => $existing['date'],
            "existing_time" => $existing['time']
        ]);
        exit();
    }

    // ❌ Rule 2: **If patient is in 'Rebooking', they should NOT book Initial Evaluation again**
    if ($existing['session_type'] === "rebooking" && $appointment_type === "Initial Evaluation") {
        echo json_encode([
            "status" => "error",
            "message" => "This patient is already in <strong>Rebooking</strong>. An Initial Evaluation is no longer required.",
            "existing_session_type" => $existing['session_type'],
            "existing_status" => $existing['status'],
            "existing_date" => $existing['date'],
            "existing_time" => $existing['time']
        ]);
        exit();
    }
}

// ✅ No conflicts, allow booking
echo json_encode(["status" => "success"]);
exit();

?>

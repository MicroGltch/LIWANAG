<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ['admin', 'head therapist'])) {
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit();
}

if (!isset($_GET['date']) || !isset($_GET['time'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit();
}

$date = $_GET['date'];
$time = $_GET['time'];
$dayOfWeek = date('l', strtotime($date));
$allTherapists = [];

// 1️⃣ Fetch ALL therapists
$query = "SELECT account_ID, account_FName, account_LName FROM users WHERE account_Type = 'therapist'";
$stmt = $connection->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $allTherapists[$row['account_ID']] = [
        "id" => $row['account_ID'],
        "name" => $row['account_FName'] . " " . $row['account_LName'],
        "available" => false, // Default to unavailable
        "schedule" => "",
        "status" => "Unavailable"
    ];
}

// 2️⃣ Get Default Availability for the Day
$query = "SELECT therapist_id, start_time, end_time 
          FROM therapist_default_availability
          WHERE day = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $dayOfWeek);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (isset($allTherapists[$row['therapist_id']])) {
        $startTime = strtotime($row['start_time']);
        $endTime = strtotime($row['end_time']);
        $appointmentTime = strtotime($time);

        $allTherapists[$row['therapist_id']]['schedule'] = date("h:i A", $startTime) . " - " . date("h:i A", $endTime);
        
        if ($appointmentTime >= $startTime && $appointmentTime < $endTime) {
            $allTherapists[$row['therapist_id']]['available'] = true;
            $allTherapists[$row['therapist_id']]['status'] = "Available";
        } else {
            $allTherapists[$row['therapist_id']]['status'] = "Schedule Time Conflict";
        }
    }
}

// 3️⃣ Apply Overrides
$query = "SELECT therapist_id, status, start_time, end_time 
          FROM therapist_overrides 
          WHERE date = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (isset($allTherapists[$row['therapist_id']])) {
        if ($row['status'] === 'Unavailable') {
            $allTherapists[$row['therapist_id']]['available'] = false;
            $allTherapists[$row['therapist_id']]['schedule'] = "Unavailable";
            $allTherapists[$row['therapist_id']]['status'] = "Unavailable";
        } elseif ($row['status'] === 'Custom') {
            $startTime = strtotime($row['start_time']);
            $endTime = strtotime($row['end_time']);
            $appointmentTime = strtotime($time);

            $allTherapists[$row['therapist_id']]['schedule'] = date("h:i A", $startTime) . " - " . date("h:i A", $endTime);

            if ($appointmentTime >= $startTime && $appointmentTime < $endTime) {
                $allTherapists[$row['therapist_id']]['available'] = true;
                $allTherapists[$row['therapist_id']]['status'] = "Available";
            } else {
                $allTherapists[$row['therapist_id']]['available'] = false;
                $allTherapists[$row['therapist_id']]['status'] = "Time Conflict (Custom Schedule)";
            }
        }
    }
}

// 3.5️⃣ Check for therapists already assigned to approved appointments at the same date and time
$query = "SELECT therapist_id FROM appointments 
          WHERE date = ? AND time = ? AND status = 'approved'";
$stmt = $connection->prepare($query);
$stmt->bind_param("ss", $date, $time);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tid = $row['therapist_id'];
    if (isset($allTherapists[$tid])) {
        $allTherapists[$tid]['available'] = false;
        $allTherapists[$tid]['status'] = "Unavailable (Already Booked)";
        $allTherapists[$tid]['schedule'] = "Conflicting with another appointment at " . date("h:i A", strtotime($time));
    }    
}


// 4️⃣ Format therapist data
$therapistsList = array_values($allTherapists);
usort($therapistsList, function($a, $b) {
    $statusPriority = [
        "Available" => 1,
        "Time Conflict" => 2,
        "Time Conflict (Custom Schedule)" => 2,
        "Unavailable (Already Booked)" => 3,
        "Unavailable" => 4
    ];

    $aPriority = $statusPriority[$a['status']] ?? 99;
    $bPriority = $statusPriority[$b['status']] ?? 99;

    return $aPriority <=> $bPriority;
});


echo json_encode(["status" => "success", "therapists" => $therapistsList]);
?>

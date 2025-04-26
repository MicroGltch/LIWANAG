<?php
header('Content-Type: application/json');
require_once "../../../dbconfig.php";
session_start();

// --- Authentication ---
if (!isset($_SESSION['account_ID']) || !isset($_SESSION['account_Type']) || strtolower($_SESSION['account_Type']) !== 'therapist') {
    http_response_code(403); // Forbidden
    echo json_encode(["status" => "error", "message" => "Access denied. Therapist login required."]);
    exit();
}

$therapist_id = $_SESSION['account_ID'];

// --- Input Validation (Date Range) ---
$startDateStr = $_GET['start_date'] ?? null;
$endDateStr = $_GET['end_date'] ?? null;

// Default to current week if not provided
if (!$startDateStr) {
    $startDate = new DateTime('monday this week');
    $startDateStr = $startDate->format('Y-m-d');
} else {
    try {
        $startDate = new DateTime($startDateStr);
    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Invalid start date format. Use YYYY-MM-DD."]);
        exit();
    }
}

if (!$endDateStr) {
    $endDate = clone $startDate;
    $endDate->modify('+6 days'); // Get Sunday of the start week
    $endDateStr = $endDate->format('Y-m-d');
} else {
     try {
        $endDate = new DateTime($endDateStr);
     } catch (Exception $e) {
         http_response_code(400); // Bad Request
         echo json_encode(["status" => "error", "message" => "Invalid end date format. Use YYYY-MM-DD."]);
         exit();
     }
}

if ($endDate < $startDate) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "End date cannot be before start date."]);
    exit();
}

// --- Core Logic ---
// Include or define the schedule fetching function
require_once "../../forAdmin/schedule_functions.php"; // Separate file for shared logic

$scheduleData = getTherapistScheduleData($connection, $therapist_id, $startDate, $endDate);

if ($scheduleData === null) {
     // Error occurred within getTherapistScheduleData, already logged
     http_response_code(500);
     echo json_encode(["status" => "error", "message" => "Failed to retrieve schedule data."]);
} else {
    echo json_encode(["status" => "success", "schedule" => $scheduleData]);
}

$connection->close();
?>
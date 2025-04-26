<?php
header('Content-Type: application/json');
require_once "../../dbconfig.php";
session_start();

// --- Authentication ---
if (!isset($_SESSION['account_ID']) || !isset($_SESSION['account_Type']) || !in_array(strtolower($_SESSION['account_Type']), ['head therapist', 'admin'])) {
    http_response_code(403); // Forbidden
    echo json_encode(["status" => "error", "message" => "Access denied. Head Therapist or Admin login required."]);
    exit();
}

// --- Determine Action ---
$action = $_GET['action'] ?? 'get_schedule'; // Default to fetching schedule

// Include or define shared functions
require_once "schedule_functions.php";

global $connection; // Make connection available to functions if not passed

// === Action: Get List of Therapists ===
if ($action === 'get_list') {
    $therapists = [];
    $sql = "SELECT account_ID, account_FName, account_LName
            FROM users
            WHERE account_Type = 'therapist' AND account_Status = 'Active'
            ORDER BY account_LName, account_FName";

    if ($stmt = $connection->prepare($sql)) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $therapists[] = [
                    'id' => $row['account_ID'],
                    'firstName' => $row['account_FName'],
                    'lastName' => $row['account_LName']
                    // No need to map directly to Swift's 'Therapist' here,
                    // just provide the raw data needed for the picker.
                ];
            }
            $result->free();
            echo json_encode(["status" => "success", "therapists" => $therapists]);
        } else {
             error_log("Error executing therapist list query: " . $stmt->error);
             http_response_code(500);
             echo json_encode(["status" => "error", "message" => "Database error fetching therapist list."]);
        }
        $stmt->close();
    } else {
         error_log("Error preparing therapist list query: " . $connection->error);
         http_response_code(500);
         echo json_encode(["status" => "error", "message" => "Database error preparing therapist list."]);
    }
    $connection->close();
    exit(); // Important: exit after handling get_list
}


// === Action: Get Schedule (Default) ===
if ($action === 'get_schedule') {
    // --- Input Validation (Therapist ID) ---
    $therapist_id = filter_input(INPUT_GET, 'therapist_id', FILTER_VALIDATE_INT);
    if ($therapist_id === false || $therapist_id <= 0) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Valid therapist_id parameter is required."]);
        exit();
    }

     // Optional: Verify therapist_id exists and is a therapist
     $sql_verify = "SELECT COUNT(*) FROM users WHERE account_ID = ? AND account_Type = 'therapist'";
     $stmt_verify = $connection->prepare($sql_verify);
     $stmt_verify->bind_param("i", $therapist_id);
     $stmt_verify->execute();
     $stmt_verify->bind_result($count);
     $stmt_verify->fetch();
     $stmt_verify->close();
     if ($count === 0) {
         http_response_code(404); // Not Found
         echo json_encode(["status" => "error", "message" => "Therapist not found."]);
         exit();
     }


    // --- Input Validation (Date Range) ---
    // (Same date range logic as in get_my_schedule.php)
    $startDateStr = $_GET['start_date'] ?? null;
    $endDateStr = $_GET['end_date'] ?? null;

    if (!$startDateStr) {
        $startDate = new DateTime('monday this week');
         $startDateStr = $startDate->format('Y-m-d');
    } else {
        try { $startDate = new DateTime($startDateStr); } catch (Exception $e) {
            http_response_code(400); echo json_encode(["status" => "error", "message" => "Invalid start date."]); exit(); }
    }
    if (!$endDateStr) {
        $endDate = clone $startDate; $endDate->modify('+6 days'); $endDateStr = $endDate->format('Y-m-d');
    } else {
         try { $endDate = new DateTime($endDateStr); } catch (Exception $e) {
             http_response_code(400); echo json_encode(["status" => "error", "message" => "Invalid end date."]); exit(); }
    }
     if ($endDate < $startDate) {
        http_response_code(400); echo json_encode(["status" => "error", "message" => "End date before start date."]); exit();
    }

    // --- Core Logic ---
    $scheduleData = getTherapistScheduleData($connection, $therapist_id, $startDate, $endDate);

    if ($scheduleData === null) {
         http_response_code(500);
         echo json_encode(["status" => "error", "message" => "Failed to retrieve schedule data."]);
    } else {
        echo json_encode(["status" => "success", "schedule" => $scheduleData]);
    }
    $connection->close();
    exit();
}

// --- Invalid Action ---
http_response_code(400);
echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
$connection->close();

?>

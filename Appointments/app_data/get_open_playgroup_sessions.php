<?php
require_once "../../dbconfig.php";

// --- Set the Target Timezone ---
try {
    // Use the correct timezone identifier for the Philippines
    $timezone = new DateTimeZone('Asia/Manila'); 
} catch (Exception $e) {
    // Handle error if timezone identifier is invalid
    error_log("Invalid Timezone: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Internal server error: Invalid timezone configuration.",
        "sessions" => [] 
    ]);
    exit;
}

// --- Get Current Date in the Target Timezone ---
$now = new DateTime('now', $timezone); // Get current time in Asia/Manila
$currentDate = $now->format('Y-m-d');  // Format as 'YYYY-MM-DD' for SQL comparison

// --- Database Query (using Prepared Statement) ---
$query = "SELECT pg_session_id, date, time, current_count, max_capacity 
          FROM playgroup_sessions 
          WHERE status = 'Open' 
            AND current_count < max_capacity 
            AND date >= ?  -- Compare against the current date in Asia/Manila
          ORDER BY date ASC, time ASC";

// Prepare the statement
$stmt = $connection->prepare($query);

// Check if statement preparation was successful
if ($stmt === false) {
    // Handle error
    error_log("Prepare failed: (" . $connection->errno . ") " . $connection->error);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare the database query.",
        "sessions" => [] 
    ]);
    exit; 
}

// Bind the current date parameter (calculated using Asia/Manila timezone)
// 's' indicates the parameter is a string
$stmt->bind_param('s', $currentDate);

// Execute the statement
if (!$stmt->execute()) {
     // Handle execution error
     error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
     echo json_encode([
        "status" => "error",
        "message" => "Failed to execute the database query.",
        "sessions" => []
     ]);
     $stmt->close(); 
     exit; 
}

// Get the result set
$result = $stmt->get_result();

$sessions = [];
// Fetch results
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
} 
// No specific error handling needed here for get_result if execute succeeded

// Close the statement
$stmt->close();

// --- Encode the results as JSON ---
$output_status = !empty($sessions) ? "success" : "no_sessions"; // More specific status
$output_message = "";
if ($output_status === 'no_sessions') {
     $output_message = "No open sessions found for today or future dates.";
}

echo json_encode([
    "status" => $output_status, 
    "message" => $output_message, 
    "sessions" => $sessions,
    "debug_current_date_used" => $currentDate // Optional: helpful for debugging
]);
?>
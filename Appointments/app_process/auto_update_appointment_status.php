<?php
require_once "../../dbconfig.php";

// Set the time zone
date_default_timezone_set('Asia/Manila');

// Check the database connection
if (!$connection) {
    $log_message = date('Y-m-d H:i:s') . " - Database connection failed\n";
    file_put_contents("status_update_log.txt", $log_message, FILE_APPEND);
    exit;
}

// Get current date and time for comparisons
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$now = date('H:i:s');

// Log the start of the process
$log_message = date('Y-m-d H:i:s') . " - Starting auto update process\n";
file_put_contents("status_update_log.txt", $log_message, FILE_APPEND);

// 1. Update Pending & Past to Cancelled
// According to business rule: "Pending & Past date/time of apt: show as Cancelled (since matic)"
$query1 = "UPDATE appointments 
           SET status = 'cancelled', 
               validation_notes = 'Auto cancelled: Appointment time passed without approval' 
           WHERE status = 'pending' AND 
                 (date < ? OR (date = ? AND time < ?))";
$stmt1 = $connection->prepare($query1);
$stmt1->bind_param("sss", $today, $today, $now);
$stmt1->execute();
$cancelled_count = $stmt1->affected_rows;

// Example of improved error handling:
if (!$stmt1->execute()) {
    $log_message = date('Y-m-d H:i:s') . " - Error updating pending to cancelled: " . $stmt1->error . "\n";
    file_put_contents("status_update_log.txt", $log_message, FILE_APPEND);
}

// 2. Update Approved & Past to Completed
// According to business rule: "Accepted & After date/time of apt: Show as Completed"
$query2 = "UPDATE appointments 
           SET status = 'completed' 
           WHERE status = 'approved' AND 
                 (date < ? OR (date = ? AND time < ?))";
$stmt2 = $connection->prepare($query2);
$stmt2->bind_param("sss", $today, $today, $now);
$stmt2->execute();
$completed_count = $stmt2->affected_rows;

// Example of improved error handling:
if (!$stmt2->execute()) {
    $log_message = date('Y-m-d H:i:s') . " - Error updating approved to completed: " . $stmt2->error . "\n";
    file_put_contents("status_update_log.txt", $log_message, FILE_APPEND);
}

// 3. Archive appointments that were completed yesterday
// According to business rule: "Status change to Archive once a day has passed"
$query3 = "UPDATE appointments 
           SET status = 'archived' 
           WHERE status = 'completed' AND date = ?";
$stmt3 = $connection->prepare($query3);
$stmt3->bind_param("s", $yesterday);
$stmt3->execute();
$archived_count = $stmt3->affected_rows;

// Example of improved error handling:
if (!$stmt3->execute()) {
    $log_message = date('Y-m-d H:i:s') . " - Error updating completed to archived: " . $stmt3->error . "\n";
    file_put_contents("status_update_log.txt", $log_message, FILE_APPEND);
}

// 4. Also update waitlisted & past to cancelled (additional business rule)
$query4 = "UPDATE appointments 
           SET status = 'cancelled', 
               validation_notes = 'Auto cancelled: Waitlisted appointment date passed' 
           WHERE status = 'waitlisted' AND date < ?";
$stmt4 = $connection->prepare($query4);
$stmt4->bind_param("s", $today);
$stmt4->execute();
$waitlisted_cancelled_count = $stmt4->affected_rows;

// Example of improved error handling:
if (!$stmt4->execute()) {
    $log_message = date('Y-m-d H:i:s') . " - Error updating completed to archived: " . $stmt4->error . "\n";
    file_put_contents("status_update_log.txt", $log_message, FILE_APPEND);
}


// Log the results
$log_message = "Results:\n";
$log_message .= "- Pending → Cancelled: " . $cancelled_count . "\n";
$log_message .= "- Approved → Completed: " . $completed_count . "\n";
$log_message .= "- Completed → Archived: " . $archived_count . "\n";
$log_message .= "- Waitlisted → Cancelled: " . $waitlisted_cancelled_count . "\n";
$log_message .= date('Y-m-d H:i:s') . " - Auto update completed\n";
file_put_contents("status_update_log.txt", $log_message, FILE_APPEND);

// Return results as JSON if called via AJAX, otherwise output text
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    echo json_encode([
        'status' => 'success',
        'cancelled' => $cancelled_count,
        'completed' => $completed_count,
        'archived' => $archived_count,
        'waitlisted_cancelled' => $waitlisted_cancelled_count
    ]);
} else {
    echo "Appointment statuses updated successfully.\n";
    echo "- Pending → Cancelled: " . $cancelled_count . "\n";
    echo "- Approved → Completed: " . $completed_count . "\n";
    echo "- Completed → Archived: " . $archived_count . "\n";
    echo "- Waitlisted → Cancelled: " . $waitlisted_cancelled_count . "\n";
}
?>
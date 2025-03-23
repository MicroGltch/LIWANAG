<?php
require_once "../../dbconfig.php";

$query = "SELECT pg_session_id, date, time, current_count, max_capacity 
          FROM playgroup_sessions 
          WHERE status = 'Open' AND current_count < max_capacity 
          ORDER BY date ASC, time ASC";

$result = $connection->query($query);

$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

echo json_encode([
    "status" => !empty($sessions) ? "success" : "error",
    "sessions" => $sessions
]);
?>

<?php
require_once "../../dbconfig.php";

//used in updatePGSlotDetails in valdate_appointmnets to dynamically show the date and time 

$pg_session_id = $_GET['pg_session_id'] ?? null;

if (!$pg_session_id) {
    echo json_encode(["status" => "error", "message" => "No session ID."]);
    exit;
}

$stmt = $connection->prepare("SELECT date, time FROM playgroup_sessions WHERE pg_session_id = ?");
$stmt->bind_param("s", $pg_session_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Session not found."]);
    exit;
}

$row = $result->fetch_assoc();
echo json_encode(["status" => "success", "session" => $row]);
?>

<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict to Head Therapist Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "head therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// ✅ Read JSON data
$data = json_decode(file_get_contents("php://input"), true);
$patient_id = $data["patient_id"] ?? null;
$pg_session_id = $data["pg_session_id"] ?? null;

if (!$patient_id || !$pg_session_id) {
    echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
    exit();
}

// ✅ Check if patient exists in the session
$checkQuery = "SELECT * FROM appointments WHERE patient_id = ? AND pg_session_id = ?";
$stmt = $connection->prepare($checkQuery);
$stmt->bind_param("is", $patient_id, $pg_session_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Patient not found in this session."]);
    exit();
}

// ✅ Remove patient from session
$deleteQuery = "DELETE FROM appointments WHERE patient_id = ? AND pg_session_id = ?";
$stmt = $connection->prepare($deleteQuery);
$stmt->bind_param("is", $patient_id, $pg_session_id);

if ($stmt->execute()) {
    // ✅ Decrease current_count in session
    $updateSessionQuery = "UPDATE playgroup_sessions SET current_count = current_count - 1 WHERE pg_session_id = ?";
    $stmt = $connection->prepare($updateSessionQuery);
    $stmt->bind_param("s", $pg_session_id);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Patient removed from session."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to remove patient."]);
}
?>

<?php
require_once "../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins & Head Therapists Only
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// ✅ Ensure POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];

    $new_status = "";
    if ($action === "approve") {
        $new_status = "Confirmed";
    } elseif ($action === "decline") {
        $new_status = "Declined";
    } elseif ($action === "waitlist") {
        $new_status = "Waitlisted";
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        exit();
    }

    // ✅ Update appointment status
    $query = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("si", $new_status, $appointment_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Appointment updated successfully.", "new_status" => $new_status]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update appointment."]);
    }
}
?>

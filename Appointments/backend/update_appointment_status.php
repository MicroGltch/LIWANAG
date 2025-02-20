<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ['admin', 'head therapist'])) {
    echo json_encode(["status" => "error", "title" => "Unauthorized", "message" => "Access denied."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    $therapist_id = $_POST['therapist_id'] ?? null;

    // ✅ Validate input
    if (!in_array($status, ["Approved", "Declined", "Waitlisted", "Cancelled"])) {
        echo json_encode(["status" => "error", "title" => "Invalid Action", "message" => "Invalid status update."]);
        exit();
    }

    // ✅ Fetch appointment details (for email notification)
    $query = "SELECT a.session_type, u.account_Email, p.first_name, p.last_name 
              FROM appointments a
              JOIN users u ON a.account_id = u.account_ID
              JOIN patients p ON a.patient_id = p.patient_id
              WHERE a.appointment_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        echo json_encode(["status" => "error", "title" => "Error", "message" => "Appointment not found."]);
        exit();
    }

    $appointment = $result->fetch_assoc();
    $email = $appointment['account_Email'];
    $session_type = $appointment['session_type'];
    $patient_name = $appointment['first_name'] . " " . $appointment['last_name'];

    // ✅ Update appointment status
    if ($status === "Approved") {
        if (!$therapist_id) {
            echo json_encode(["status" => "error", "title" => "Error", "message" => "A therapist must be assigned for approval."]);
            exit();
        }

        $updateQuery = "UPDATE appointments SET status = ?, therapist_id = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("sii", $status, $therapist_id, $appointment_id);
    } else {
        $updateQuery = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("si", $status, $appointment_id);
    }

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "title" => "Success", "message" => "Appointment updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "title" => "Error", "message" => "Failed to update appointment."]);
    }
}
?>

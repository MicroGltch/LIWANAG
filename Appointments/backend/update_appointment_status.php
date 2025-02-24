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
    if (!in_array($status, ["Approved", "Declined", "Waitlisted", "Cancelled", "Completed"])) {
        echo json_encode(["status" => "error", "title" => "Invalid Action", "message" => "Invalid status update."]);
        exit();
    }

    // ✅ Fetch appointment details
    $query = "SELECT a.session_type, a.date, a.time, a.status, u.account_Email, p.first_name, p.last_name 
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
    $appointment_date = $appointment['date'];
    $appointment_time = $appointment['time'];
    $patient_name = $appointment['first_name'] . " " . $appointment['last_name'];

    // ✅ Handle "Approved" Status (Requires Therapist Selection)
    if ($status === "Approved") {
        if (!$therapist_id) {
            echo json_encode(["status" => "error", "title" => "Validation Error", "message" => "A therapist must be assigned."]);
            exit();
        }

        $updateQuery = "UPDATE appointments SET status = ?, therapist_id = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("sii", $status, $therapist_id, $appointment_id);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "title" => "Appointment Approved",
                "message" => "Appointment for **$patient_name** on **$appointment_date at $appointment_time** has been **approved** and assigned to a therapist."
            ]);
            exit();
        }
    }

    // ✅ Handle "Completed" Status (Only if it's currently "Approved")
    if ($status === "Completed") {
        if ($appointment["status"] !== "Approved") {
            echo json_encode(["status" => "error", "title" => "Invalid Action", "message" => "Only approved appointments can be marked as completed."]);
            exit();
        }

        $updateQuery = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("si", $status, $appointment_id);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "title" => "Appointment Completed",
                "message" => "Appointment for **$patient_name** on **$appointment_date at $appointment_time** has been marked as **Completed**."
            ]);
            exit();
        }
    }

    // ✅ Handle "Declined", "Waitlisted", "Cancelled"
    if (in_array($status, ["Declined", "Waitlisted", "Cancelled"])) {
        $updateQuery = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("si", $status, $appointment_id);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "title" => "Appointment $status",
                "message" => "Appointment for **$patient_name** on **$appointment_date at $appointment_time** has been **$status**."
            ]);
            exit();
        }
    }

    // ✅ If we reached here, something went wrong
    echo json_encode(["status" => "error", "title" => "Error", "message" => "Failed to update appointment."]);
    exit();
}
?>

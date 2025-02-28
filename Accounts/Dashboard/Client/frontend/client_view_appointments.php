<?php
require_once "../../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || $_SESSION['account_Type'] !== "client") {
    header("Location: ../../../loginpage.php");
    exit();
}

$client_id = $_SESSION['account_ID'];

// ✅ Fetch all appointments for the logged-in client
$query = "SELECT a.appointment_id, a.date, a.time, a.status, a.session_type, a.edit_count,
                 p.first_name AS patient_name
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          WHERE a.account_id = ?
          ORDER BY a.date ASC, a.time ASC";

$stmt = $connection->prepare($query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>My Appointments</h2>

        <table class="uk-table uk-table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Session Type</th>
                    <th>Patient</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= htmlspecialchars($appointment['date']); ?></td>
                        <td><?= htmlspecialchars($appointment['time']); ?></td>
                        <td><?= htmlspecialchars($appointment['session_type']); ?></td>
                        <td><?= htmlspecialchars($appointment['patient_name']); ?></td>
                        <td><?= ucfirst($appointment['status']); ?></td>
                        <td>
                            <!-- ✅ Cancel button (Allowed only for "Pending" or "Waitlisted") -->
                            <?php if (in_array($appointment['status'], ["pending", "waitlisted"])): ?>
                                <button class="uk-button uk-button-danger cancel-btn" data-id="<?= $appointment['appointment_id']; ?>">Cancel</button>
                            <?php endif; ?>

                            <!-- ✅ Edit button (Only for "Pending" & edit_count < 2) -->
                            <?php if ($appointment['status'] === "pending" && $appointment['edit_count'] < 2): ?>
                                <button class="uk-button uk-button-primary edit-btn" data-id="<?= $appointment['appointment_id']; ?>"
                                    data-date="<?= $appointment['date']; ?>" data-time="<?= $appointment['time']; ?>">
                                    Edit (<?= 2 - $appointment['edit_count']; ?> left)
                                </button>
                            <?php else: ?>
                                <button class="uk-button uk-button-default" disabled>Editing Not Allowed</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <a href="../"></a>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
            // ✅ Cancel Appointment
        document.querySelectorAll(".cancel-btn").forEach(button => {
            button.addEventListener("click", function () {
                let appointmentId = this.getAttribute("data-id");

                Swal.fire({
                    title: "Cancel Appointment?",
                    text: "Please provide a reason for cancellation:",
                    icon: "warning",
                    input: "text",
                    inputPlaceholder: "Enter cancellation reason",
                    showCancelButton: true,
                    confirmButtonText: "Yes, Cancel",
                    cancelButtonText: "No, Keep Appointment",
                    preConfirm: (reason) => {
                        if (!reason) {
                            Swal.showValidationMessage("A cancellation reason is required.");
                        }
                        return reason;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch("../backend/client_edit_appointment.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ 
                                appointment_id: appointmentId, 
                                action: "cancel",
                                validation_notes: result.value 
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === "success") {
                                Swal.fire({
                                    title: data.title,
                                    text: data.message,
                                    icon: "success",
                                    confirmButtonText: "OK"
                                }).then(() => {
                                    location.reload(); // ✅ Reload after user sees the message
                                });
                            } else {
                                Swal.fire({
                                    title: data.title,
                                    text: data.message,
                                    icon: "error"
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: "Error",
                                text: "Something went wrong. Please try again.",
                                icon: "error"
                            });
                        });
                    }
                });
            });
        });

    // ✅ Edit Appointment (Reschedule)
    document.querySelectorAll(".edit-btn").forEach(button => {
        button.addEventListener("click", function () {
            let appointmentId = this.getAttribute("data-id");
            let currentStatus = this.getAttribute("data-status"); // Get status from dataset

            Swal.fire({
                title: "Edit Appointment",
                html: `<label>New Date:</label> <input type="date" id="appointmentDate" class="swal2-input">
                       <label>New Time:</label> <input type="time" id="appointmentTime" class="swal2-input">`,
                showCancelButton: true,
                confirmButtonText: "Save Changes",
                preConfirm: () => {
                    return {
                        newDate: document.getElementById("appointmentDate").value,
                        newTime: document.getElementById("appointmentTime").value
                    };
                }
            }).then((result) => {
                fetch("../backend/client_edit_appointment.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ appointment_id: appointmentId, action: "edit", new_date: result.value.newDate, new_time: result.value.newTime })
                })
                .then(response => response.json())
                .then(data => {
                    Swal.fire(data.title, data.message, data.status === "success" ? "success" : "error")
                        .then(() => location.reload());
                });
            });
        });
    });
});

    </script>
</body>
</html>

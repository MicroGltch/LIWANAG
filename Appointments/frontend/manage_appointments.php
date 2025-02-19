<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins & Head Therapists Only
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// Fetch appointments
$query = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.status, a.session_type, 
                 p.first_name, p.last_name, u.account_FName AS client_firstname, u.account_LName AS client_lastname 
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          ORDER BY a.date DESC, a.time DESC";
$result = $connection->query($query);
$appointments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Manage Appointments</h2>

        <table class="uk-table uk-table-striped">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Session Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                        <td><?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?></td>
                        <td><?= htmlspecialchars($appointment['date']); ?></td>
                        <td><?= htmlspecialchars($appointment['time']); ?></td>
                        <td><?= htmlspecialchars($appointment['session_type']); ?></td>
                        <td id="status-<?= $appointment['appointment_id']; ?>">
                            <?= htmlspecialchars($appointment['status']); ?>
                        </td>
                        <td>
                            <button class="uk-button uk-button-primary approve-btn" data-id="<?= $appointment['appointment_id']; ?>">Approve</button>
                            <button class="uk-button uk-button-danger decline-btn" data-id="<?= $appointment['appointment_id']; ?>">Decline</button>
                            <button class="uk-button uk-button-default waitlist-btn" data-id="<?= $appointment['appointment_id']; ?>">Waitlist</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".approve-btn, .decline-btn, .waitlist-btn").forEach(button => {
                button.addEventListener("click", function () {
                    let appointmentId = this.getAttribute("data-id");
                    let action = this.classList.contains("approve-btn") ? "approve" :
                                 this.classList.contains("decline-btn") ? "decline" : "waitlist";

                    Swal.fire({
                        title: "Are you sure?",
                        text: "You are about to update this appointment's status.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, proceed!"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch("../backend/update_appointment_status.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                body: `appointment_id=${appointmentId}&action=${action}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === "success") {
                                    document.getElementById(`status-${appointmentId}`).textContent = data.new_status;
                                    Swal.fire("Success!", data.message, "success");
                                } else {
                                    Swal.fire("Error!", data.message, "error");
                                }
                            })
                            .catch(error => console.error("Error:", error));
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

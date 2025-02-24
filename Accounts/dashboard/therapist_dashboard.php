<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header("Location: ../loginpage.php");
    exit();
}

$therapistID = $_SESSION['account_ID'];
$today = date("Y-m-d");

// Fetch therapist's upcoming appointments
$query = "SELECT a.appointment_id, a.date, a.time, a.session_type, a.status,
                 p.first_name, p.last_name 
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          WHERE a.therapist_id = ? AND a.status IN ('Approved', 'Pending')
          ORDER BY a.date ASC, a.time ASC";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $therapistID);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Therapist Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['username']); ?></h2>
        <p><strong>Today's Date:</strong> <?= date("F j, Y"); ?></p>

        <h3>Upcoming Appointments</h3>
        <table class="uk-table uk-table-striped">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Session Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                        <td><?= htmlspecialchars($appointment['session_type']); ?></td>
                        <td><?= htmlspecialchars($appointment['date']); ?></td>
                        <td><?= htmlspecialchars($appointment['time']); ?></td>
                        <td><?= htmlspecialchars($appointment['status']); ?></td>
                        <td>
                            <button class="uk-button uk-button-primary complete-btn" data-id="<?= $appointment['appointment_id']; ?>">Complete</button>
                            <button class="uk-button uk-button-default notes-btn" data-id="<?= $appointment['appointment_id']; ?>">Add Notes</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Manage Your Availability Schedule</h3>
        <a href="therapist/manage_availability.php" class="uk-button uk-button-secondary">Update Availability</a> <br/>
        <a href="therapist/override_availability.php" class="uk-button uk-button-default">Block Specific Availability</a> <br/>
        <a href="../logout.php" class="uk-button uk-button-default">Logout</a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // ✅ Mark Session as Completed
            document.querySelectorAll(".complete-btn").forEach(button => {
                button.addEventListener("click", function () {
                    let appointmentId = this.getAttribute("data-id");

                    Swal.fire({
                        title: "Mark as Completed?",
                        text: "This will mark the session as completed.",
                        icon: "question",
                        showCancelButton: true,
                        confirmButtonText: "Yes, complete",
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch("../backend/update_appointment_status.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                body: `appointment_id=${appointmentId}&status=Completed`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === "success") {
                                    Swal.fire("Success!", data.message, "success").then(() => location.reload());
                                } else {
                                    Swal.fire("Error!", data.message, "error");
                                }
                            })
                            .catch(error => console.error("Error:", error));
                        }
                    });
                });
            });

            // ✅ Add Session Notes
            document.querySelectorAll(".notes-btn").forEach(button => {
                button.addEventListener("click", function () {
                    let appointmentId = this.getAttribute("data-id");

                    Swal.fire({
                        title: "Add Session Notes",
                        input: "textarea",
                        inputPlaceholder: "Enter notes here...",
                        showCancelButton: true,
                        confirmButtonText: "Save Notes",
                        preConfirm: (notes) => {
                            return fetch("../backend/save_session_notes.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                body: `appointment_id=${appointmentId}&notes=${encodeURIComponent(notes)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status !== "success") {
                                    Swal.showValidationMessage(data.message);
                                }
                            })
                            .catch(error => Swal.showValidationMessage("Request failed"));
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire("Saved!", "Session notes have been saved.", "success");
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

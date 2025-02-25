<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header("Location: ../loginpage.php");
    exit();
}

$therapistID = $_SESSION['account_ID'];

// Fetch therapist's upcoming appointments
$query = "SELECT a.appointment_id, a.date, a.time, a.session_type, a.status,
                 p.patient_id, p.first_name, p.last_name 
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
                            <button class="uk-button uk-button-danger cancel-btn" data-id="<?= $appointment['appointment_id']; ?>">Cancel</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <br/>
    <div>
        <a href="therapist/rebook_patient.php" class="uk-button uk-button-secondary">Rebook a Previous Patient</a>
    </div>

    <div>
    <br/>
        <a href="therapist/manage_availability.php">Setup your default availability</a> <br/>
        <a href="therapist/override_availability.php">Unavailable for a specific date? Block a date schedule here</a>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // ✅ Complete Button - Ask if they want to rebook before marking as complete
            document.querySelectorAll(".complete-btn").forEach(button => {
                button.addEventListener("click", function () {
                    let appointmentId = this.getAttribute("data-id");

                    Swal.fire({
                        title: "Rebook Next Session?",
                        text: "Would you like to rebook a follow-up session before marking this as completed?",
                        icon: "question",
                        showDenyButton: true,    // ✅ Adds "Skip Rebooking" as a middle button
                        showCancelButton: true,  // ✅ Adds "Cancel" button
                        confirmButtonText: "Rebook",  
                        denyButtonText: "Skip Rebooking",  // ✅ This will mark as completed without rebooking
                        cancelButtonText: "Cancel",  // ✅ This will close the modal
                        allowOutsideClick: false, // ✅ Prevents accidental dismissals
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // ✅ Redirect to rebook page
                            window.location.href = `therapist/rebook_appointment.php?appointment_id=${appointmentId}`;
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            // ✅ Ensure confirmation before marking as complete
                            Swal.fire({
                                title: "Mark as Completed?",
                                text: "Are you sure you want to mark this session as completed?",
                                icon: "warning",
                                showCancelButton: true,
                                confirmButtonText: "Yes, Complete",
                                cancelButtonText: "No",
                                allowOutsideClick: false,
                            }).then((confirmResult) => {
                                if (confirmResult.isConfirmed) {
                                    fetch("../../Appointments/backend/update_appointment_status.php", {
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
                        }
                    });
                });
            });

            // ✅ Cancel Button with Validation Note Requirement
            document.querySelectorAll(".cancel-btn").forEach(button => {
                button.addEventListener("click", function () {
                    let appointmentId = this.getAttribute("data-id");

                    Swal.fire({
                        title: "Cancel Appointment",
                        input: "textarea",
                        inputPlaceholder: "Enter a reason for cancellation...",
                        showCancelButton: true,
                        confirmButtonText: "Confirm Cancel",
                        allowOutsideClick: false,
                        preConfirm: (note) => {
                            if (!note) {
                                Swal.showValidationMessage("A cancellation note is required.");
                                return false;
                            }
                            return note;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch("../../Appointments/backend/update_appointment_status.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({
                                    appointment_id: appointmentId,
                                    status: "cancelled", // ✅ Changed to lowercase to match DB validation
                                    validation_notes: result.value
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === "success") {
                                    Swal.fire({
                                        title: "Cancelled!",
                                        text: data.message,
                                        icon: "success",
                                        confirmButtonText: "Proceed to Rebooking"
                                    }).then(() => {
                                        // ✅ Redirect therapist to rebook page with patient_id
                                        window.location.href = "therapist/rebook_appointment.php";
                                    });
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

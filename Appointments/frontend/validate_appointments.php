<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins & Head Therapists Only
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// Fetch pending appointments with client name
$query = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.status, a.session_type, 
                 p.first_name, p.last_name, u.account_FName AS client_firstname, u.account_LName AS client_lastname 
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          WHERE a.status = 'Pending'
          ORDER BY a.date ASC, a.time ASC";
$result = $connection->query($query);
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// Fetch therapists (for therapist selection during approval)
$therapistQuery = "SELECT account_ID, account_FName, account_LName FROM users WHERE account_Type = 'therapist'";
$therapistResult = $connection->query($therapistQuery);
$therapists = $therapistResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validate Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Validate Appointments</h2>

        <table class="uk-table uk-table-striped">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Session Type</th>
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
                        <td>
                            <button class="uk-button uk-button-primary action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Approve">Approve</button>
                            <button class="uk-button uk-button-danger action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Decline">Decline</button>
                            <button class="uk-button uk-button-default action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Waitlist">Waitlist</button>
                            <button class="uk-button uk-button-secondary details-btn" data-id="<?= $appointment['appointment_id']; ?>">View More</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".action-btn").forEach(button => {
            button.addEventListener("click", function () {
                let appointmentId = this.getAttribute("data-id");
                let action = this.getAttribute("data-action");

                let statusMapping = {
                    "Approve": "Approved",
                    "Decline": "Declined",
                    "Waitlist": "Waitlisted"
                };

                let status = statusMapping[action]; // Convert action to valid status

                fetch(`../backend/get_appointment_details.php?appointment_id=${appointmentId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            let detailsHtml = `
                                <p><strong>Patient:</strong> ${data.details.patient_name}</p>
                                ${data.details.patient_picture ? `<img src="${data.details.patient_picture}" style="width: 100px; height: 100px; border-radius: 10px;">` : ""}
                                
                                <p><strong>Client:</strong> ${data.details.client_name}</p>
                                ${data.details.client_picture ? `<img src="${data.details.client_picture}" style="width: 100px; height: 100px; border-radius: 10px;">` : ""}
                                <p><strong>Date:</strong> ${data.details.date}</p>
                                <p><strong>Time:</strong> ${data.details.time}</p>
                                <p><strong>Session Type:</strong> ${data.details.session_type}</p>
                                <p><strong>Status:</strong> ${data.details.status}</p>
                                <p><strong>Doctor's Referral:</strong> ${data.details.doctor_referral}</p>
                            `;

                            if (action === "Approve") {
                                detailsHtml += `
                                    <label><strong>Assign a Therapist:</strong></label>
                                    <select id="therapistSelect" class="swal2-select">
                                        <option value="">Select a Therapist</option>
                                        ${data.therapists.length > 0 ? data.therapists.map(therapist => `
                                            <option value="${therapist.id}" ${therapist.availability === "Available" ? "selected" : ""}>
                                                ${therapist.name} - ${therapist.availability}
                                            </option>
                                        `).join('') : "<option disabled>No therapists available</option>"}
                                    </select>
                                `;
                            }

                            Swal.fire({
                                title: `${action} Appointment`,
                                html: detailsHtml,
                                showCancelButton: true,
                                confirmButtonText: action,
                                preConfirm: () => {
                                    if (action === "Approve") {
                                        let therapistId = document.getElementById("therapistSelect").value;
                                        if (!therapistId) {
                                            Swal.showValidationMessage("Please select a therapist");
                                            return false;
                                        }
                                        return { therapistId };
                                    }
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    let bodyData = `appointment_id=${appointmentId}&status=${status}`;
                                    if (action === "Approve") {
                                        bodyData += `&therapist_id=${result.value.therapistId}`;
                                    }

                                    fetch("../backend/update_appointment_status.php", {
                                        method: "POST",
                                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                        body: bodyData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.status === "success") {
                                            Swal.fire("Success!", data.message, "success").then(() => {
                                                location.reload();
                                            });
                                        } else {
                                            Swal.fire("Error!", data.message, "error");
                                        }
                                    })
                                    .catch(error => console.error("Error:", error));
                                }
                            });
                        } else {
                            Swal.fire("Error!", "Failed to retrieve appointment details.", "error");
                        }
                    })
                    .catch(error => console.error("Error fetching details:", error));
            });
        });

        // ✅ Handle View More Details
        document.querySelectorAll(".details-btn").forEach(button => {
            button.addEventListener("click", function () {
                let appointmentId = this.getAttribute("data-id");

                fetch(`../backend/get_appointment_details.php?appointment_id=${appointmentId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire({
                                title: "Appointment Details",
                                html: `
                                    <p><strong>Patient:</strong> ${data.details.patient_name}</p>
                                    <p><strong>Client:</strong> ${data.details.client_name}</p>
                                    <p><strong>Date:</strong> ${data.details.date}</p>
                                    <p><strong>Time:</strong> ${data.details.time}</p>
                                    <p><strong>Session Type:</strong> ${data.details.session_type}</p>
                                    <p><strong>Appointment Status:</strong> ${data.details.status}</p>
                                    <p><strong>Assigned Therapist:</strong> ${data.details.therapist}</p>
                                    <p><strong>Doctor's Referral:</strong> ${data.details.doctor_referral}</p>
                                `,
                                confirmButtonText: "Close"
                            });
                        } else {
                            Swal.fire("Error!", "Failed to retrieve appointment details.", "error");
                        }
                    })
                    .catch(error => console.error("Error fetching details:", error));
            });
        });
    });

    </script>
</body>
</html>

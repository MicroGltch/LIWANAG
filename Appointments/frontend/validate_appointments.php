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
                 p.first_name, p.last_name, p.profile_picture AS patient_picture,
                 u.account_FName AS client_firstname, u.account_LName AS client_lastname, u.profile_picture AS client_picture
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          WHERE a.status = 'Pending'
          ORDER BY a.date ASC, a.time ASC";
$result = $connection->query($query);
$appointments = $result->fetch_all(MYSQLI_ASSOC);
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
                        <td>
                            <img src="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../uploads/profile_pictures/default.png'; ?>"
                                alt="Patient Picture" class="uk-border-rounded" style="width: 40px; height: 40px; object-fit: cover;">
                            <?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?>
                        </td>
                        <td>
                            <img src="<?= !empty($appointment['client_picture']) ? '../../uploads/profile_pictures/' . $appointment['client_picture'] : '../../uploads/profile_pictures/default.png'; ?>"
                                alt="Client Picture" class="uk-border-rounded" style="width: 40px; height: 40px; object-fit: cover;">
                            <?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?>
                        </td>
                        <td><?= htmlspecialchars($appointment['date']); ?></td>
                        <td><?= htmlspecialchars($appointment['time']); ?></td>
                        <td><?= htmlspecialchars($appointment['session_type']); ?></td>
                        <td>
                            <button class="uk-button uk-button-primary action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Approve">Approve</button>
                            <button class="uk-button uk-button-danger action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Decline">Decline</button>
                            <button class="uk-button uk-button-default action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Waitlist">Waitlist</button>
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

            let status = statusMapping[action];

            fetch(`../backend/get_appointment_details.php?appointment_id=${appointmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        let detailsHtml = `
                            <p><strong>Patient:</strong> ${data.details.patient_name}</p>
                            <img src="${data.details.patient_picture}" style="width: 100px; height: 100px; border-radius: 10px; object-fit: cover;">
                            
                            <p><strong>Client:</strong> ${data.details.client_name}</p>
                            <img src="${data.details.client_picture}" style="width: 100px; height: 100px; border-radius: 10px; object-fit: cover;">
                            
                            <p><strong>Date:</strong> ${data.details.date}</p>
                            <p><strong>Time:</strong> ${data.details.time}</p>
                            <p><strong>Session Type:</strong> ${data.details.session_type}</p>
                            <p><strong>Status:</strong> ${data.details.status}</p>
                        `;


                        if (action === "Approve") {
                            fetch(`../backend/get_available_therapists.php?date=${data.details.date}&time=${data.details.time}`)
                                .then(response => response.json())
                                .then(therapistsData => {
                                    if (therapistsData.status === "success") {
                                        let therapistOptions = therapistsData.therapists.length > 0
                                        ? therapistsData.therapists.map(t => {
                                            let statusLabel = t.status === "Available" ? "" : `[${t.status}]`;
                                            return `<option value="${t.id}">
                                                        ${t.name} - ${statusLabel} ${t.schedule}
                                                    </option>`;
                                        }).join('')
                                        : "<option disabled>No therapists available</option>";



                                        Swal.fire({
                                            title: "Assign a Therapist",
                                            html: detailsHtml + `
                                                <label><strong>Select Therapist:</strong></label>
                                                <select id="therapistSelect" class="swal2-select">
                                                    <option value="">Select a Therapist</option>
                                                    ${therapistOptions}
                                                </select>
                                            `,
                                            showCancelButton: true,
                                            confirmButtonText: "Approve",
                                            preConfirm: () => {
                                                let therapistId = document.getElementById("therapistSelect").value;
                                                if (!therapistId) {
                                                    Swal.showValidationMessage("Please select a therapist");
                                                    return false;
                                                }
                                                return { therapistId };
                                            }
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                fetch("../backend/update_appointment_status.php", {
                                                    method: "POST",
                                                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                                    body: `appointment_id=${appointmentId}&status=Approved&therapist_id=${result.value.therapistId}`
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
                                        Swal.fire("Error!", "Failed to retrieve therapist availability.", "error");
                                    }
                                })
                                .catch(error => console.error("Error fetching therapists:", error));
                        } else {
                            Swal.fire({
                                title: `Are you sure you want to ${action.toLowerCase()} this appointment?`,
                                html: detailsHtml,
                                icon: "warning",
                                showCancelButton: true,
                                confirmButtonText: `Yes, ${action}`
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    fetch("../backend/update_appointment_status.php", {
                                        method: "POST",
                                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                        body: `appointment_id=${appointmentId}&status=${status}`
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
                        }
                    } else {
                        Swal.fire("Error!", "Failed to retrieve appointment details.", "error");
                    }
                })
                .catch(error => console.error("Error fetching appointment details:", error));
        });
    });
});
</script>

</body>
</html>

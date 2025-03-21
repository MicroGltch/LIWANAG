<?php
require_once "../../dbconfig.php";
session_start();

//✅ Restrict Access to Admins & Head Therapists Only
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// ✅ Fetch appointments with referral information from `doctor_referrals`
$query = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.status, 
                 CASE 
                     WHEN a.session_type = 'Rebooking' AND t.account_FName IS NOT NULL 
                     THEN CONCAT('Rebooking by: ', t.account_FName, ' ', t.account_LName) 
                     ELSE a.session_type 
                 END AS session_type,
                 dr.referral_type, -- ✅ Fetch referral type
                 dr.official_referral_file, -- ✅ Fetch official referral file
                 dr.proof_of_booking_referral_file, -- ✅ Fetch proof of booking file
                 p.first_name, p.last_name, p.profile_picture AS patient_picture,
                 u.account_FName AS client_firstname, u.account_LName AS client_lastname, u.profile_picture AS client_picture
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          LEFT JOIN users t ON a.rebooked_by = t.account_ID
          LEFT JOIN doctor_referrals dr ON a.referral_id = dr.referral_id -- ✅ Join doctor_referrals table
          WHERE a.status = 'Pending'
          ORDER BY a.date ASC, a.time ASC";

$result = $connection->query($query);
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Query to get waitlisted appointments
$waitlistQuery = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.session_type,
                         p.first_name, p.last_name,
                         u.account_FName AS client_firstname, u.account_LName AS client_lastname 
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.patient_id
                  JOIN users u ON a.account_id = u.account_ID
                  WHERE a.status = 'waitlisted'
                  ORDER BY a.date ASC, a.time ASC";
$waitlistedAppointments = $connection->query($waitlistQuery)->fetch_all(MYSQLI_ASSOC);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validate Appointments</title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css" />
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
                    <th>Doctors Referral</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td>
                            <img src="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../uploads/profile_pictures/default.png'; ?>"
                                onerror="this.style.display='none';"
                                alt="Patient Picture" class="uk-border-rounded" style="width: 40px; height: 40px; object-fit: cover;">
                            <?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?>
                        </td>
                        <td>
                            <img src="<?= !empty($appointment['client_picture']) ? '../../uploads/profile_pictures/' . $appointment['client_picture'] : '../../uploads/profile_pictures/default.png'; ?>"
                                onerror="this.style.display='none';"
                                alt="Client Picture" class="uk-border-rounded" style="width: 40px; height: 40px; object-fit: cover;">
                            <?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?>
                        </td>
                        <td><?= htmlspecialchars($appointment['date']); ?></td>
                        <td><?= htmlspecialchars($appointment['time']); ?></td>
                        <td><?= htmlspecialchars($appointment['session_type']); ?></td>

                        <td>
                            <?php if (!empty($appointment['official_referral_file'])): ?>
                                <!-- ✅ Show Official Referral (Priority) -->
                                <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['official_referral_file']); ?>" 
                                target="_blank" class="uk-button uk-button-secondary">
                                    View Official Referral
                                </a>
                            <?php elseif (!empty($appointment['proof_of_booking_referral_file'])): ?>
                                <!-- ✅ Show Proof of Booking ONLY if no Official Referral exists -->
                                <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['proof_of_booking_referral_file']); ?>" 
                                target="_blank" class="uk-button uk-button-warning">
                                    View Proof of Booking
                                </a>
                            <?php else: ?>
                                <!-- ✅ No referral available -->
                                <span class="uk-text-muted">Not Applicable</span>
                            <?php endif; ?>
                        </td>




                        <td>
                            <button class="uk-button uk-button-primary action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Approve"
                                data-patient-img="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../uploads/profile_pictures/default.png'; ?>">Approve</button>
                            
                            <button class="uk-button uk-button-danger action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Decline">Decline</button>
                            
                            <?php if (strpos($appointment['session_type'], 'Rebooking') === false): ?>
                                <button class="uk-button uk-button-default action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Waitlist">Waitlist</button>
                            <?php endif; ?>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        
       

    </div>

    <div> <!-- ✅ Waitlisted Appointments Area -->
        <h3>Waitlisted Appointments</h3>
        <table class="uk-table uk-table-striped">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Client</th>
                    <th>Original Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($waitlistedAppointments as $appointment): ?>
                    <tr>
                        <td><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                        <td><?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?></td>
                        <td><?= htmlspecialchars($appointment['date']); ?> (Waitlisted)</td>
                        <td>
                            <?php if (strtolower($appointment['session_type']) === 'playgroup'): ?>
                                <button class="uk-button uk-button-primary assign-playgroup-btn" data-id="<?= $appointment['appointment_id']; ?>">
                                    Assign Playgroup Slot
                                </button>
                            <?php else: ?>
                                <button class="uk-button uk-button-primary assign-btn" data-id="<?= $appointment['appointment_id']; ?>">
                                    Assign Date, Time & Therapist
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

    <!-- Recheck Dashboard Redirect -->
    <a href="../../Dashboards/headtherapistdashboard.php">Back to Dashboard</a></li>
</body>
</html>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".action-btn").forEach(button => {
        button.addEventListener("click", function () {
            let appointmentId = this.getAttribute("data-id");
            let action = this.getAttribute("data-action");

            let statusMapping = {
                "Approve": "approved",
                "Decline": "declined",
                "Waitlist": "waitlisted"
            };

            let status = statusMapping[action];

            fetch(`../app_data/get_appointment_details.php?appointment_id=${appointmentId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok.");
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status !== "success") {
                        Swal.fire("Error", "Failed to fetch appointment details.", "error");
                        return;
                    }

                    let detailsHtml = `
                        <p><strong>Patient:</strong> ${data.details.patient_name}</p>
                        <p><strong>Client:</strong> ${data.details.client_name}</p>
                        <p><strong>Date:</strong> ${data.details.date}</p>
                        <p><strong>Time:</strong> ${data.details.time}</p>
                        <p><strong>Session Type:</strong> ${data.details.session_type}</p>
                    `;

                    // ✅ Show "Rebooked By" if session type contains "Rebooking"
                    if (data.details.session_type.includes("rebooking") && data.details.rebooked_by_name) {
                        detailsHtml += `<p><strong>Rebooked By:</strong> ${data.details.rebooked_by_name}</p>`;
                    }

                    detailsHtml += `<p><strong>Status:</strong> ${data.details.status}</p>`;


                    if (action === "Approve") {
                        if (data.details.session_type === "playgroup") {
                            fetch("../app_data/get_open_playgroup_sessions.php")
                                .then(response => response.json())
                                .then(slotData => {
                                    if (slotData.status !== "success" || !slotData.sessions || slotData.sessions.length === 0) {
                                        Swal.fire("No Available Sessions", "No open Playgroup sessions are available. Please create one first on the <a href='playgroup_dashboard.php'>Playgroup Dashboard</a>.", "warning");
                                        return;
                                    }

                                    let slotOptions = slotData.sessions.map(slot => `
                                        <option value="${slot.pg_session_id}">
                                            ${slot.date} at ${slot.time} (${slot.current_count}/${slot.max_capacity})
                                        </option>
                                    `).join('');
                                    Swal.fire({
                                        title: "Select a Playgroup Slot",
                                        html: detailsHtml + `
                                            <label>Select a Playgroup Slot:</label>
                                            <select id="playgroupSlotSelect" class="uk-select" required onchange="updatePGSlotDetails(this)">
                                                <option value="">Select a Slot</option>
                                                ${slotOptions}
                                            </select>
                                        `,
                                        showCancelButton: true,
                                        confirmButtonText: "Approve",
                                        preConfirm: () => {
                                            let selectedSlot = document.getElementById("playgroupSlotSelect").value;
                                            if (!selectedSlot) {
                                                Swal.showValidationMessage("Please select a Playgroup slot");
                                                return false;
                                            }
                                            return { selectedSlot };
                                        }
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            fetch("../app_process/update_appointment_status.php", {
                                                method: "POST",
                                                headers: { "Content-Type": "application/json" },
                                                body: JSON.stringify({
                                                    appointment_id: appointmentId,
                                                    status: "approved",
                                                    pg_session_id: result.value.selectedSlot
                                                })
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.status === "success") {
                                                    Swal.fire("Success!", data.message, "success").then(() => location.reload());
                                                } else {
                                                    Swal.fire("Error", data.message, "error");
                                                }
                                            })
                                            .catch(error => {
                                                Swal.fire("Error", "Failed to update appointment.", "error");
                                            });
                                        }
                                    });
                                })
                                .catch(error => {
                                    Swal.fire("Error", "Failed to fetch available slots.", "error");
                                });
                        } else {
                            // ✅ Standard approval process (requires therapist)
                        fetch(`../app_data/get_available_therapists.php?date=${data.details.date}&time=${data.details.time}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error("Failed to fetch available therapists.");
                                }
                                return response.json();
                            })
                            .then(therapistsData => {
                                if (therapistsData.status !== "success") {
                                    Swal.fire("Error", "No therapists available.", "error");
                                    return;
                                }

                                let therapistOptions = therapistsData.therapists.map(t => `
                                    <option value="${t.id}">${t.name} - [${t.status}] ${t.schedule}</option>
                                `).join('');

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
                                        fetch("../app_process/update_appointment_status.php", {
                                            method: "POST",
                                            headers: { "Content-Type": "application/json" },  // ✅ Change to JSON
                                            body: JSON.stringify({
                                                appointment_id: appointmentId,
                                                status: "approved",
                                                therapist_id: result.value.therapistId
                                            })
                                        })

                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.status === "success") {
                                                Swal.fire("Success!", data.message, "success").then(() => location.reload());
                                            } else {
                                                Swal.fire("Error", "Failed to approve appointment.", "error");
                                            }
                                        })
                                        .catch(error => {
                                            Swal.fire("Error", "Failed to update appointment.", "error");
                                        });
                                    }
                                });
                            })
                            .catch(error => {
                                Swal.fire("Error", "Failed to fetch therapists.", "error");
                            });
                        }
                    } else if (action === "Decline") {
                        Swal.fire({
                            title: "Provide a Decline Reason",
                            html: detailsHtml + `
                                <label><strong>Reason for Declining:</strong></label>
                                <textarea id="declineReason" class="swal2-textarea" placeholder="Enter decline reason"></textarea>
                            `,
                            showCancelButton: true,
                            confirmButtonText: "Confirm Decline",
                            preConfirm: () => {
                                let reason = document.getElementById("declineReason").value.trim();
                                if (!reason) {
                                    Swal.showValidationMessage("Please provide a reason for declining.");
                                    return false;
                                }
                                return { reason };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch("../app_process/update_appointment_status.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                body: `appointment_id=${appointmentId}&status=declined&validation_notes=${encodeURIComponent(result.value.reason)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                console.log("Debug Response:", data); // Log response to console
                                if (data.status === "success") {
                                    Swal.fire("Declined!", data.message, "success").then(() => location.reload());
                                } else {
                                    Swal.fire("Error", "Failed to decline appointment.", "error");
                                }
                            })
                            .catch(error => {
                                console.error("Fetch Error:", error);
                                Swal.fire("Error", "Failed to update appointment.", "error");
                            });
                            }
                        });
                    } else if (action === "Waitlist") {
                        Swal.fire({
                            title: "Waitlist Appointment",
                            html: detailsHtml,
                            input: "textarea",
                            inputPlaceholder: "Enter a reason for waitlisting...",
                            showCancelButton: true,
                            confirmButtonText: "Confirm Waitlist",
                            allowOutsideClick: false,
                            preConfirm: (note) => {
                                if (!note) {
                                    Swal.showValidationMessage("A reason is required.");
                                    return false;
                                }
                                return note;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch("../app_process/update_appointment_status.php", {
                                    method: "POST",
                                    headers: { "Content-Type": "application/json" },
                                    body: JSON.stringify({
                                        appointment_id: appointmentId,
                                        status: "waitlisted",
                                        validation_notes: result.value
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === "success") {
                                        Swal.fire("Waitlisted!", data.message, "success").then(() => location.reload());
                                    } else {
                                        Swal.fire("Error!", "Failed to waitlist appointment.", "error");
                                    }
                                })
                                .catch(error => console.error("Error:", error));
                            }
                        });

                    } else {
                        // 🔹 Handle invalid selection
                        Swal.fire({
                            title: "Error",
                            text: "Invalid selection or action is not recognized.",
                            icon: "error"
                        });
                    }
                })
                .catch(error => {
                    Swal.fire("Error", "Failed to fetch appointment details.", "error");
                });
        });
    });
});


//waitlisted js add ${t.schedule} if want to fetch the time for that date too
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".assign-btn").forEach(button => {
        button.addEventListener("click", function () {
            let appointmentId = this.getAttribute("data-id");

            Swal.fire({
                title: "Reschedule Appointment",
                html: `
                    <label>New Date:</label>
                    <input type="date" id="appointmentDate" class="swal2-input">
                    <label>New Time:</label>
                    <input type="time" id="appointmentTime" class="swal2-input">
                    <label>Assign Therapist:</label>
                    <select id="therapistSelect" class="swal2-select" disabled>
                        <option value="">Select a Date & Time First</option>
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: "Assign",
                preConfirm: () => {
                    let date = document.getElementById("appointmentDate").value;
                    let time = document.getElementById("appointmentTime").value;
                    let therapistId = document.getElementById("therapistSelect").value;

                    if (!date || !time || !therapistId) {
                        Swal.showValidationMessage("Please select a valid date, time, and therapist.");
                        return false;
                    }

                    return { date, time, therapistId };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("../app_process/update_appointment_status.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            appointment_id: appointmentId,
                            status: "approved",
                            date: result.value.date,
                            time: result.value.time,
                            therapist_id: result.value.therapistId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire("Assigned!", "Appointment has been rescheduled and therapist assigned.", "success")
                                .then(() => location.reload()); // ✅ Reload to reflect changes
                        } else {
                            Swal.fire("Error!", "Failed to update appointment.", "error");
                        }
                    })
                    .catch(error => Swal.fire("Error", "Failed to send update request.", "error"));
                }
            });

            // ✅ Load therapists dynamically after selecting a date & time
            document.getElementById("appointmentDate").addEventListener("change", fetchTherapists);
            document.getElementById("appointmentTime").addEventListener("change", fetchTherapists);

            function fetchTherapists() {
                let date = document.getElementById("appointmentDate").value;
                let time = document.getElementById("appointmentTime").value;
                let therapistDropdown = document.getElementById("therapistSelect");

                if (!date || !time) {
                    therapistDropdown.innerHTML = `<option value="">Select a Date & Time First</option>`;
                    therapistDropdown.disabled = true;
                    return;
                }

                fetch(`../app_data/get_available_therapists.php?date=${date}&time=${time}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== "success" || data.therapists.length === 0) {
                            therapistDropdown.innerHTML = `<option value="">No Available Therapists</option>`;
                            therapistDropdown.disabled = true;
                            return;
                        }

                        therapistDropdown.innerHTML = data.therapists.map(t => `
                            <option value="${t.id}">${t.name}</option>
                        `).join('');

                        therapistDropdown.disabled = false;
                    })
                    .catch(error => {
                        therapistDropdown.innerHTML = `<option value="">Error Fetching Therapists</option>`;
                        therapistDropdown.disabled = true;
                    });
            }
        });
    });
});

function updatePGSlotDetails(select) {
    const selected = select.options[select.selectedIndex];
    const match = selected.textContent.match(/(\d{2}:\d{2}:\d{2})/);
    const sessionId = selected.value;

    fetch(`../app_data/get_playgroup_session_details.php?pg_session_id=${sessionId}`)
        .then(response => response.json());
}


//waitlisted playgroup button
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".assign-playgroup-btn").forEach(button => {
        button.addEventListener("click", function () {
            let appointmentId = this.getAttribute("data-id");

            fetch("../app_data/get_open_playgroup_sessions.php")
                .then(response => response.json())
                .then(data => {
                    if (data.status !== "success" || !data.sessions || data.sessions.length === 0) {
                        Swal.fire("No Sessions", "No open Playgroup sessions are available.", "warning");
                        return;
                    }

                    let options = data.sessions.map(session => `
                        <option value="${session.pg_session_id}">
                            ${session.date} at ${session.time} (${session.current_count}/${session.max_capacity})
                        </option>
                    `).join('');

                    Swal.fire({
                        title: "Assign to Playgroup Slot",
                        html: `
                            <label>Select a Playgroup Slot:</label>
                            <select id="pgSlotSelect" class="uk-select">
                                <option value="">Select a slot</option>
                                ${options}
                            </select>
                        `,
                        showCancelButton: true,
                        confirmButtonText: "Assign",
                        preConfirm: () => {
                            const selected = document.getElementById("pgSlotSelect").value;
                            if (!selected) {
                                Swal.showValidationMessage("Please select a Playgroup slot.");
                                return false;
                            }
                            return { pg_session_id: selected };
                        }
                    }).then(result => {
                        if (result.isConfirmed) {
                            fetch("../app_process/update_appointment_status.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({
                                    appointment_id: appointmentId,
                                    status: "approved",
                                    pg_session_id: result.value.pg_session_id
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === "success") {
                                    Swal.fire("Success", data.message, "success").then(() => location.reload());
                                } else {
                                    Swal.fire("Error", data.message || "Failed to assign Playgroup slot.", "error");
                                }
                            })
                            .catch(() => {
                                Swal.fire("Error", "Server error while assigning slot.", "error");
                            });
                        }
                    });
                })
                .catch(() => {
                    Swal.fire("Error", "Failed to fetch Playgroup sessions.", "error");
                });
        });
    });
});


</script>


</body>
</html>

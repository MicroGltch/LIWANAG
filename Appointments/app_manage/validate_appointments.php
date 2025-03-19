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
$waitlistQuery = "SELECT a.appointment_id, a.patient_id, a.date, a.time, 
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

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css">
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!--SWAL-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        html,
        body {
            background-color: #ffffff !important;
        }

        .action-btn {
            width: 120px;
            /* Set a fixed width for consistency */
            text-align: center;
            /* Center the text */
            margin: 0 auto;
            /* Center the button within the container */
            border-radius: 8px;
            /* Make the buttons rounded */
        }

        .uk-button-secondary,
        .uk-button-warning {
            border-radius: 8px;
            padding: 0 15px;
            min-width: 160px;
            margin: 5px 0;
        }

        .uk-button-secondary:hover,
        .uk-button-warning:hover {
            transform: translateY(-1px);
            transition: transform 0.2s;
        }

        /* Updated action button styles */
        .action-btn {
            width: 120px;
            text-align: center;
            margin: 5px 0;
            border-radius: 8px;
            display: block;
        }

        td .action-btn:first-child {
            margin-top: 0;
        }

        td .action-btn:last-child {
            margin-bottom: 0;
        }

        /* Keep existing referral button styles */
        .uk-button-secondary,
        .uk-button-warning {
            border-radius: 8px;
            padding: 0 15px;
            min-width: 160px;
            margin: 5px 0;
        }

        .uk-button-secondary:hover,
        .uk-button-warning:hover {
            transform: translateY(-1px);
            transition: transform 0.2s;
        }
    </style>
</head>

<body>
    <!-- Main Content -->

    <!-- Breadcrumb -->
    <ul class="uk-breadcrumb">
        <li><a href="manage_appointments.php">Manage Appointments</a></li>
        <li><span>Validate Appointments</span></li>
    </ul>

    <h1 class="uk-text-bold">Validate Appointments</h1>

    <!-- Pending Appointments Table -->
    <div class="uk-overflow-auto">
        <table id="pendingAppointmentsTable" class="uk-table uk-table-striped uk-table-middle uk-table-responsive">
            <thead>
                <tr>
                    <th>Picture</span></th>
                    <th>Patient <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Client <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Date <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Time <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Session Type <span uk-icon="icon: arrow-down-arrow-up"></span></th>
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
                        </td>
                        <td><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                        <td><?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?></td>
                        <td><?= htmlspecialchars($appointment['date']); ?></td>
                        <td><?= htmlspecialchars($appointment['time']); ?></td>
                        <td><?= htmlspecialchars($appointment['session_type']); ?></td>
                        <td>
                            <?php if (!empty($appointment['official_referral_file'])): ?>
                                <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['official_referral_file']); ?>"
                                    target="_blank" class="uk-button uk-button-secondary">
                                    View Official Referral
                                </a>
                            <?php elseif (!empty($appointment['proof_of_booking_referral_file'])): ?>
                                <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['proof_of_booking_referral_file']); ?>"
                                    target="_blank" class="uk-button uk-button-secondary">
                                    View Proof of Booking
                                </a>
                            <?php else: ?>
                                <span class="uk-text-muted">Not Applicable</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="action-btn uk-button uk-button-primary" data-id="<?= $appointment['appointment_id']; ?>" data-action="Approve"
                                data-patient-img="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../uploads/profile_pictures/default.png'; ?>">Approve</button>

                            <button class="action-btn uk-button uk-button-danger" data-id="<?= $appointment['appointment_id']; ?>" data-action="Decline">Decline</button>

                            <?php if (strpos($appointment['session_type'], 'Rebooking') === false): ?>
                                <button class="action-btn uk-button uk-button-default" data-id="<?= $appointment['appointment_id']; ?>" data-action="Waitlist">Waitlist</button>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Waitlisted Appointments Table -->
    <div class="uk-margin-large-top">
        <h2 class="uk-text-bold">Waitlisted Appointments</h2>
        <div class="uk-overflow-auto">
            <table id="waitlistedAppointmentsTable" class="uk-table uk-table-striped uk-table-middle uk-table-responsive">
                <thead>
                    <tr>
                        <th>Patient <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                        <th>Client <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                        <th>Original Date <span uk-icon="icon: arrow-down-arrow-up"></span></th>
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
                                <button class="uk-button uk-button-primary assign-btn" data-id="<?= $appointment['appointment_id']; ?>">
                                    Assign Date, Time & Therapist
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#pendingAppointmentsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50],
                order: [
                    [3, 'asc']
                ], // Sort by date column (now index 3) by default
                language: {
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    search: "Search:",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                columnDefs: [{
                        orderable: false,
                        targets: [0] // Disable sorting for picture column
                    },
                    {
                        orderable: true,
                        targets: [1, 2, 3, 4, 5] // Make name, client, date, time, and session type columns sortable
                    },
                    {
                        orderable: false,
                        targets: [6, 7] // Disable sorting for Doctors Referral and Actions columns
                    },
                    {
                        type: 'date',
                        targets: 3 // Specify date type for date column (now index 3)
                    }
                ]
            });

            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".action-btn").forEach(button => {
                    button.addEventListener("click", function() {
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
                                <p><strong>Status:</strong> ${data.details.status}</p>
                            `;

                                if (action === "Approve") {
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
                                                    return {
                                                        therapistId
                                                    };
                                                }
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    fetch("../app_process/update_appointment_status.php", {
                                                            method: "POST",
                                                            headers: {
                                                                "Content-Type": "application/json"
                                                            }, // ✅ Change to JSON
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
                                            return {
                                                reason
                                            };
                                        }
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            fetch("../app_process/update_appointment_status.php", {
                                                    method: "POST",
                                                    headers: {
                                                        "Content-Type": "application/x-www-form-urlencoded"
                                                    },
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
                                                    headers: {
                                                        "Content-Type": "application/json"
                                                    },
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

            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".assign-btn").forEach(button => {
                    button.addEventListener("click", function() {
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

                                return {
                                    date,
                                    time,
                                    therapistId
                                };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                fetch("../app_process/update_appointment_status.php", {
                                        method: "POST",
                                        headers: {
                                            "Content-Type": "application/json"
                                        },
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
        });
    </script>


</body>

</html>
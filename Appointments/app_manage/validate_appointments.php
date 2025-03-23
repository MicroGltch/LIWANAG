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
                 a.created_at, -- ✅ Include created_at
                 dr.referral_type,
                 dr.official_referral_file,
                 dr.proof_of_booking_referral_file,
                 p.first_name, p.last_name, p.profile_picture AS patient_picture,
                 u.account_FName AS client_firstname, u.account_LName AS client_lastname, u.profile_picture AS client_picture
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          JOIN users u ON a.account_id = u.account_ID
          LEFT JOIN users t ON a.rebooked_by = t.account_ID
          LEFT JOIN doctor_referrals dr ON a.referral_id = dr.referral_id
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
                    <th>Patient <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Client <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Date <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Time <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                    <th>Booked On <span uk-icon="icon: arrow-down-arrow-up"></span></th>
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
                            <?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?>
                        </td>
                        <td>
                            <img src="<?= !empty($appointment['client_picture']) ? '../../uploads/profile_pictures/' . $appointment['client_picture'] : '../../uploads/profile_pictures/default.png'; ?>"
                                onerror="this.style.display='none';"
                                alt="Client Picture" class="uk-border-rounded" style="width: 40px; height: 40px; object-fit: cover;">
                            <?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?>
                        </td>
                        <td><?= htmlspecialchars(date("M d, Y", strtotime($appointment['date']))); ?></td>
                        <td><?= htmlspecialchars(date("h:i A", strtotime($appointment['time']))); ?></td>
                        <td><?= htmlspecialchars(date("M d, Y h:i A", strtotime($appointment['created_at']))); ?></td>
                        <td><?= htmlspecialchars(ucwords(strtolower($appointment['session_type']))); ?></td>

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
                            <td><?= htmlspecialchars(date("M d, Y", strtotime($appointment['date']))); ?> (Waitlisted)</td>
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
    </div>



<!-- Remodified/combined script -->
 <script>
    $(document).ready(function() {
    console.log("jQuery version:", $.fn.jquery);
    
    // Add error handling
    window.onerror = function(message, source, lineno, colno, error) {
        console.error("JS Error:", message, "at", source, ":", lineno);
        return false;
    };

    // Initialize DataTables for pending appointments
    $('#pendingAppointmentsTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        order: [[3, 'asc']], // Sort by date column by default
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
        columnDefs: [
            { orderable: false, targets: [0] }, 
            { orderable: true, targets: [1, 2, 3, 4, 5] }, 
            { orderable: false, targets: [6] },
            { type: 'date', targets: 3 }
        ],
        initComplete: function(settings, json) {
            console.log("Pending appointments table initialized");
        }
    });
    
    // Initialize DataTables for waitlisted appointments if present
    if ($('#waitlistedAppointmentsTable').length > 0) {
        $('#waitlistedAppointmentsTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            language: {
                lengthMenu: "Show _MENU_ entries per page",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                search: "Search:"
            },
            initComplete: function(settings, json) {
                console.log("Waitlisted appointments table initialized");
            }
        });
    }
    
    // Use event delegation for action buttons (Approve, Decline, Waitlist)
    $(document).on('click', '.action-btn', function() {
        console.log("Action button clicked!");
        let appointmentId = $(this).attr('data-id');
        let action = $(this).attr('data-action');
        console.log(`Action: ${action}, Appointment ID: ${appointmentId}`);
        
        let statusMapping = {
            "Approve": "approved",
            "Decline": "declined",
            "Waitlist": "waitlisted"
        };
        
        let status = statusMapping[action];
        
        // Fetch appointment details
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
                
                // Show "Rebooked By" if session type contains "Rebooking"
                if (data.details.session_type?.includes("rebooking") && data.details.rebooked_by_name) {
                    detailsHtml += `<p><strong>Rebooked By:</strong> ${data.details.rebooked_by_name}</p>`;
                }
                
                detailsHtml += `<p><strong>Status:</strong> ${data.details.status}</p>`;

                if (action === "Approve") {
                    if (data.details.session_type === "playgroup") {
                        handlePlaygroupApproval(appointmentId, data, detailsHtml);
                    } else {
                        handleApproveAction(appointmentId, data, detailsHtml);
                    }
                } else if (action === "Decline") {
                    handleDeclineAction(appointmentId, detailsHtml);
                } else if (action === "Waitlist") {
                    handleWaitlistAction(appointmentId, detailsHtml);
                } else {
                    Swal.fire({
                        title: "Error",
                        text: "Invalid selection or action is not recognized.",
                        icon: "error"
                    });
                }
            })
            .catch(error => {
                console.error("Error fetching appointment details:", error);
                Swal.fire("Error", "Failed to fetch appointment details.", "error");
            });
    });
    
    // Use event delegation for assign buttons
    $(document).on('click', '.assign-btn', function() {
        console.log("Assign button clicked!");
        let appointmentId = $(this).attr('data-id');
        console.log(`Assign button ID: ${appointmentId}`);
        
        handleAssignAction(appointmentId);
    });
    
    // Use event delegation for assign playgroup buttons
    $(document).on('click', '.assign-playgroup-btn', function() {
        console.log("Assign playgroup button clicked!");
        let appointmentId = $(this).attr('data-id');
        console.log(`Assign playgroup button ID: ${appointmentId}`);
        
        handleAssignPlaygroupAction(appointmentId);
    });
});

// Handle Playgroup approval
function handlePlaygroupApproval(appointmentId, data, detailsHtml) {
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
                    updateAppointmentStatus({
                        appointment_id: appointmentId,
                        status: "approved",
                        pg_session_id: result.value.selectedSlot
                    }, "Playgroup slot assigned successfully", "Failed to assign playgroup slot");
                }
            });
        })
        .catch(error => {
            console.error("Error fetching playgroup slots:", error);
            Swal.fire("Error", "Failed to fetch available slots.", "error");
        });
}

// Handle the Approve action
function handleApproveAction(appointmentId, data, detailsHtml) {
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
                    updateAppointmentStatus({
                        appointment_id: appointmentId,
                        status: "approved",
                        therapist_id: result.value.therapistId
                    }, "Approval successful", "Failed to approve appointment");
                }
            });
        })
        .catch(error => {
            console.error("Error fetching therapists:", error);
            Swal.fire("Error", "Failed to fetch therapists.", "error");
        });
}

// Handle the Decline action
function handleDeclineAction(appointmentId, detailsHtml) {
    Swal.fire({
        title: "Decline Appointment?",
        html: detailsHtml + `
            <label><strong>Reason for Declining:</strong></label>
            <select id="declineReasonSelect" class="swal2-select">
                <option value="">Select a reason (optional)</option>
                <option value="Fully booked">Fully booked</option>
                <option value="Client requested cancellation">Client requested cancellation</option>
                <option value="Therapist unavailable">Therapist unavailable</option>
                <option value="Other">Other (type below)</option>
            </select>
            <textarea id="declineReason" class="swal2-textarea" placeholder="Enter reason (optional)" style="display:none;"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: "Confirm Decline",
        didOpen: () => {
            const reasonSelect = document.getElementById("declineReasonSelect");
            const reasonInput = document.getElementById("declineReason");

            reasonSelect.addEventListener("change", function() {
                if (this.value === "Other") {
                    reasonInput.style.display = "block";
                } else {
                    reasonInput.style.display = "none";
                    reasonInput.value = this.value; // Set the selected option as the reason
                }
            });
        },
        preConfirm: () => {
            let reason = document.getElementById("declineReason").value.trim();
            return { reason }; // Send reason even if blank
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateAppointmentStatus({
                appointment_id: appointmentId,
                status: "declined",
                validation_notes: result.value.reason || "No reason provided"
            }, "Appointment declined", "Failed to decline appointment");
        }
    });
}


// Handle the Waitlist action
function handleWaitlistAction(appointmentId, detailsHtml) {
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
            updateAppointmentStatus({
                appointment_id: appointmentId,
                status: "waitlisted",
                validation_notes: result.value
            }, "Appointment waitlisted", "Failed to waitlist appointment");
        }
    });
}

// Handle the Assign action for waitlisted appointments
function handleAssignAction(appointmentId) {
    // Fetch timetable settings
    fetch("../app_data/get_timetable_settings.php")
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                let settings = data.settings;
                let blockedDates = settings.blocked_dates || [];
                let minDays = Number(settings.min_days_advance);
                let maxDays = Number(settings.max_days_advance);

                let minDate = new Date();
                let maxDate = new Date();
                minDate.setDate(minDate.getDate() + minDays);
                maxDate.setDate(maxDate.getDate() + maxDays);

                Swal.fire({
                    title: "Reschedule Appointment",
                    html: `
                        <label>New Date:</label>
                        <input type="date" id="appointmentDate" class="swal2-input">
                        <label>New Time:</label>
                        <select id="appointmentTime" class="swal2-select">
                            <option value="">Select a Date First</option>
                        </select>
                        <label>Assign Therapist:</label>
                        <select id="therapistSelect" class="swal2-select" disabled>
                            <option value="">Select a Date & Time First</option>
                        </select>
                    `,
                    showCancelButton: true,
                    confirmButtonText: "Assign",
                    didOpen: () => {
                        setupDateTimeFields(settings, blockedDates);
                    },
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
                        updateAppointmentStatus({
                            appointment_id: appointmentId,
                            status: "approved",
                            date: result.value.date,
                            time: result.value.time,
                            therapist_id: result.value.therapistId
                        }, "Appointment has been rescheduled and therapist assigned", "Failed to update appointment");
                    }
                });
            } else {
                Swal.fire("Error!", "Could not fetch timetable settings.", "error");
            }
        })
        .catch(error => {
            console.error("Error fetching timetable settings:", error);
            Swal.fire("Error!", "Error fetching settings.", "error");
        });
}

// Handle the Assign Playgroup action for waitlisted playgroup appointments
function handleAssignPlaygroupAction(appointmentId) {
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
                    updateAppointmentStatus({
                        appointment_id: appointmentId,
                        status: "approved",
                        pg_session_id: result.value.pg_session_id
                    }, "Successfully assigned to playgroup slot", "Failed to assign Playgroup slot");
                }
            });
        })
        .catch(() => {
            Swal.fire("Error", "Failed to fetch Playgroup sessions.", "error");
        });
}

// Setup date and time fields for appointment rescheduling
function setupDateTimeFields(settings, blockedDates) {
    let minDate = new Date();
    let maxDate = new Date();
    minDate.setDate(minDate.getDate() + Number(settings.min_days_advance));
    maxDate.setDate(maxDate.getDate() + Number(settings.max_days_advance));
    
    // Set Date Picker Restrictions
    let datePicker = document.getElementById("appointmentDate");
    datePicker.min = minDate.toISOString().split('T')[0];
    datePicker.max = maxDate.toISOString().split('T')[0];

    datePicker.addEventListener("change", function() {
        let selectedDate = this.value;
        let timeDropdown = document.getElementById("appointmentTime");
        let therapistDropdown = document.getElementById("therapistSelect");
        
        // Reset therapist dropdown
        therapistDropdown.innerHTML = `<option value="">Select a Date & Time First</option>`;
        therapistDropdown.disabled = true;

        // Disable blocked dates
        if (blockedDates.includes(selectedDate)) {
            Swal.fire("Unavailable Date", "This date is blocked. Please choose another.", "warning");
            this.value = ""; // Reset date input
            timeDropdown.innerHTML = `<option value="">Select a Date First</option>`;
            timeDropdown.disabled = true;
            return;
        }

        // Generate Available Time Slots
        timeDropdown.innerHTML = generateTimeSlots(settings.business_hours_start, settings.business_hours_end);
        timeDropdown.disabled = false;
    });

    // Handle time selection
    document.getElementById("appointmentTime").addEventListener("change", function() {
        let date = document.getElementById("appointmentDate").value;
        let time = this.value;
        
        if (!date || !time) return;
        
        fetchTherapistsForDateTime(date, time);
    });
}

// Generate time slots based on business hours
function generateTimeSlots(startTime, endTime) {
    let options = "";
    let start = new Date(`1970-01-01T${startTime}`);
    let end = new Date(`1970-01-01T${endTime}`);

    while (start < end) {
        let timeStr = start.toTimeString().slice(0, 5);
        options += `<option value="${timeStr}">${timeStr}</option>`;
        start.setMinutes(start.getMinutes() + 60); // Assuming 1-hour slots
    }

    return options;
}

// Fetch available therapists for a specific date and time
function fetchTherapistsForDateTime(date, time) {
    let therapistDropdown = document.getElementById("therapistSelect");
    
    fetch(`../app_data/get_available_therapists.php?date=${date}&time=${time}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== "success" || data.therapists.length === 0) {
                therapistDropdown.innerHTML = `<option value="">No Available Therapists</option>`;
                therapistDropdown.disabled = true;
                return;
            }

            therapistDropdown.innerHTML = data.therapists.map(t => `
                <option value="${t.id}">${t.name} - [${t.status}] ${t.schedule}</option>
            `).join('');

            therapistDropdown.disabled = false;
        })
        .catch(error => {
            console.error("Error fetching therapists:", error);
            therapistDropdown.innerHTML = `<option value="">Error Fetching Therapists</option>`;
            therapistDropdown.disabled = true;
        });
}

// Update appointment status API call
function updateAppointmentStatus(data, successMessage, errorMessage) {
    console.log("Updating appointment status with data:", data);
    
    fetch("../app_process/update_appointment_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log("Response status:", response.status);
        return response.json();
    })
    .then(responseData => {
        console.log("Response data:", responseData);
        if (responseData.status === "success") {
            Swal.fire("Success!", responseData.message || successMessage, "success")
                .then(() => location.reload());
        } else {
            Swal.fire("Error", responseData.message || errorMessage, "error");
        }
    })
    .catch(error => {
        console.error("Error updating appointment:", error);
        Swal.fire("Error", errorMessage, "error");
    });
}

// Function for updating playgroup slot details
function updatePGSlotDetails(select) {
    const selected = select.options[select.selectedIndex];
    const sessionId = selected.value;

    fetch(`../app_data/get_playgroup_session_details.php?pg_session_id=${sessionId}`)
        .then(response => response.json());
}
 </script>

</body>
</html>

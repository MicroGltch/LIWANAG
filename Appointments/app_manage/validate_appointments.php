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

// Fetch Filters
$statusFilter = $_GET['status'] ?? "";
$sessionTypeFilter = $_GET['session_type'] ?? "";
$therapistFilter = $_GET['therapist'] ?? "";
$startDate = $_GET['start_date'] ?? "";
$endDate = $_GET['end_date'] ?? "";

// Fetch Therapist List
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

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


    <style>
        html,
        body {
            background-color: #ffffff !important;
        }

        .no-break {
            white-space: nowrap;
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

    <!-- Back Button (alt) 
    <a href="manage_appointments.php" class="uk-link-text" style="border-radius: 15px; padding: 10px 15px; font-size: 16px; height: auto; display: inline-block;">
        <span uk-icon="icon: arrow-left"></span> Back
    </a>
    -->
    
    <!-- Tabs -->
    <ul uk-tab >
        <li><a href="#"><span uk-icon="icon: calendar"></span> Pending Appointments</a></li>
        <li><a href="#"><span uk-icon="icon: clock"></span> Waitlisted Appointments</a></li>
    </ul>

    <ul class="uk-switcher uk-margin">
    <li>
        <!-- Pending Appointments Table -->
        <div class="uk-overflow-auto">
        <h2 class="uk-text-bold">Validate Appointments</h2>

            <!-- ✅ Custom Search and Show Entries -->
            <div class="uk-flex uk-flex-between uk-flex-middle uk-margin">
                <div class="uk-width-1-3">
                    <input type="text" id="customSearch" class="uk-input" placeholder="Search..." style="border-radius: 15px;">
                </div>
                <div class="uk-width-auto">
                    <label for="customEntries" class="uk-margin-small-right">Show entries per page:</label>
                    <select id="customEntries" class="uk-select" style="width: auto; border-radius: 15px;">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <table id="pendingAppointmentsTable" class="uk-table uk-table-striped uk-table-middle uk-table-responsive">
                <thead>
                    <tr>
                        <th><span class="no-break">Patient <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th><span class="no-break">Client <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th><span class="no-break">Date <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th><span class="no-break">Time <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th>Booked On <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                        <th><span class="no-break">Session Type <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
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
                                    <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['official_referral_file']); ?>" 
                                    target="_blank" class="uk-button uk-button-secondary" 
                                    style="border-radius: 15px; padding: 8px 12px; font-size: 14px; height: auto; white-space: nowrap;">
                                        View Official Referral
                                    </a>
                                <?php elseif (!empty($appointment['proof_of_booking_referral_file'])): ?>
                                    <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['proof_of_booking_referral_file']); ?>" 
                                    target="_blank" class="uk-button uk-button-warning" 
                                    style="border-radius: 15px; padding: 8px 12px; font-size: 14px; height: auto; white-space: nowrap;">
                                        View Proof of Booking
                                    </a>
                                <?php else: ?>
                                    <span class="uk-text-muted">Not Applicable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="uk-button uk-button-primary action-btn" style="border-radius: 15px;" data-id="<?= $appointment['appointment_id']; ?>" data-action="Approve"
                                data-patient-img="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../uploads/profile_pictures/default.png'; ?>">Approve</button>
                                <button class="uk-button uk-button-danger action-btn" style="border-radius: 15px;" data-id="<?= $appointment['appointment_id']; ?>" data-action="Decline">Decline</button>
                                <?php if (strpos($appointment['session_type'], 'Rebooking') === false): ?>
                                    <button class="uk-button uk-button-default action-btn" style="border-radius: 15px;" data-id="<?= $appointment['appointment_id']; ?>" data-action="Waitlist">Waitlist</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </li>
    <li>
        <!-- Waitlisted Appointments Table -->
        <div class="uk-margin-large-top">
            <h2 class="uk-text-bold">Waitlisted Appointments</h2>

            <!-- ✅ Custom Search and Show Entries -->
            <div class="uk-flex uk-flex-between uk-flex-middle uk-margin">
                <div class="uk-width-1-3">
                    <input type="text" id="waitlistSearch" class="uk-input" placeholder="Search..." style="border-radius: 15px;">
                </div>
                <div class="uk-width-auto">
                    <label for="waitlistEntries" class="uk-margin-small-right">Show entries per page:</label>
                    <select id="waitlistEntries" class="uk-select" style="width: auto; border-radius: 15px;">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
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
    </li>
</ul>



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
     const pendingTable = $('#pendingAppointmentsTable').DataTable({
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            order: [[3, 'asc']], // Sort by date column by default
            dom: 'rtip', // Remove default search and length menu
            language: {
                lengthMenu: "Show _MENU_ entries per page",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });

        // Custom Search for Pending Appointments
        $('#customSearch').on('keyup', function() {
            pendingTable.search(this.value).draw();
        });

        // Custom Entries Dropdown for Pending Appointments
        $('#customEntries').on('change', function() {
            pendingTable.page.len(this.value).draw();
        });

        // Initialize DataTables for waitlisted appointments if present
        if ($('#waitlistedAppointmentsTable').length > 0) {
            const waitlistedTable = $('#waitlistedAppointmentsTable').DataTable({
                pageLength: 5,
                lengthMenu: [5, 10, 25, 50],
                dom: 'rtip', // Remove default search and length menu
                language: {
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });

            // Custom Search for Waitlisted Appointments
            $('#waitlistSearch').on('keyup', function() {
                waitlistedTable.search(this.value).draw();
            });

            // Custom Entries Dropdown for Waitlisted Appointments
            $('#waitlistEntries').on('change', function() {
                waitlistedTable.page.len(this.value).draw();
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

                let rawDate = data.details.date;
                let rawTime = data.details.time;

                // Combine into full datetime string
                let datetimeStr = `${rawDate}T${rawTime}`;
                let datetimeObj = new Date(datetimeStr);

                // Format date: Mar 23, 2025
                let formattedDate = datetimeObj.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

                // Format time: 02:30 PM
                let formattedTime = datetimeObj.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });

                let detailsHtml = `
                    <p><strong>Patient:</strong> ${data.details.patient_name}</p>
                    <p><strong>Client:</strong> ${data.details.client_name}</p>
                    <p><strong>Date:</strong> ${formattedDate}</p>
                    <p><strong>Time:</strong> ${formattedTime}</p>
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

            // ✅ Build therapist card-style options
            let therapistCards = therapistsData.therapists.map(t => {
                const status = t.status.toLowerCase();
                const statusColor = status === "available" ? "#27ae60" :
                                    status.includes("time conflict") ? "#e67e22" : "#e74c3c";
                const disabled = status.includes("unavailable") || status.includes("booked") ? "disabled" : "";
                const schedule = t.schedule || "No schedule info";
                const tooltip = t.status.includes("Booked") ? "This therapist already has an approved appointment at this time." : "";

                return `
                    <div class="therapist-option" style="padding:10px; border:1px solid #ccc; border-radius:8px; margin-bottom:10px;">
                        <label style="display:flex; gap:10px; align-items:flex-start; cursor:pointer;" title="${tooltip}">
                            <input type="radio" name="therapist" value="${t.id}" style="margin-top:5px;" ${disabled} />
                            <div>
                                <div><strong>${t.name}</strong></div>
                                <div style="color:${statusColor}; font-weight: bold;">${t.status}</div>
                                <div style="font-size: 0.85em; color: #555;">${schedule}</div>
                                ${tooltip ? `<div style="font-size: 0.75em; color: #999;">${tooltip}</div>` : ""}
                            </div>
                        </label>
                    </div>
                `;
            }).join('');

            // ✅ Show in SweetAlert modal
            Swal.fire({
                title: "Assign a Therapist",
                html: detailsHtml + `
                    <label><strong>Select Therapist:</strong></label>
                    <div id="therapistOptions" style="text-align:left; max-height:300px; overflow-y:auto; margin-top:10px;">
                        ${therapistCards}
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: "Approve",
                preConfirm: () => {
                    const selected = document.querySelector('input[name="therapist"]:checked');
                    if (!selected) {
                        Swal.showValidationMessage("Please select a therapist");
                        return false;
                    }
                    return { therapistId: selected.value };
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
            const reasonSelect = document.getElementById("declineReasonSelect");
            const reasonInput = document.getElementById("declineReason");

            let finalReason = "";

            if (reasonSelect.value === "Other") {
                finalReason = reasonInput.value.trim();
            } else {
                finalReason = reasonSelect.value || "No reason provided";
            }

            return { reason: finalReason };
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
    // 1️⃣ Fetch appointment details first
    fetch(`../app_data/get_appointment_details.php?appointment_id=${appointmentId}`)
        .then(response => response.json())
        .then(appointmentData => {
            if (appointmentData.status !== "success") {
                Swal.fire("Error", "Failed to fetch appointment details.", "error");
                return;
            }

            const d = appointmentData.details;

            // Optional: Format date and time
            const datetime = new Date(`${d.date}T${d.time}`);
            const formattedDate = datetime.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            const formattedTime = datetime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });

            const detailsHtml = `
                <p><strong>Patient:</strong> ${d.patient_name}</p>
                <p><strong>Client:</strong> ${d.client_name}</p>
                <p><strong>Session Type:</strong> ${d.session_type}</p>
                <p><strong>Original Date:</strong> ${formattedDate}</p>
                <p><strong>Original Time:</strong> ${formattedTime}</p>
            `;

            // 2️⃣ Then fetch timetable settings
            fetch("../app_data/get_timetable_settings.php")
                .then(response => response.json())
                .then(data => {
                    if (data.status !== "success") {
                        Swal.fire("Error!", "Could not fetch timetable settings.", "error");
                        return;
                    }

                    const settings = data.settings;
                    const blockedDates = settings.blocked_dates || [];

                    Swal.fire({
                        title: "Reschedule Appointment",
                        html: `
                            ${detailsHtml}
                            <label>New Date:</label>
                            <input type="text" id="appointmentDate" class="swal2-input">
                            <label>New Time:</label>
                            <select id="appointmentTime" class="swal2-select">
                                <option value="">Select a Date First</option>
                            </select>
                            <br/>
                            <br/>
                            <br/>
                            <label>Assign Therapist:</label>
                            <div id="therapistSelectContainer" style="max-height:300px; overflow-y:auto; text-align:left;"></div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: "Assign",
                        didOpen: () => {
                            setupDateTimeFields(settings, blockedDates);
                        },
                        preConfirm: () => {
                            const date = document.getElementById("appointmentDate").value;
                            const time = document.getElementById("appointmentTime").value;
                            const selected = document.querySelector('input[name="therapist"]:checked');

                            if (!date || !time || !selected) {
                                Swal.showValidationMessage("Please select a valid date, time, and therapist.");
                                return false;
                            }

                            return {
                                date,
                                time,
                                therapist_id: selected.value
                            };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            updateAppointmentStatus({
                                appointment_id: appointmentId,
                                status: "approved",
                                date: result.value.date,
                                time: result.value.time,
                                therapist_id: result.value.therapist_id
                            }, "Appointment has been rescheduled and therapist assigned", "Failed to update appointment");
                        }
                    });
                })
                .catch(error => {
                    console.error("Error fetching timetable settings:", error);
                    Swal.fire("Error!", "Error fetching settings.", "error");
                });
        })
        .catch(error => {
            console.error("Error fetching appointment details:", error);
            Swal.fire("Error", "Unable to load appointment info.", "error");
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
    const minDate = new Date();
    const maxDate = new Date();
    minDate.setDate(minDate.getDate() + Number(settings.min_days_advance));
    maxDate.setDate(maxDate.getDate() + Number(settings.max_days_advance));

    // 🟩 Initialize Flatpickr for Date Input
    flatpickr("#appointmentDate", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "M d, Y",
        minDate: minDate,
        maxDate: maxDate,
        disable: blockedDates, // ⛔ Disable blocked dates
        onChange: function (selectedDates, dateStr) {
            const timeDropdown = document.getElementById("appointmentTime");
            const therapistContainer = document.getElementById("therapistSelectContainer");

            if (!dateStr) return;

            // 🧹 Reset containers
            therapistContainer.innerHTML = "";
            timeDropdown.innerHTML = generateTimeSlots(settings.business_hours_start, settings.business_hours_end);
            timeDropdown.disabled = false;
        }
    });

    // ⏱️ Time dropdown logic
    document.getElementById("appointmentTime").addEventListener("change", function () {
        const selectedDate = document.getElementById("appointmentDate").value;
        const selectedTime = this.value;

        if (!selectedDate || !selectedTime) return;
        fetchTherapistsForDateTime(selectedDate, selectedTime);
    });
}


// Generate time slots based on business hours
function generateTimeSlots(startTime, endTime) {
    let options = "";
    let start = new Date(`1970-01-01T${startTime}`);
    let end = new Date(`1970-01-01T${endTime}`);

    while (start < end) {
        const hour = start.getHours();
        const minutes = start.getMinutes().toString().padStart(2, "0");
        const ampm = hour >= 12 ? "PM" : "AM";
        const hour12 = hour % 12 || 12;

        const timeStr = `${hour12}:${minutes} ${ampm}`;
        const valueStr = `${hour.toString().padStart(2, "0")}:${minutes}`;

        options += `<option value="${valueStr}">${timeStr}</option>`;
        start.setMinutes(start.getMinutes() + 60); // Adjust interval as needed
    }

    return options;
}

// Fetch available therapists for a specific date and time
function fetchTherapistsForDateTime(date, time) {
    const container = document.getElementById("therapistSelectContainer");
    container.innerHTML = `<p>Loading therapists...</p>`;
    container.dataset.valid = "false";

    fetch(`../app_data/get_available_therapists.php?date=${date}&time=${time}`)
        .then(response => response.json())
        .then(data => {
            if (data.status !== "success" || data.therapists.length === 0) {
                container.innerHTML = `<p style="color:red;">No therapists available.</p>`;
                return;
            }

            let therapistCards = data.therapists.map(t => {
                const status = t.status.toLowerCase();
                const statusColor = status === "available" ? "#27ae60" :
                                    status.includes("time conflict") ? "#e67e22" : "#e74c3c";
                const disabled = status === "unavailable" ? "disabled" : "";
                const schedule = t.schedule || "No schedule info";

                return `
                    <div class="therapist-option" style="padding:10px; border:1px solid #ccc; border-radius:8px; margin-bottom:10px;">
                        <label style="display:flex; gap:10px; align-items:flex-start; cursor:pointer;">
                            <input type="radio" name="therapist" value="${t.id}" style="margin-top:5px;" ${disabled} />
                            <div>
                                <div><strong>${t.name}</strong></div>
                                <div style="color:${statusColor}; font-weight: bold;">${t.status}</div>
                                <div style="font-size: 0.85em; color: #555;">${schedule}</div>
                            </div>
                        </label>
                    </div>
                `;
            }).join("");

            container.innerHTML = therapistCards;
            container.dataset.valid = "true";
        })
        .catch(error => {
            console.error("Error fetching therapists:", error);
            container.innerHTML = `<p style="color:red;">Error loading therapists.</p>`;
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
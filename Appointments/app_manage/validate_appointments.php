<?php
require_once "../../dbconfig.php"; // Adjust path
session_start();

// Restrict Access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type'] ?? ''), ["admin", "head therapist"])) { // Added null check for session type
    header("Location: ../../Accounts/loginpage.php"); // Adjust path
    exit();
}

// --- Database Interaction ---
$pendingAppointments = [];
$waitlistedAppointments = [];
$connectionFailed = false; // Flag for connection status

if ($connection) {
    // Fetch PENDING appointments with referral information
    $query = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.status,
                     -- Use IF condition to check if rebooked_by is set, otherwise show session_type
                     IF(a.rebooked_by IS NOT NULL AND t.account_FName IS NOT NULL,
                        CONCAT('Rebooking (', a.session_type, ') by: ', t.account_FName, ' ', t.account_LName),
                        a.session_type) AS display_session_type, -- Renamed for clarity
                     a.session_type AS raw_session_type, -- Keep original session type for logic
                     a.created_at,
                     dr.referral_type,
                     dr.official_referral_file,
                     dr.proof_of_booking_referral_file,
                     p.first_name, p.last_name, p.profile_picture AS patient_picture,
                     u.account_FName AS client_firstname, u.account_LName AS client_lastname, u.profile_picture AS client_picture
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN users u ON a.account_id = u.account_ID
              LEFT JOIN users t ON a.rebooked_by = t.account_ID -- Join for rebooked_by user name
              LEFT JOIN doctor_referrals dr ON a.referral_id = dr.referral_id
              WHERE a.status = 'pending' -- Only pending (lowercase)
              ORDER BY a.date ASC, a.time ASC";

    $result = $connection->query($query);
    if ($result) {
        $pendingAppointments = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Error fetching pending appointments: " . $connection->error);
    }

    // Fetch WAITLISTED appointments
    $waitlistQuery = "SELECT a.appointment_id, a.patient_id, a.date, a.time,
                             a.session_type AS raw_session_type, -- Keep raw type for logic
                             a.status as waitlist_status,
                             p.first_name, p.last_name,
                             u.account_FName AS client_firstname, u.account_LName AS client_lastname
                      FROM appointments a
                      JOIN patients p ON a.patient_id = p.patient_id
                      JOIN users u ON a.account_id = u.account_ID
                      WHERE a.status IN ('waitlisted', 'Waitlisted - Any Day', 'Waitlisted - Specific Date') -- lowercase standard waitlist
                      ORDER BY a.created_at ASC"; // Order waitlist by creation time

    $waitlistResult = $connection->query($waitlistQuery);
    if ($waitlistResult) {
        $waitlistedAppointments = $waitlistResult->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Error fetching waitlisted appointments: " . $connection->error);
    }

    $connection->close(); // Close DB connection after all queries
} else {
    // Handle connection error
    $connectionFailed = true;
    error_log("Database connection failed in validate_appointments.php");
}
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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.uikit.min.css">
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- FontAwesome (Replace with your actual Kit URL if using) -->
    <!-- <script src="https://kit.fontawesome.com/YOUR_KIT_CODE.js" crossorigin="anonymous"></script> -->


    <style>
        html, body { background-color: #ffffff !important; }
        .no-break { white-space: nowrap; }
        .uk-table th { font-weight: bold; }
        .uk-table td img { vertical-align: middle; }
        .action-btn, .assign-btn, .assign-playgroup-btn, .remove-waitlist-btn { margin: 2px; }
        /* Styling for therapist selection modal */
        .therapist-option { padding: 10px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 10px; background-color: #fff; }
        .therapist-option label { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; }
        .therapist-option input[type="radio"] { margin-top: 5px; }
        #therapistOptions, #assignTherapistContainer { text-align: left; max-height: 350px; overflow-y: auto; margin-top: 10px; border: 1px solid #e5e5e5; padding: 10px; background: #f9f9f9; }
        /* Add subtle animation/feedback for processing */
        button[data-processing="true"] { opacity: 0.7; cursor: wait; }
    </style>
</head>

<body>
    <!-- Breadcrumb -->
    <ul class="uk-breadcrumb">
        <li><a href="manage_appointments.php">Manage Appointments</a></li>
        <li><span>Validate Appointments</span></li>
    </ul>

    <?php if ($connectionFailed): ?>
        <div class="uk-alert-danger" uk-alert>
            <p>Error: Could not connect to the database. Please contact support.</p>
        </div>
    <?php else: ?>
        <!-- Tabs -->
        <ul uk-tab>
            <li class="uk-active"><a href="#">Pending Appointments <span class="uk-badge"><?= count($pendingAppointments) ?></span></a></li>
            <li><a href="#">Waitlisted Appointments <span class="uk-badge"><?= count($waitlistedAppointments) ?></span></a></li>
        </ul>

        <ul class="uk-switcher uk-margin">
            <li>
                <!-- Pending Appointments Section -->
                <div class="uk-card uk-card-default uk-card-body uk-card-small">
                    <h2 class="uk-card-title uk-text-bold">Validate Pending Appointments</h2>
                    <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
                        <div class="uk-width-1-3@m uk-width-1-2@s">
                            <input type="text" id="pendingSearch" class="uk-input uk-form-small" placeholder="Search Pending...">
                        </div>
                        <div class="uk-width-auto">
                            <label for="pendingEntries" class="uk-form-label uk-margin-small-right">Show:</label>
                            <select id="pendingEntries" class="uk-select uk-form-width-xsmall uk-form-small">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                    <div class="uk-overflow-auto">
                        <table id="pendingAppointmentsTable" class="uk-table uk-table-hover uk-table-striped uk-table-middle uk-table-responsive" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Booked On</th>
                                    <th>Session Type</th>
                                    <th>Referral</th>
                                    <th class="uk-text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingAppointments)): ?>
                                    <tr><td colspan="8" class="uk-text-center uk-text-muted">No pending appointments found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pendingAppointments as $appointment):
                                        $patientPic = !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . htmlspecialchars($appointment['patient_picture']) : '../../uploads/profile_pictures/default.png';
                                        $clientPic = !empty($appointment['client_picture']) ? '../../uploads/profile_pictures/' . htmlspecialchars($appointment['client_picture']) : '../../uploads/profile_pictures/default.png';
                                    ?>
                                        <tr>
                                            <td class="no-break">
                                                <img src="<?= $patientPic ?>" alt="P" class="uk-border-circle" style="width: 35px; height: 35px; object-fit: cover; margin-right: 5px;" onerror="this.style.display='none';">
                                                <?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?>
                                            </td>
                                            <td class="no-break">
                                                <img src="<?= $clientPic ?>" alt="C" class="uk-border-circle" style="width: 35px; height: 35px; object-fit: cover; margin-right: 5px;" onerror="this.style.display='none';">
                                                <?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?>
                                            </td>
                                            <td class="no-break"><?= !empty($appointment['date']) ? htmlspecialchars(date("M d, Y", strtotime($appointment['date']))) : 'N/A'; ?></td>
                                            <td class="no-break"><?= !empty($appointment['time']) ? htmlspecialchars(date("g:i A", strtotime($appointment['time']))) : 'N/A'; ?></td>
                                            <td class="no-break"><?= !empty($appointment['created_at']) ? htmlspecialchars(date("M d, Y g:i A", strtotime($appointment['created_at']))) : 'N/A'; ?></td>
                                            <td><?= htmlspecialchars(ucwords(str_replace(['-', '_'], ' ', strtolower($appointment['display_session_type'] ?? '')))); ?></td>
                                            <td>
                                                <?php if (!empty($appointment['official_referral_file'])): ?>
                                                    <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['official_referral_file']); ?>" target="_blank" class="uk-link-reset" uk-tooltip="View Official Referral"><span uk-icon="icon: file-pdf; ratio: 1.1"></span></a>
                                                <?php elseif (!empty($appointment['proof_of_booking_referral_file'])): ?>
                                                    <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['proof_of_booking_referral_file']); ?>" target="_blank" class="uk-link-reset" uk-tooltip="View Proof of Booking"><span uk-icon="icon: file-text; ratio: 1.1"></span></a>
                                                <?php else: ?>
                                                    <span class="uk-text-muted uk-text-small" uk-tooltip="No referral applicable or uploaded">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="uk-text-center no-break">
                                                <button class="uk-button uk-button-primary uk-button-small action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Approve" uk-tooltip="Approve and Assign Therapist">
                                                    <span uk-icon="check"></span>
                                                </button>
                                                <button class="uk-button uk-button-danger uk-button-small action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Decline" uk-tooltip="Decline Request">
                                                    <span uk-icon="ban"></span>
                                                </button>
                                                <?php if (strpos(strtolower($appointment['display_session_type'] ?? ''), 'rebooking') === false): ?>
                                                    <button class="uk-button uk-button-secondary uk-button-small action-btn" data-id="<?= $appointment['appointment_id']; ?>" data-action="Waitlist" uk-tooltip="Move to Waitlist">
                                                        <span uk-icon="history"></span>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>
            <li>
                <!-- Waitlisted Appointments Section -->
                <div class="uk-card uk-card-default uk-card-body uk-card-small">
                    <h2 class="uk-card-title uk-text-bold">Waitlisted Appointments</h2>
                   <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
                       <div class="uk-width-1-3@m uk-width-1-2@s">
                           <input type="text" id="waitlistSearch" class="uk-input uk-form-small" placeholder="Search Waitlist...">
                       </div>
                       <div class="uk-width-auto">
                           <label for="waitlistEntries" class="uk-form-label uk-margin-small-right">Show:</label>
                           <select id="waitlistEntries" class="uk-select uk-form-width-xsmall uk-form-small">
                               <option value="5">5</option>
                               <option value="10" selected>10</option>
                               <option value="25">25</option>
                               <option value="50">50</option>
                           </select>
                       </div>
                   </div>
                   <div class="uk-overflow-auto">
                       <table id="waitlistedAppointmentsTable" class="uk-table uk-table-hover uk-table-striped uk-table-middle uk-table-responsive" style="width:100%">
                           <thead>
                               <tr>
                                   <th>Patient</th>
                                   <th>Client</th>
                                   <th>Session Type</th>
                                   <th>Waitlist Status</th>
                                   <th>Original Date</th>
                                   <th>Action</th>
                               </tr>
                           </thead>
                           <tbody>
                               <?php if (empty($waitlistedAppointments)): ?>
                                   <tr><td colspan="6" class="uk-text-center uk-text-muted">No waitlisted appointments found.</td></tr>
                               <?php else: ?>
                                   <?php foreach ($waitlistedAppointments as $appointment): ?>
                                       <tr>
                                           <td class="no-break"><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                                           <td class="no-break"><?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?></td>
                                           <td><?= htmlspecialchars(ucwords(str_replace(['-', '_'], ' ', strtolower($appointment['raw_session_type'] ?? '')))); ?></td>
                                           <td><?= htmlspecialchars(ucwords(str_replace('-', ' ', strtolower($appointment['waitlist_status'] ?? '')))); ?></td>
                                           <td><?= !empty($appointment['date']) ? htmlspecialchars(date("M d, Y", strtotime($appointment['date']))) : 'Any Day'; ?></td>
                                           <td class="no-break uk-text-center">
                                               <?php if (strtolower($appointment['raw_session_type'] ?? '') === 'playgroup'): ?>
                                                   <button class="uk-button uk-button-primary uk-button-small assign-playgroup-btn" data-id="<?= $appointment['appointment_id']; ?>" uk-tooltip="Assign Playgroup Slot">
                                                       <span uk-icon="icon: plus-circle"></span> Assign
                                                   </button>
                                               <?php else: ?>
                                                   <button class="uk-button uk-button-primary uk-button-small assign-btn" data-id="<?= $appointment['appointment_id']; ?>" uk-tooltip="Assign Date, Time & Therapist">
                                                      <span uk-icon="icon: plus-circle"></span> Assign
                                                   </button>
                                               <?php endif; ?>
                                               <button class="uk-button uk-button-danger uk-button-small remove-waitlist-btn" data-id="<?= $appointment['appointment_id']; ?>" uk-tooltip="Remove from Waitlist">
                                                   <span uk-icon="trash"></span>
                                               </button>
                                           </td>
                                       </tr>
                                   <?php endforeach; ?>
                               <?php endif; ?>
                           </tbody>
                       </table>
                   </div>
                </div>
            </li>
        </ul>
    <?php endif; // End check for connection failure ?>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Custom Script -->
    <script>
        // --- Constants for API Endpoints ---
        const API_ENDPOINTS = {
            getDetails: '../app_data/get_appointment_details.php',
            getTherapists: '../app_data/get_available_therapists.php',
            getPlaygroupSessions: '../app_data/get_open_playgroup_sessions.php',
            getTimetableSettings: '../app_data/get_timetable_settings.php', // Needed for assign modal
            getSlotsEnhanced: '../app_data/get_available_slots_enhanced.php', // Needed for assign modal
            updateStatus: '../app_process/update_appointment_status.php' // Adjust path if needed
        };

        // --- Helper Functions ---
        function log(...args) { console.log('[ValidateAppt]', ...args); }
        function ucfirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

        // --- Main Document Ready ---
        $(document).ready(function() {
            log("Document Ready. Initializing...");

            // Global error handler
            window.onerror = function(message, source, lineno, colno, error) {
                 console.error("JS Error:", message, "at", source, ":", lineno, error);
                 // Avoid showing Swal for every minor script error during development
                 // if (!Swal.isVisible()) { Swal.fire('Script Error', 'An error occurred.', 'error'); }
                 return true;
             };

            // --- Initialize DataTables ---
            try {
                if ($.fn.DataTable.isDataTable('#pendingAppointmentsTable') === false) {
                    const pendingTable = $('#pendingAppointmentsTable').DataTable({
                         pageLength: 10, lengthMenu: [5, 10, 25, 50],
                         order: [[2, 'asc'], [3, 'asc']], dom: 'lrtip',
                         // Add column definitions if needed for sorting specific formats
                    });
                    $('#pendingSearch').on('keyup', function() { pendingTable.search(this.value).draw(); });
                    $('#pendingEntries').on('change', function() { pendingTable.page.len(this.value).draw(); });
                }

                if ($('#waitlistedAppointmentsTable').length > 0 && $.fn.DataTable.isDataTable('#waitlistedAppointmentsTable') === false) {
                    const waitlistedTable = $('#waitlistedAppointmentsTable').DataTable({
                         pageLength: 10, lengthMenu: [5, 10, 25, 50],
                         order: [[0, 'asc']], dom: 'lrtip',
                    });
                    $('#waitlistSearch').on('keyup', function() { waitlistedTable.search(this.value).draw(); });
                    $('#waitlistEntries').on('change', function() { waitlistedTable.page.len(this.value).draw(); });
                }
            } catch (e) { console.error("Error initializing DataTables:", e); }


            // --- Event Delegation for ALL Action Buttons ---
            $(document).on('click', '.action-btn, .assign-btn, .assign-playgroup-btn, .remove-waitlist-btn', function(e) {
                e.preventDefault();
                const button = $(this);
                const appointmentId = button.data('id'); // Use .data() which gets data-* attributes
                let action = button.data('action');

                // Determine action based on class if data-action is missing
                if (!action) {
                     if (button.hasClass('assign-btn')) action = 'Assign';
                     else if (button.hasClass('assign-playgroup-btn')) action = 'AssignPlaygroup';
                     else if (button.hasClass('remove-waitlist-btn')) action = 'RemoveWaitlist';
                }

                if (!appointmentId || !action) {
                    console.error("Missing appointment ID or action.", { button: this });
                    Swal.fire("Error", "Could not determine appointment or action.", "error");
                    return;
                }

                // Prevent double clicks/submissions
                if (button.data('processing')) { log("Already processing..."); return; }
                button.data('processing', true);
                button.prop('disabled', true); // Visually disable button
                log(`Action Triggered: ${action}, Appointment ID: ${appointmentId}`);

                // Route to appropriate handler
                try {
                     switch (action) {
                         case 'Approve':
                         case 'Decline':
                         case 'Waitlist':
                             fetchAppointmentDetailsAndAct(appointmentId, action, button);
                             break;
                         case 'Assign':
                             handleAssignAction(appointmentId, button);
                             break;
                         case 'AssignPlaygroup':
                             handleAssignPlaygroupAction(appointmentId, button);
                             break;
                         case 'RemoveWaitlist':
                             handleRemoveWaitlistAction(appointmentId, button);
                             break;
                         default:
                             throw new Error(`Unrecognized action: ${action}`);
                     }
                 } catch (error) { // Catch synchronous errors in routing
                      console.error("Error routing action:", error);
                      Swal.fire("Error", "An unexpected error occurred initiating the action.", "error");
                      button.removeData('processing').prop('disabled', false); // Reset button state
                 }

            }); // End generalized button click listener

            log("Event listeners initialized.");
        }); // --- End Document Ready ---


        // =============================================
        // --- Fetch and Route Primary Actions ---
        // =============================================
        function fetchAppointmentDetailsAndAct(appointmentId, action, buttonElement) {
            // Show loading immediately
             Swal.fire({ title: 'Loading Details...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch(`${API_ENDPOINTS.getDetails}?appointment_id=${appointmentId}`)
                .then(response => response.ok ? response.json() : response.text().then(text => { throw new Error(`Fetch Details Error (${response.status}): ${text}`); }))
                .then(data => {
                    // Close loading Swal only AFTER data received, before showing next Swal
                     Swal.close();
                     log("Appointment Details Fetched:", data);

                    if (data.status !== "success") { throw new Error(data.message || "Failed to fetch appointment details."); }
                    if (!data.details || !data.details.date || !data.details.time || !data.details.raw_session_type) {
                        throw new Error("Incomplete appointment details received (date, time, or type missing).");
                    }

                    // Construct HTML
                     let details = data.details;
                     let displaySessionType = details.display_session_type || details.raw_session_type;
                     let formattedDate = 'N/A', formattedTime = 'N/A';
                     try {
                         let dt = new Date(`${details.date}T${details.time}`);
                         if (!isNaN(dt)) {
                             formattedDate = dt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                             formattedTime = dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
                         }
                     } catch(e) { log("Date/Time format error", e); }

                     let detailsHtml = `
                         <div style="text-align: left; margin-bottom: 15px; font-size: 0.9em;">
                             <p><strong>Patient:</strong> ${details.patient_name || 'N/A'}</p>
                             <p><strong>Client:</strong> ${details.client_name || 'N/A'}</p>
                             <p><strong>Date:</strong> ${formattedDate}</p>
                             <p><strong>Time:</strong> ${formattedTime}</p>
                             <p><strong>Session Type:</strong> ${ucfirst(displaySessionType.replace(/[-_]/g, ' '))}</p>
                             ${details.rebooked_by_name ? `<p><strong>Rebooked By:</strong> ${details.rebooked_by_name}</p>` : ''}
                             <p><strong>Referral:</strong> ${details.doctor_referral || 'N/A'}</p>
                         </div>
                         <hr style='margin: 10px 0;'>
                     `;

                    // Route to specific handler
                     if (action === "Approve") {
                          if (details.raw_session_type && details.raw_session_type.toLowerCase() === "playgroup") {
                              handlePlaygroupApproval(appointmentId, data, detailsHtml, buttonElement);
                          } else {
                              handleApproveAction(appointmentId, data, detailsHtml, buttonElement);
                          }
                     } else if (action === "Decline") {
                          handleDeclineAction(appointmentId, detailsHtml, buttonElement);
                     } else if (action === "Waitlist") {
                          if (displaySessionType && displaySessionType.toLowerCase().includes('rebooking')) {
                              Swal.fire("Action Not Allowed", "Cannot waitlist a rebooking request.", "warning");
                              buttonElement.removeData('processing').prop('disabled', false); // Reset button
                          } else {
                              handleWaitlistAction(appointmentId, detailsHtml, buttonElement);
                          }
                     } else {
                         // Should not happen if routing is correct
                         throw new Error("Invalid action passed to fetchAppointmentDetailsAndAct");
                     }
                })
                .catch(error => {
                     Swal.close(); // Ensure loading is closed on error
                     console.error(`Error in fetchAppointmentDetailsAndAct for action ${action}:`, error);
                     Swal.fire("Error", error.message || "Failed to process request.", "error");
                     buttonElement.removeData('processing').prop('disabled', false); // Reset button state on error
                });
        }

        // ==================================
        // --- Specific Action Handlers ---
        // ==================================

        // --- APPROVE ACTION (Non-Playgroup) ---
        function handleApproveAction(appointmentId, appointmentData, detailsHtml, buttonElement) {
            const details = appointmentData?.details;
             // ** Check details again **
             if (!details || !details.date || !details.time || !details.raw_session_type) {
                 console.error("Approve Error: Essential details missing", details);
                 Swal.fire("Error", "Cannot approve: Incomplete appointment details.", "error");
                 buttonElement.removeData('processing').prop('disabled', false); return;
             }

            const sessionType = details.raw_session_type;
            const appointmentDate = details.date;
            const appointmentTime = details.time;
            log(`Handling Approve: Appt ID=${appointmentId}, Type=${sessionType}, Date=${appointmentDate}, Time=${appointmentTime}`);

            // Basic format validation
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/; const timeRegex = /^\d{2}:\d{2}:\d{2}$/;
             if (!dateRegex.test(appointmentDate) || !timeRegex.test(appointmentTime)) {
                 console.error("Approve Error: Invalid date/time format", { date: appointmentDate, time: appointmentTime });
                 Swal.fire("Error", "Cannot approve: Invalid date or time format.", "error");
                 buttonElement.removeData('processing').prop('disabled', false); return;
             }

            const therapistUrl = `${API_ENDPOINTS.getTherapists}?date=${appointmentDate}&time=${appointmentTime}&session_type=${encodeURIComponent(sessionType)}`;
            log("Fetching therapists using URL:", therapistUrl);

            Swal.fire({ title: 'Finding Available Therapists...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch(therapistUrl)
                .then(response => response.ok ? response.json() : response.text().then(text => { throw new Error(`Therapist Fetch Error (${response.status}): ${text}`); }))
                .then(therapistsData => {
                    log("Available Therapists Response:", therapistsData);

                     // Reset flag once fetch completes, BEFORE showing next Swal
                     // buttonElement.removeData('processing').prop('disabled', false); // Do NOT reset yet, user needs to confirm/cancel therapist selection

                    if (therapistsData.status !== "success") {
                        throw new Error(therapistsData.message || "Could not retrieve therapist list."); // Throw error to be caught
                    }
                    if (!therapistsData.therapists || therapistsData.therapists.length === 0) {
                         Swal.fire({
                             title: "No Suitable Therapists Available",
                             html: `No qualified therapists for <strong>${sessionType}</strong> were found available on <strong>${appointmentDate}</strong> at <strong>${appointmentTime.substring(0,5)}</strong>.`,
                             icon: "warning"
                         });
                         buttonElement.removeData('processing').prop('disabled', false); // Reset here if none found
                         return; // Stop if no therapists
                    }

                    // Build therapist cards (ONLY shows available ones now)
                    let therapistCards = therapistsData.therapists.map(t => {
                         const schedule = t.schedule || "N/A";
                         return `
                         <div class="therapist-option">
                             <label title="Available for this slot">
                                 <input type="radio" name="therapist" value="${t.id}" class="uk-radio"/>
                                 <div class="uk-width-expand">
                                     <div class="uk-text-bold">${t.name}</div>
                                     <div class="uk-text-meta uk-text-small">Schedule: ${schedule}</div>
                                 </div>
                             </label>
                         </div>`;
                    }).join('');

                    // Show Swal to select therapist
                    Swal.fire({
                         title: "Assign Available Therapist",
                         html: detailsHtml +
                               `<label class="uk-form-label uk-text-bold">Select Therapist (${ucfirst(sessionType.replace(/[-_]/g, ' '))}):</label>
                               <div id="therapistOptions">${therapistCards}</div>`,
                         width: '600px',
                         showCancelButton: true,
                         confirmButtonText: "Approve & Assign",
                         cancelButtonText: "Cancel",
                         preConfirm: () => {
                             const selected = document.querySelector('#therapistOptions input[name="therapist"]:checked');
                             if (!selected) { Swal.showValidationMessage("Please select a therapist."); return false; }
                             return { therapistId: selected.value };
                         }
                    }).then((result) => {
                        // Reset button state ONLY AFTER user confirms or cancels
                         buttonElement.removeData('processing').prop('disabled', false);
                         if (result.isConfirmed) {
                             updateAppointmentStatus({
                                 appointment_id: appointmentId, status: "approved", therapist_id: result.value.therapistId
                             }, "Approval successful...", "Failed to approve...", buttonElement);
                         } else { log("Therapist assignment cancelled by user."); }
                    });

                })
                .catch(error => {
                     Swal.close(); // Close the loading Swal
                     console.error("Error fetching or processing therapists:", error);
                     Swal.fire("Error", "An error occurred fetching available therapists. " + error.message, "error");
                     buttonElement.removeData('processing').prop('disabled', false); // Ensure flag reset on error
                });
        }

        // --- APPROVE ACTION (Playgroup) ---
        function handlePlaygroupApproval(appointmentId, appointmentData, detailsHtml, buttonElement) {
            log(`Handling Playgroup Approve for Appt ID: ${appointmentId}`);
             Swal.fire({ title: 'Finding Playgroup Sessions...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch(API_ENDPOINTS.getPlaygroupSessions)
                .then(response => response.ok ? response.json() : Promise.reject('Network error fetching sessions'))
                .then(slotData => {
                     if (slotData.status !== "success" || !slotData.sessions || slotData.sessions.length === 0) {
                         Swal.fire("No Available Sessions", "No open Playgroup sessions found...", "warning");
                         buttonElement.removeData('processing').prop('disabled', false); return;
                     }
                     let slotOptions = slotData.sessions.map(slot => { /* ... format ... */ return `<option value="${slot.pg_session_id}">...</option>`; }).join('');
                     // Show Swal to select session
                      Swal.fire({ /* ... Swal config ... */ })
                      .then((result) => {
                           buttonElement.removeData('processing').prop('disabled', false); // Reset after user interaction
                           if (result.isConfirmed) {
                                updateAppointmentStatus({ /* ... */ }, "Playgroup assigned...", "Failed...", buttonElement);
                           } else { log("Playgroup assignment cancelled."); }
                      });
                })
                .catch(error => {
                     Swal.close(); console.error("Error fetching playgroup slots:", error);
                     Swal.fire("Error", "Failed to fetch Playgroup sessions.", "error");
                     buttonElement.removeData('processing').prop('disabled', false); // Reset on error
                });
        }

        // --- DECLINE ACTION ---
        function handleDeclineAction(appointmentId, detailsHtml, buttonElement) {
            log(`Handling Decline for Appt ID: ${appointmentId}`);
            Swal.fire({ /* ... Swal config ... */ })
            .then((result) => {
                // Reset button state regardless of confirmation
                buttonElement.removeData('processing').prop('disabled', false);
                if (result.isConfirmed) {
                    updateAppointmentStatus({
                        appointment_id: appointmentId, status: "declined", validation_notes: result.value.reason
                    }, "Appointment declined", "Failed...", buttonElement);
                } else { log("Decline cancelled."); }
            });
        }

        // --- WAITLIST ACTION ---
        function handleWaitlistAction(appointmentId, detailsHtml, buttonElement) {
             log(`Handling Waitlist for Appt ID: ${appointmentId}`);
             Swal.fire({ /* ... Swal config ... */ })
             .then((result) => {
                  buttonElement.removeData('processing').prop('disabled', false); // Reset button state
                  if (result.isConfirmed) {
                       updateAppointmentStatus({
                           appointment_id: appointmentId, status: "waitlisted", validation_notes: result.value
                       }, "Appointment waitlisted", "Failed...", buttonElement);
                  } else { log("Waitlist cancelled."); }
             });
        }

        // --- REMOVE FROM WAITLIST ACTION ---
        function handleRemoveWaitlistAction(appointmentId, buttonElement) {
             log(`Handling Remove Waitlist for ID: ${appointmentId}`);
             Swal.fire({ /* ... Confirmation Swal ... */ })
             .then((result) => {
                 // Don't reset button here, let updateAppointmentStatus handle it or reload
                  if (result.isConfirmed) {
                       updateAppointmentStatus({
                           appointment_id: appointmentId, status: "cancelled", validation_notes: "Removed from waitlist by admin/staff."
                       }, "Removed from waitlist...", "Failed...", buttonElement);
                  } else {
                       buttonElement.removeData('processing').prop('disabled', false); // Reset if cancelled
                  }
             });
        }

        // --- ASSIGN SLOT FROM WAITLIST (Non-Playgroup) ---
        function handleAssignAction(appointmentId, buttonElement) {
             log(`Handling Assign Slot from Waitlist for ID: ${appointmentId}`);
             let originalAppointmentData = null;
             Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

             fetch(`${API_ENDPOINTS.getDetails}?appointment_id=${appointmentId}`)
                 .then(response => response.ok ? response.json() : Promise.reject('Failed details fetch'))
                 .then(appointmentData => {
                     if (appointmentData.status !== "success" || !appointmentData.details) { throw new Error(appointmentData.message || "Could not load details."); }
                     originalAppointmentData = appointmentData;
                     return fetch(API_ENDPOINTS.getTimetableSettings); // Chain next fetch
                 })
                 .then(response => response.ok ? response.json() : Promise.reject('Failed settings fetch'))
                 .then(settingsData => {
                      if (settingsData.status !== "success" || !settingsData.settings) { throw new Error(settingsData.message || "Could not load settings."); }
                      const settings = settingsData.settings;
                      const blockedDates = settings.blocked_dates ? JSON.parse(settings.blocked_dates) : [];

                      // Show Assign Swal
                      Swal.fire({ /* ... Assign Swal config ... */ })
                      .then((result) => {
                            // Reset button AFTER user interaction with this modal
                            buttonElement.removeData('processing').prop('disabled', false);
                            if (result.isConfirmed) {
                                 updateAppointmentStatus({ /* ... new details ... */ }, "Assigned...", "Failed...", buttonElement);
                            } else { log("Assignment cancelled."); }
                       });
                 })
                 .catch(error => {
                      Swal.close(); console.error("Error during waitlist assignment:", error);
                      Swal.fire("Error", "Could not proceed. " + error.message, "error");
                      buttonElement.removeData('processing').prop('disabled', false); // Reset on error
                 });
        }


        // --- ASSIGN SLOT FROM WAITLIST (Playgroup) ---
        function handleAssignPlaygroupAction(appointmentId, buttonElement) {
             log(`Handling Assign Playgroup from Waitlist for ID: ${appointmentId}`);
             Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

             fetch(API_ENDPOINTS.getPlaygroupSessions)
                  .then(response => response.ok ? response.json() : Promise.reject('Failed sessions fetch'))
                  .then(slotData => {
                      if (slotData.status !== "success" || !slotData.sessions || slotData.sessions.length === 0) {
                          Swal.fire("No Sessions", "No open Playgroup sessions.", "warning");
                           buttonElement.removeData('processing').prop('disabled', false); return; // Reset if no sessions
                      }
                       let options = slotData.sessions.map(session => { /* format */ return `<option value="${session.pg_session_id}">...</option>`; }).join('');
                       // Show Select Session Swal
                       Swal.fire({ /* ... Select Session Swal config ... */ })
                       .then((result) => {
                            buttonElement.removeData('processing').prop('disabled', false); // Reset after user interaction
                            if (result.isConfirmed) {
                                 updateAppointmentStatus({ /* ... pg_session_id ... */ }, "Assigned...", "Failed...", buttonElement);
                            } else { log("Playgroup assignment cancelled."); }
                        });
                  })
                  .catch(error => {
                       Swal.close(); console.error("Error fetching Playgroup sessions:", error);
                       Swal.fire("Error", "Failed to fetch sessions.", "error");
                       buttonElement.removeData('processing').prop('disabled', false); // Reset on error
                  });
        }

        // --- Setup Date/Time/Therapist fields for ASSIGNING from waitlist ---
        function setupAssignDateTimeFields(settings, blockedDates, sessionType) {
            const dateInput = document.getElementById("assignDate");
            const timeDropdown = document.getElementById("assignTime");
            const therapistContainer = document.getElementById("assignTherapistContainer");
            if (!dateInput || !timeDropdown || !therapistContainer) { log("Assign modal elements not found"); return; }

            const maxDate = new Date(); maxDate.setDate(maxDate.getDate() + Number(settings.max_days_advance ?? 30));

            flatpickr(dateInput, {
                 dateFormat: "Y-m-d", altInput: true, altFormat: "F j, Y",
                 minDate: "today", maxDate: maxDate, disable: blockedDates,
                 onChange: function(selectedDates, dateStr, instance) {
                     log("Assign Date selected:", dateStr);
                     timeDropdown.innerHTML = '<option value="">Loading Times...</option>';
                     timeDropdown.disabled = true;
                     therapistContainer.innerHTML = '<p class="uk-text-muted uk-text-small">Select time first.</p>';
                     if (!dateStr) return;

                     fetch(`${API_ENDPOINTS.getSlotsEnhanced}?date=${dateStr}&appointment_type=${encodeURIComponent(sessionType)}`)
                          .then(response => response.ok ? response.json() : Promise.reject('Failed slots fetch'))
                          .then(slotData => { /* ... Populate time dropdown ... */ })
                          .catch(error => { /* ... Handle time fetch error ... */ });
                 }
            });

            timeDropdown.addEventListener("change", function() {
                 const fpInstance = dateInput._flatpickr;
                 const selectedDate = fpInstance?.selectedDates[0] ? fpInstance.formatDate(fpInstance.selectedDates[0], "Y-m-d") : null;
                 const selectedTime = this.value; // HH:MM:SS
                 therapistContainer.innerHTML = '<p class="uk-text-muted uk-text-small">Loading therapists...</p>';
                 if (!selectedDate || !selectedTime) { /* ... reset therapist container ...*/ return; }
                 fetchTherapistsForAssign(selectedDate, selectedTime, sessionType);
            });
        }

        // --- Fetch Therapists specifically for the ASSIGN modal ---
        function fetchTherapistsForAssign(date, time, sessionType) {
            const container = document.getElementById("assignTherapistContainer");
            if(!container) return;
            const therapistUrl = `${API_ENDPOINTS.getTherapists}?date=${date}&time=${time}&session_type=${encodeURIComponent(sessionType)}`;
            log("Fetching therapists for Assign:", therapistUrl);

            fetch(therapistUrl)
                .then(response => response.ok ? response.json() : Promise.reject('Failed therapist fetch'))
                .then(therapistsData => {
                     log("Therapists for Assign:", therapistsData);
                     if (therapistsData.status !== "success" || !therapistsData.therapists || therapistsData.therapists.length === 0) {
                         container.innerHTML = `<p class="uk-text-danger uk-text-small">No qualified therapists available at this time.</p>`; return;
                     }
                     // Build therapist cards (only available ones)
                     let therapistCards = therapistsData.therapists.map(t => { /* ... build card ... */ return `...`; }).join('');
                     container.innerHTML = therapistCards;
                })
                .catch(error => { /* ... Handle therapist fetch error ... */ });
        }


        // --- Universal Update Status Function ---
        function updateAppointmentStatus(data, successMessage, errorMessage, buttonElement = null) {
            log("Updating status with data:", data);
            if (!buttonElement) { // Try to find button if not passed
                buttonElement = $(`button[data-id="${data.appointment_id}"]`).first(); // General selector
            }

            Swal.fire({ title: 'Processing Update...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch(API_ENDPOINTS.updateStatus, {
                method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data)
            })
            .then(response => response.ok ? response.json() : response.text().then(text => { throw new Error(`Update Error (${response.status}): ${text}`); }))
            .then(responseData => {
                if (responseData.status === "success") {
                    Swal.fire("Success!", responseData.message || successMessage, "success").then(() => location.reload());
                } else {
                    Swal.fire("Error", responseData.message || errorMessage, "error");
                    if (buttonElement && buttonElement.length > 0) buttonElement.removeData('processing').prop('disabled', false); // Reset only on error if no reload
                }
            })
            .catch(error => {
                console.error("Error updating appointment:", error);
                Swal.fire("Error", `${errorMessage}. ${error.message}`, "error");
                if (buttonElement && buttonElement.length > 0) buttonElement.removeData('processing').prop('disabled', false); // Reset on catch
            });
        }

        // --- createTimeOption helper ---
        function createTimeOption(slot_hhmmss, isPending) {
             const [hours, minutes] = slot_hhmmss.split(':');
             const hoursInt = parseInt(hours); const ampm = hoursInt >= 12 ? 'PM' : 'AM';
             const formattedHour = hoursInt % 12 === 0 ? 12 : hoursInt % 12;
             let displayTime = `${formattedHour}:${minutes} ${ampm}`;
             const option = new Option(); option.value = slot_hhmmss; option.dataset.isPending = isPending ? "true" : "false";
             if (isPending) { option.textContent = `${displayTime} (* Pending Request Exists)`; option.style.color = '#e67e22'; }
             else { option.textContent = displayTime; }
             return option;
         }

    </script>

</body>
</html>
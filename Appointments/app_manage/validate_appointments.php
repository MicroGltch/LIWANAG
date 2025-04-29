<?php
require_once "../../dbconfig.php"; // Adjust path
session_start();

// Restrict Access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../Accounts/loginpage.php"); // Adjust path
    exit();
}

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
          WHERE a.status = 'pending' -- Only pending
          ORDER BY a.date ASC, a.time ASC";


$result = $connection->query($query);
$pendingAppointments = [];
if ($result) {
    $pendingAppointments = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Handle query error if needed
    error_log("Error fetching pending appointments: " . $connection->error);
}


// Fetch WAITLISTED appointments
$waitlistQuery = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.session_type,
                         a.status as waitlist_status, -- Keep waitlist status type
                         p.first_name, p.last_name,
                         u.account_FName AS client_firstname, u.account_LName AS client_lastname
                  FROM appointments a
                  JOIN patients p ON a.patient_id = p.patient_id
                  JOIN users u ON a.account_id = u.account_ID
                  WHERE a.status IN ('waitlisted', 'Waitlisted - Any Day', 'Waitlisted - Specific Date')
                  ORDER BY a.created_at ASC"; // Order waitlist by creation time
$waitlistedAppointments = [];
$waitlistResult = $connection->query($waitlistQuery);
if ($waitlistResult) {
    $waitlistedAppointments = $waitlistResult->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error fetching waitlisted appointments: " . $connection->error);
}


// Note: Fetching all therapists here is no longer needed for the 'Approve' modal logic,
// as it's handled dynamically via AJAX based on date/time/type.
// It might still be needed if you have other filters on the page.
// $therapistQuery = "SELECT account_ID, account_FName, account_LName FROM users WHERE account_Type = 'therapist' AND account_Status = 'Active'";
// $therapistResult = $connection->query($therapistQuery);
// $therapists = $therapistResult ? $therapistResult->fetch_all(MYSQLI_ASSOC) : [];


$connection->close(); // Close DB connection
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
    <!-- FontAwesome -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script> <!-- Get your kit code -->


    <style>
        html,
        body {
            background-color: #ffffff !important;
        }

        .no-break {
            white-space: nowrap;
        }

        /* Add styles for therapist cards if needed */
        .therapist-option {
            /* existing styles */
        }

        .therapist-option label[disabled] {
            cursor: not-allowed;
        }

        .therapist-option label[disabled] input {
            cursor: not-allowed;
        }

        #pendingAppointmentsTable th,
        #pendingAppointmentsTable td,
        #waitlistedAppointmentsTable th,
        #waitlistedAppointmentsTable td {
            text-align: left;
        }

        #pendingAppointmentsTable th.uk-text-center,
        #pendingAppointmentsTable td.uk-text-center,
        #waitlistedAppointmentsTable th.uk-text-center,
        #waitlistedAppointmentsTable td.uk-text-center {
            text-align: center;
        }

        .action-btn-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }

        .action-btn-group .uk-button {
            width: 45px !important;
            height: 45px !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin: 0;
            min-height: 45px !important;
            border-radius: 4px;
        }

        /* Add these new styles to ensure consistent sizing for all action buttons */
        .action-btn-group .uk-button,
        .action-btn-group .uk-button.uk-button-secondary {
            width: 45px !important;
            min-width: 45px !important;
            max-width: 45px !important;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            padding: 0 !important;
        }

        .action-btn-group .uk-button span[uk-icon] {
            width: 20px;
            height: 20px;
        }

        /* Ensure icons are centered */
        .action-btn-group .uk-button span[uk-icon]::before {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .action-btn-group .uk-button:hover {
            transform: translateY(-1px);
            transition: transform 0.2s ease;
        }
    </style>
</head>

<body>
    <!-- Breadcrumb -->
    <ul class="uk-breadcrumb">
        <li><a href="manage_appointments.php">Manage Appointments</a></li>
        <li><span>Validate Appointments</span></li>
    </ul>

    <!-- Tabs -->
    <ul uk-tab>
        <li class="uk-active"><a href="#">Pending Appointments <span class="uk-badge"><?= count($pendingAppointments) ?></span></a></li>
        <li><a href="#">Waitlisted Appointments <span class="uk-badge"><?= count($waitlistedAppointments) ?></span></a></li>
    </ul>

    <ul class="uk-switcher uk-margin">
        <li>
            <!-- Pending Appointments Table -->
            <div class="uk-card uk-card-default uk-card-body uk-card-small">
                <h2 class="uk-card-title uk-text-bold">Validate Pending Appointments</h2>

                <!-- Custom Search and Show Entries -->
                <div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small uk-margin-small" uk-grid>
                    <div class="uk-width-1-2@s uk-width-expand@m">
                        <input type="text" id="pendingSearch" class="uk-input uk-form-small" placeholder="Search Pending...">
                    </div>
                    <div class="uk-width-auto">
                        <div class="uk-flex uk-flex-middle">
                            <label for="pendingEntries" class="uk-form-label uk-margin-small-right">Show:</label>
                            <select id="pendingEntries" class="uk-select uk-form-width-xsmall uk-form-small">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table -->
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
                            <?php foreach ($pendingAppointments as $appointment):
                                $patientPic = !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../uploads/profile_pictures/default.png';
                                $clientPic = !empty($appointment['client_picture']) ? '../../uploads/profile_pictures/' . $appointment['client_picture'] : '../../uploads/profile_pictures/default.png';
                            ?>
                                <tr>
                                    <td>
                                        <div class="uk-flex uk-flex-middle">
                                            <div class="uk-margin-small-right">
                                                <img src="<?= $patientPic ?>" alt="P" class="uk-border-circle"
                                                    style="width: 35px; height: 35px; object-fit: cover;">
                                            </div>
                                            <div class="uk-text-truncate uk-text-emphasis">
                                                <?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="uk-flex uk-flex-middle">
                                            <div class="uk-margin-small-right">
                                                <img src="<?= $clientPic ?>" alt="C" class="uk-border-circle"
                                                    style="width: 35px; height: 35px; object-fit: cover;">
                                            </div>
                                            <div class="uk-text-truncate uk-text-emphasis">
                                                <?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="uk-text-nowrap uk-text-emphasis">
                                        <?= htmlspecialchars(date("M d, Y", strtotime($appointment['date']))); ?>
                                    </td>
                                    <td class="uk-text-nowrap">
                                        <?= htmlspecialchars(date("g:i A", strtotime($appointment['time']))); ?>
                                    </td>
                                    <td class="uk-text-nowrap uk-text-muted uk-text-small">
                                        <?= htmlspecialchars(date("M d, Y g:i A", strtotime($appointment['created_at']))); ?>
                                    </td>
                                    <td class="uk-text-emphasis">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', strtolower($appointment['display_session_type'])))); ?>
                                    </td>
                                    <td class="uk-text-center">
                                        <?php if (!empty($appointment['official_referral_file'])): ?>
                                            <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['official_referral_file']); ?>"
                                                target="_blank" class="uk-link-text uk-text-primary"
                                                uk-tooltip="View Official Referral">
                                                <span uk-icon="icon: file-pdf; ratio: 1.2"></span>
                                            </a>
                                        <?php elseif (!empty($appointment['proof_of_booking_referral_file'])): ?>
                                            <a href="../../uploads/doctors_referrals/<?= htmlspecialchars($appointment['proof_of_booking_referral_file']); ?>"
                                                target="_blank" class="uk-link-text uk-text-primary"
                                                uk-tooltip="View Proof of Booking">
                                                <span uk-icon="icon: file-text; ratio: 1.2"></span>
                                            </a>
                                        <?php else: ?>
                                            <span class="uk-text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="uk-text-center">
                                        <div class="action-btn-group">
                                            <button class="uk-button uk-button-small uk-button-primary action-btn"
                                                data-id="<?= $appointment['appointment_id']; ?>"
                                                data-action="Approve"
                                                uk-tooltip="Approve and Assign Therapist">
                                                <span uk-icon="check"></span>
                                            </button>
                                            <button class="uk-button uk-button-small uk-button-danger action-btn"
                                                data-id="<?= $appointment['appointment_id']; ?>"
                                                data-action="Decline"
                                                uk-tooltip="Decline Request">
                                                <span uk-icon="ban"></span>
                                            </button>
                                            <?php if (strpos(strtolower($appointment['display_session_type']), 'rebooking') === false): ?>
                                                <button class="uk-button uk-button-small uk-button-secondary action-btn"
                                                    data-id="<?= $appointment['appointment_id']; ?>"
                                                    data-action="Waitlist"
                                                    uk-tooltip="Move to Waitlist">
                                                    <span uk-icon="history"></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pendingAppointments)): ?>
                                <tr>
                                    <td colspan="8" class="uk-text-center uk-text-muted">No pending appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </li>
        <li>
            <!-- Waitlisted Appointments Table -->
            <div class="uk-card uk-card-default uk-card-body uk-card-small">
                <h2 class="uk-card-title uk-text-bold">Waitlisted Appointments</h2>

                <!-- Custom Search and Show Entries -->
                <div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small uk-margin-small" uk-grid>
                    <div class="uk-width-1-2@s uk-width-expand@m">
                        <input type="text" id="pendingSearch" class="uk-input uk-form-small" placeholder="Search Pending...">
                    </div>
                    <div class="uk-width-auto">
                        <div class="uk-flex uk-flex-middle">
                            <label for="pendingEntries" class="uk-form-label uk-margin-small-right">Show:</label>
                            <select id="pendingEntries" class="uk-select uk-form-width-xsmall uk-form-small">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table -->
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
                            <?php foreach ($waitlistedAppointments as $appointment): ?>
                                <tr>
                                    <td class="no-break"><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                                    <td class="no-break"><?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', strtolower($appointment['session_type'])))); ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('-', ' ', strtolower($appointment['waitlist_status'])))); ?></td>
                                    <td>
                                        <?= !empty($appointment['date']) ? htmlspecialchars(date("M d, Y", strtotime($appointment['date']))) : 'Any Day'; ?>
                                    </td>
                                    <td class="no-break">
                                        <?php if (strtolower($appointment['session_type']) === 'playgroup'): ?>
                                            <button class="uk-button uk-button-primary uk-button-small assign-playgroup-btn" data-id="<?= $appointment['appointment_id']; ?>" uk-tooltip="Assign Playgroup Slot">
                                                Assign Slot
                                            </button>
                                        <?php else: ?>
                                            <button class="uk-button uk-button-primary uk-button-small assign-btn" data-id="<?= $appointment['appointment_id']; ?>" uk-tooltip="Assign Date, Time & Therapist">
                                                Assign Slot
                                            </button>
                                        <?php endif; ?>
                                        <button class="uk-button uk-button-danger uk-button-small remove-waitlist-btn" data-id="<?= $appointment['appointment_id']; ?>" uk-tooltip="Remove from Waitlist">
                                            <span uk-icon="trash"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($waitlistedAppointments)): ?>
                                <tr>
                                    <td colspan="6" class="uk-text-center uk-text-muted">No waitlisted appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </li>
    </ul>


    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        const API_ENDPOINTS = {
            getDetails: '../app_data/get_appointment_details.php',
            getTherapists: '../app_data/get_available_therapists.php',
            getPlaygroupSessions: '../app_data/get_open_playgroup_sessions.php',
            getTimetableSettings: '../app_data/get_timetable_settings.php', // Needed for assign modal
            getSlotsEnhanced: '../app_data/get_available_slots_enhanced.php', // Needed for assign modal
            updateStatus: '../app_process/update_appointment_status.php' // Adjust path if needed
        };

        // --- Helper Functions ---
        function log(...args) {
            console.log('[ValidateAppt]', ...args);
        }

        // Helper to capitalize first letter
        function ucfirst(str) {
            return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
        }


        // *** DEFINE resetButtonState HERE (Moved Up) ***
        function resetButtonState(buttonElement) {
            if (buttonElement && buttonElement.length > 0) {
                log("Resetting button state for:", buttonElement.data('id'), buttonElement.data('action') || buttonElement.attr('class'));
                // Use .prop() for boolean attributes like disabled
                buttonElement.removeData('processing').prop('disabled', false);
            } else {
                log("Attempted to reset state for invalid button element.");
            }
        }
        // *** END Definition ***

        // --- Main Document Ready ---
        $(document).ready(function() {
            log("Document Ready. Initializing...");

            // Add global error handler for debugging JS issues
            window.onerror = function(message, source, lineno, colno, error) {
                console.error("JS Error:", message, "at", source, ":", lineno);
                log("Error object:", error); // Log the full error object if available
                // Optionally show a user-friendly message
                // Swal.fire('Script Error', 'An unexpected error occurred. Please check the console or contact support.', 'error');
                return true; // Prevent default browser error handling
            };

            // --- Initialize DataTables ---
            try {
                const pendingTable = $('#pendingAppointmentsTable').DataTable({
                    pageLength: 10, // Default length
                    lengthChange: false, // Disable the default "Show entries" dropdown
                    order: [
                        [2, 'asc'], // Order by Date (column index 2)
                        [3, 'asc'] // Then by Time (column index 3)
                    ],
                    dom: 'lrtip', // Keep table, info, and pagination; remove default search and length menu
                    language: {
                        // Customize text if needed
                    }
                });

                // Custom Search for Pending Appointments
                $('#pendingSearch').on('keyup', function() {
                    pendingTable.search(this.value).draw();
                });

                // Custom Entries Dropdown for Pending Appointments
                $('#pendingEntries').on('change', function() {
                    pendingTable.page.len(this.value).draw();
                });

                // --- Initialize Waitlist DataTable (if table exists) ---
                if ($('#waitlistedAppointmentsTable').length > 0) {
                    const waitlistedTable = $('#waitlistedAppointmentsTable').DataTable({
                        pageLength: 10,
                        lengthChange: false, // Disable the default "Show entries" dropdown
                        order: [
                            [0, 'asc'] // Order by Patient Name (column 0) by default
                        ],
                        dom: 'lrtip',
                        language: {
                            // Customize text if needed
                        }
                    });

                    $('#waitlistSearch').on('keyup', function() {
                        waitlistedTable.search(this.value).draw();
                    });

                    $('#waitlistEntries').on('change', function() {
                        waitlistedTable.page.len(this.value).draw();
                    });
                } else {
                    log("Waitlist table not found, skipping initialization.");
                }
            } catch (e) {
                console.error("Error initializing DataTables:", e);
                // Handle error - maybe show a message to the user
            }


            // --- Event Delegation for Action Buttons ---
            $(document).on('click', '.action-btn', function(e) {
                e.preventDefault(); // Prevent default button behavior
                const button = $(this); // Store reference to the button

                // Prevent double clicks
                if (button.data('processing')) {
                    log("Action button already processing, ignoring click.");
                    return;
                }
                button.data('processing', true); // Set flag
                log("Action button clicked!");

                let appointmentId = button.attr('data-id');
                let action = button.attr('data-action');
                log(`Action: ${action}, Appointment ID: ${appointmentId}`);

                // Fetch appointment details FIRST
                fetch(`../app_data/get_appointment_details.php?appointment_id=${appointmentId}`)
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error(`Network error fetching details (${response.status}): ${text}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => { // Response from get_appointment_details.php
                        log("Appointment Details Fetched:", data);

                        if (data.status !== "success") {
                            Swal.fire("Error", data.message || "Failed to fetch appointment details.", "error");
                            button.removeData('processing'); // Clear flag on error
                            return;
                        }

                        // ** Check essential details **
                        if (!data.details || !data.details.date || !data.details.time || !data.details.raw_session_type) { // ** Check raw_session_type **
                            console.error("Essential details missing:", data.details);
                            Swal.fire("Error", "Incomplete appointment details received (date, time, or type missing).", "error");
                            button.removeData('processing'); // Clear flag
                            return;
                        }

                        // --- Details OK, proceed ---
                        // Construct details HTML (Use display_session_type for display)
                        let rawDate = data.details.date;
                        let rawTime = data.details.time;
                        let displaySessionType = data.details.display_session_type || data.details.raw_session_type; // Fallback
                        let datetimeStr = `${rawDate}T${rawTime}`;
                        let formattedDate = 'Invalid Date';
                        let formattedTime = 'Invalid Time';
                        try {
                            let datetimeObj = new Date(datetimeStr);
                            if (!isNaN(datetimeObj)) { // Check if date is valid
                                formattedDate = datetimeObj.toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric'
                                });
                                formattedTime = datetimeObj.toLocaleTimeString([], {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hour12: true
                                });
                            }
                        } catch (dateError) {
                            console.error("Error parsing date/time:", dateError);
                        }


                        let detailsHtml = `
                         <div style="text-align: left; margin-bottom: 15px;">
                             <p><strong>Patient:</strong> ${data.details.patient_name || 'N/A'}</p>
                             <p><strong>Client:</strong> ${data.details.client_name || 'N/A'}</p>
                             <p><strong>Date:</strong> ${formattedDate}</p>
                             <p><strong>Time:</strong> ${formattedTime}</p>
                             <p><strong>Session Type:</strong> ${ucfirst(displaySessionType.replace(/_/g, ' '))}</p> <!-- Use display type -->
                             ${data.details.rebooked_by_name ? `<p><strong>Rebooked By:</strong> ${data.details.rebooked_by_name}</p>` : ''}
                             <!-- Removed status from here as it's always pending initially -->
                             <p><strong>Referral:</strong> ${data.details.doctor_referral || 'N/A'}</p>
                         </div>
                         <hr>
                     `;


                        // Route to the correct handler
                        if (action === "Approve") {
                            // Use raw_session_type for logic checks
                            if (data.details.raw_session_type && data.details.raw_session_type.toLowerCase() === "playgroup") {
                                handlePlaygroupApproval(appointmentId, data, detailsHtml, button); // Pass button
                            } else {
                                handleApproveAction(appointmentId, data, detailsHtml, button); // Pass button
                            }
                        } else if (action === "Decline") {
                            handleDeclineAction(appointmentId, detailsHtml, button); // Pass button
                        } else if (action === "Waitlist") {
                            // Check if it's rebooking before allowing waitlist (use display_session_type for check)
                            if (displaySessionType && displaySessionType.toLowerCase().includes('rebooking')) {
                                Swal.fire("Action Not Allowed", "Cannot waitlist a rebooking request.", "warning");
                                button.removeData('processing'); // Reset flag
                            } else {
                                handleWaitlistAction(appointmentId, detailsHtml, button); // Pass button
                            }
                        } else {
                            console.error("Unrecognized action:", action);
                            Swal.fire("Error", "Invalid action detected.", "error");
                            button.removeData('processing'); // Reset flag
                        }
                        // Note: Flag reset is now primarily handled within the specific action handlers or updateAppointmentStatus

                    })
                    .catch(error => {
                        console.error("Error fetching appointment details:", error);
                        Swal.fire("Error Fetching Details", "Failed to fetch appointment details. " + error.message, "error");
                        button.removeData('processing'); // Reset flag on CATCH
                    });
            }); // End '.action-btn' listener

            // --- Event Delegation for Waitlist Assign Buttons ---
            $(document).on('click', '.assign-btn', function(e) {
                e.preventDefault();
                let appointmentId = $(this).data('id');
                log(`Assign button clicked for Waitlist ID: ${appointmentId}`);
                handleAssignAction(appointmentId); // Call specific handler
            });

            $(document).on('click', '.assign-playgroup-btn', function(e) {
                e.preventDefault();
                let appointmentId = $(this).data('id');
                log(`Assign Playgroup button clicked for Waitlist ID: ${appointmentId}`);
                handleAssignPlaygroupAction(appointmentId); // Call specific handler
            });

            // --- Event Delegation for Remove from Waitlist Button ---
            $(document).on('click', '.remove-waitlist-btn', function(e) {
                e.preventDefault();
                let appointmentId = $(this).data('id');
                log(`Remove Waitlist button clicked for ID: ${appointmentId}`);
                handleRemoveWaitlistAction(appointmentId, $(this)); // Pass button reference
            });


            log("Event listeners attached.");
        }); // --- End Document Ready ---


        // ==================================
        // --- Action Handler Functions ---
        // ==================================

        // --- APPROVE ACTION (Non-Playgroup) ---
        // Now receives the button element to manage the processing flag
        function handleApproveAction(appointmentId, appointmentData, detailsHtml, buttonElement) {
            const details = appointmentData?.details;
            if (!details || !details.date || !details.time || !details.raw_session_type) { // Check raw type
                console.error("Approve Error: Essential details missing", details);
                Swal.fire("Error", "Cannot approve: Incomplete appointment details.", "error");
                buttonElement.removeData('processing'); // Reset flag
                return;
            }

            const sessionType = details.raw_session_type; // Use raw type for logic
            const appointmentDate = details.date;
            const appointmentTime = details.time;

            log(`Handling Approve for Appt ID: ${appointmentId}, Type: ${sessionType}, Date: ${appointmentDate}, Time: ${appointmentTime}`);

            // Basic format validation
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            const timeRegex = /^\d{2}:\d{2}:\d{2}$/;
            if (!dateRegex.test(appointmentDate) || !timeRegex.test(appointmentTime)) {
                console.error("Approve Error: Invalid date/time format", {
                    date: appointmentDate,
                    time: appointmentTime
                });
                Swal.fire("Error", "Cannot approve: Invalid date or time format.", "error");
                buttonElement.removeData('processing'); // Reset flag
                return;
            }

            // Construct URL
            const therapistUrl = `../app_data/get_available_therapists.php?date=${appointmentDate}&time=${appointmentTime}&session_type=${encodeURIComponent(sessionType)}`;
            log("Fetching therapists using URL:", therapistUrl);

            // Show loading specifically for therapist fetch
            Swal.fire({
                title: 'Finding Available Therapists...',
                text: 'Please wait.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });


            // Fetch therapists
            fetch(therapistUrl)
                .then(response => response.ok ? response.json() : response.text().then(text => {
                    throw new Error(`Therapist Fetch Error (${response.status}): ${text}`);
                }))
                .then(therapistsData => {
                    log("Available/Qualified Therapists Response:", therapistsData);

                    // Reset flag now that fetch is complete
                    buttonElement.removeData('processing');

                    if (therapistsData.status !== "success") {
                        Swal.fire("Error", therapistsData.message || "Could not retrieve therapist list.", "error");
                        return;
                    }
                    // ** Check if the FILTERED list is empty **
                    if (!therapistsData.therapists || therapistsData.therapists.length === 0) {
                        Swal.fire({
                            title: "No Suitable Therapists Available",
                            html: `No qualified therapists for <strong>${sessionType}</strong> were found available on <strong>${appointmentDate}</strong> at <strong>${appointmentTime.substring(0,5)}</strong>. <br><br>Please check therapist schedules or consider declining/waitlisting this request.`, // Adjusted message
                            icon: "warning"
                        });
                        return;
                    }

                    // --- Build therapist cards (SIMPLIFIED - all therapists are available) ---
                    let therapistCards = therapistsData.therapists.map(t => {
                        // All therapists in this list have status "Available"
                        const schedule = t.schedule || "No schedule info";
                        return `
                    <div class="therapist-option" style="padding:10px; border:1px solid #ccc; background-color: #fff; border-radius:8px; margin-bottom:10px;">
                        <label style="display:flex; gap:10px; align-items:flex-start; cursor:pointer;" title="Available for this slot">
                            <input type="radio" name="therapist" value="${t.id}" class="uk-radio" style="margin-top:5px;" /> <!-- No need for disabled check -->
                            <div class="uk-width-expand">
                                <div class="uk-text-bold">${t.name}</div>
                                <!-- Optional: Can still show status if desired, but it will be 'Available' -->
                                <!-- <div style="color:#27ae60; font-weight: bold;" class="uk-text-small">Available</div> -->
                                <div class="uk-text-meta uk-text-small">Schedule: ${schedule}</div>
                            </div>
                        </label>
                    </div>`;
                    }).join('');

                    // --- Show Swal to select therapist ---
                    Swal.fire({
                        title: "Assign Available Therapist", // Updated title
                        html: detailsHtml +
                            `<label class="uk-form-label uk-text-bold">Select Therapist (${ucfirst(sessionType.replace(/_/g, ' '))}):</label>
                       <div id="therapistOptions" style="text-align:left; max-height:350px; overflow-y:auto; margin-top:10px; border: 1px solid #e5e5e5; padding: 10px;">
                           ${therapistCards}
                       </div>`,
                        width: '600px',
                        showCancelButton: true,
                        confirmButtonText: "Approve & Assign",
                        cancelButtonText: "Cancel",
                        preConfirm: () => {
                            // ** SIMPLIFIED preConfirm: Just need to ensure *one* is selected **
                            const selected = document.querySelector('#therapistOptions input[name="therapist"]:checked');
                            if (!selected) {
                                Swal.showValidationMessage("Please select a therapist.");
                                return false;
                            }
                            return {
                                therapistId: selected.value
                            };
                        }
                    }).then((result) => {
                        // Flag was already reset after fetch
                        if (result.isConfirmed) {
                            updateAppointmentStatus({
                                appointment_id: appointmentId,
                                status: "approved",
                                therapist_id: result.value.therapistId
                            }, "Approval successful...", "Failed to approve...");
                        } else {
                            log("Therapist assignment cancelled by user.");
                        }
                    });

                })
                .catch(error => {
                    Swal.close(); // Close the loading Swal if it was shown
                    console.error("Error fetching or processing therapists:", error);
                    Swal.fire("Error", "An error occurred fetching available therapists. " + error.message, "error");
                    buttonElement.removeData('processing'); // Ensure flag reset on error
                });
        }


        // --- APPROVE ACTION (Playgroup) ---
        function handlePlaygroupApproval(appointmentId, appointmentData, detailsHtml, buttonElement) {
            log(`Handling Playgroup Approve for Appt ID: ${appointmentId}`);
            Swal.fire({
                title: 'Finding Playgroup Sessions...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch("../app_data/get_open_playgroup_sessions.php")
                .then(response => response.ok ? response.json() : Promise.reject('Network error fetching sessions'))
                .then(slotData => {
                    if (slotData.status !== "success" || !slotData.sessions || slotData.sessions.length === 0) {
                        Swal.fire("No Available Sessions", "No open Playgroup sessions found. Please create one on the <a href='playgroup_dashboard.php'>Playgroup Dashboard</a> first.", "warning");
                        buttonElement.removeData('processing'); // Reset flag
                        return;
                    }

                    let slotOptions = slotData.sessions.map(slot => {
                        // Format time for display
                        let displayTime = slot.time; // Fallback
                        try {
                            let timeObj = new Date(`1970-01-01T${slot.time}`);
                            displayTime = timeObj.toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });
                        } catch {}
                        return `<option value="${slot.pg_session_id}">
                        ${slot.date} at ${displayTime} (${slot.current_count}/${slot.max_capacity})
                    </option>`;
                    }).join('');

                    Swal.fire({
                        title: "Assign to Playgroup Session",
                        html: detailsHtml +
                            `<label class="uk-form-label uk-text-bold">Select Open Playgroup Session:</label>
                          <select id="playgroupSlotSelect" class="uk-select swal2-select" required> <!-- Use Swal class for better styling -->
                              <option value="" disabled selected>-- Select a Session --</option>
                              ${slotOptions}
                          </select>`,
                        showCancelButton: true,
                        confirmButtonText: "Approve & Assign",
                        cancelButtonText: "Cancel",
                        preConfirm: () => {
                            let selectedSlot = document.getElementById("playgroupSlotSelect").value;
                            if (!selectedSlot) {
                                Swal.showValidationMessage("Please select a Playgroup session.");
                                return false;
                            }
                            return {
                                selectedSlot
                            };
                        }
                    }).then((result) => {
                        buttonElement.removeData('processing'); // Reset flag
                        if (result.isConfirmed) {
                            updateAppointmentStatus({
                                appointment_id: appointmentId,
                                status: "approved", // lowercase
                                pg_session_id: result.value.selectedSlot,
                                // Reset date/time to null if assigning PG? Or keep original request?
                                // Let's assume we keep original request date/time as metadata,
                                // but the pg_session_id dictates the actual schedule slot.
                                // date: null, // Optional: Clear original date/time if needed
                                // time: null
                            }, "Playgroup slot assigned successfully", "Failed to assign playgroup slot");
                        } else {
                            log("Playgroup assignment cancelled by user.");
                        }
                    });
                })
                .catch(error => {
                    Swal.close(); // Close loading
                    console.error("Error fetching playgroup slots:", error);
                    Swal.fire("Error", "Failed to fetch available Playgroup sessions.", "error");
                    buttonElement.removeData('processing'); // Reset flag
                });
        }


        // --- DECLINE ACTION ---
        function handleDeclineAction(appointmentId, detailsHtml, buttonElement) {
            log(`Handling Decline for Appt ID: ${appointmentId}`);
            Swal.fire({
                title: "Decline Appointment?",
                html: detailsHtml +
                    `<label class="uk-form-label uk-text-bold">Reason for Declining:</label>
                  <select id="declineReasonSelect" class="uk-select swal2-select"> <!-- Use Swal class -->
                      <option value="">-- Select Reason (Optional) --</option>
                      <option value="Fully Booked">Fully Booked on Requested Date/Time</option>
                      <option value="Therapist Unavailable">Therapist Specialty Unavailable</option>
                      <option value="Schedule Conflict">Internal Schedule Conflict</option>
                      <option value="Client Request">Client Requested Cancellation</option>
                      <option value="Incomplete Information">Incomplete Information Provided</option>
                      <option value="Other">Other (Specify Below)</option>
                  </select>
                  <textarea id="declineReasonText" class="uk-textarea swal2-textarea" placeholder="Specify reason if 'Other' or add details..." style="display:none; margin-top: 10px;"></textarea>`,
                showCancelButton: true,
                confirmButtonText: "Confirm Decline",
                cancelButtonText: "Cancel",
                didOpen: () => {
                    const reasonSelect = document.getElementById("declineReasonSelect");
                    const reasonText = document.getElementById("declineReasonText");
                    reasonSelect.addEventListener("change", function() {
                        reasonText.style.display = (this.value === "Other") ? "block" : "none";
                    });
                },
                preConfirm: () => {
                    const reasonSelect = document.getElementById("declineReasonSelect");
                    const reasonText = document.getElementById("declineReasonText");
                    let finalReason = reasonSelect.value;
                    if (finalReason === "Other") {
                        finalReason = reasonText.value.trim();
                        if (!finalReason) {
                            Swal.showValidationMessage("Please specify the reason if 'Other' is selected.");
                            return false;
                        }
                    }
                    return {
                        reason: finalReason || "No reason specified"
                    }; // Default if somehow empty
                }
            }).then((result) => {
                buttonElement.removeData('processing'); // Reset flag
                if (result.isConfirmed) {
                    updateAppointmentStatus({
                        appointment_id: appointmentId,
                        status: "declined", // lowercase
                        validation_notes: result.value.reason
                    }, "Appointment declined", "Failed to decline appointment");
                } else {
                    log("Decline cancelled by user.");
                }
            });
        }


        // --- WAITLIST ACTION ---
        function handleWaitlistAction(appointmentId, detailsHtml, buttonElement) {
            log(`Handling Waitlist: ID=${appointmentId}`); // <<< CONFIRM THIS LOG APPEARS
            Swal.fire({
                title: "Move to Waitlist?",
                html: detailsHtml + `<label class="uk-form-label uk-text-bold">Reason (Required):</label><textarea id="waitlistReason" class="uk-textarea swal2-textarea" placeholder="e.g., Fully booked..."></textarea>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: "Confirm Waitlist",
                cancelButtonText: "Cancel",
                preConfirm: () => {
                    const reason = document.getElementById("waitlistReason")?.value.trim(); // Safe access
                    if (!reason) {
                        Swal.showValidationMessage("Reason required.");
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                resetButtonState(buttonElement); // Reset after interaction
                if (result.isConfirmed) {
                    log("Waitlist confirmed, calling updateAppointmentStatus...");
                    updateAppointmentStatus({
                            appointment_id: appointmentId,
                            status: "waitlisted",
                            validation_notes: result.value
                        },
                        "Moved to waitlist", "Failed to waitlist", buttonElement
                    );
                } else {
                    log("Waitlist cancelled.");
                }
            });
        }

        // --- REMOVE FROM WAITLIST ACTION ---
        function handleRemoveWaitlistAction(appointmentId, buttonElement) {
            log(`Handling Remove Waitlist for ID: ${appointmentId}`);
            // Optional: Fetch details first to show confirmation
            // For simplicity, directly ask for confirmation
            Swal.fire({
                title: 'Remove from Waitlist?',
                text: "Are you sure you want to permanently remove this waitlist request?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Remove It',
                cancelButtonText: 'No, Keep It',
                confirmButtonColor: '#d33', // Red for destructive action
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Use update status endpoint with a 'removed' or 'cancelled' status
                    // Or have a dedicated endpoint? Let's use update with 'cancelled' for now.
                    updateAppointmentStatus({
                        appointment_id: appointmentId,
                        status: "cancelled", // Or a specific 'removed_waitlist' status if you prefer
                        validation_notes: "Removed from waitlist by admin/staff."
                    }, "Removed from waitlist successfully", "Failed to remove from waitlist");
                }
            });
        }


        // --- ASSIGN SLOT FROM WAITLIST (Non-Playgroup) ---
        function handleAssignAction(appointmentId, buttonElement) { // Ensure buttonElement is passed
            log(`Handling Assign Slot from Waitlist: ID=${appointmentId}`);
            let originalAppointmentData = null;
            // Show initial loading indicator
            Swal.fire({
                title: 'Loading...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                showConfirmButton: false
            });

            fetch(`${API_ENDPOINTS.getDetails}?appointment_id=${appointmentId}`)
                .then(response => response.ok ? response.json() : Promise.reject('Failed details fetch'))
                .then(appointmentData => {
                    if (appointmentData.status !== "success" || !appointmentData.details) {
                        throw new Error(appointmentData.message || "Could not load details.");
                    }
                    originalAppointmentData = appointmentData;
                    // Update loading message before next fetch
                    Swal.update({
                        title: 'Loading Settings...'
                    });
                    return fetch(API_ENDPOINTS.getTimetableSettings); // Chain next fetch
                })
                .then(response => response.ok ? response.json() : Promise.reject('Failed settings fetch'))
                .then(settingsData => {
                    if (settingsData.status !== "success" || !settingsData.settings) {
                        throw new Error(settingsData.message || "Could not load settings.");
                    }
                    const settings = settingsData.settings;
                    const blockedDates = settings.blocked_dates || []; // Already decoded by PHP
                    const patientName = originalAppointmentData.details.patient_name || 'N/A';
                    const sessionType = originalAppointmentData.details.raw_session_type || 'N/A';

                    // Show Assign Swal - This returns a Promise
                    return Swal.fire({ // *** Return the Swal promise ***
                        title: "Assign New Slot",
                        html: `
                      <div style="text-align: left; margin-bottom: 15px; font-size: 0.9em;">
                          <p><strong>Patient:</strong> ${patientName}</p>
                          <p><strong>Session Type:</strong> ${ucfirst(sessionType.replace(/[-_]/g, ' '))}</p><hr style='margin: 10px 0;'>
                      </div>
                      <div class="uk-form-stacked">
                          <div class="uk-margin"><label class="uk-form-label uk-text-bold" for="assignDate">New Date:</label><div class="uk-form-controls"><input type="text" id="assignDate" class="uk-input swal2-input" placeholder="Select Date..."></div></div>
                          <div class="uk-margin"><label class="uk-form-label uk-text-bold" for="assignTime">New Time:</label><div class="uk-form-controls"><select id="assignTime" class="uk-select swal2-select" disabled><option value="">-- Select Date --</option></select></div></div>
                          <div class="uk-margin"><label class="uk-form-label uk-text-bold">Assign Therapist:</label><div id="assignTherapistContainer"><p class="uk-text-muted uk-text-small">Select date & time.</p></div></div>
                      </div>`,
                        width: '600px',
                        allowOutsideClick: false, // Prevent closing by clicking outside during selection
                        showCancelButton: true,
                        confirmButtonText: "Assign & Approve",
                        cancelButtonText: "Cancel",
                        didOpen: () => {
                            setupAssignDateTimeFields(settings, blockedDates, sessionType);
                        },
                        preConfirm: () => {
                            const dateInput = document.getElementById("assignDate");
                            const time = document.getElementById("assignTime").value;
                            const selectedTherapist = document.querySelector('#assignTherapistContainer input[name="therapist"]:checked');
                            const fpInstance = dateInput._flatpickr;
                            const selectedDate = fpInstance?.selectedDates[0] ? fpInstance.formatDate(fpInstance.selectedDates[0], "Y-m-d") : null;

                            if (!selectedDate || !time || !selectedTherapist) {
                                Swal.showValidationMessage("Select date, time, & therapist.");
                                return false;
                            }
                            return {
                                date: selectedDate,
                                time: time,
                                therapist_id: selectedTherapist.value
                            };
                        }
                    }); // *** End of Swal.fire call ***
                }) // *** End of .then(settingsData => ...) ***
                .then((result) => { // *** This .then() receives the result of the Swal.fire promise ***
                    // Reset button state HERE, after the user has interacted with the modal
                    resetButtonState(buttonElement);

                    if (result.isConfirmed) {
                        log("Assign Swal confirmed. Calling updateAppointmentStatus...");
                        updateAppointmentStatus({
                            appointment_id: appointmentId,
                            status: "approved",
                            date: result.value.date,
                            time: result.value.time,
                            therapist_id: result.value.therapist_id,
                            validation_notes: "Assigned from waitlist."
                        }, "Assigned successfully", "Failed assignment", buttonElement);
                    } else {
                        log("Assignment cancelled by user (Cancel button or Esc/Outside click).");
                        // No further action needed, button state already reset
                    }
                })
                .catch(error => { // Catches errors from fetch OR "No sessions found" rejection
                    Swal.close(); // Close any loading Swal if fetches failed
                    console.error("Error during waitlist assignment process:", error);
                    // Avoid showing Swal again if it was already shown (like "No Sessions") unless it's a new error
                    if (error !== "No sessions found") {
                        Swal.fire("Error", "Could not proceed with assignment. " + error.message, "error");
                    }
                    resetButtonState(buttonElement); // Reset button state on any error in the chain
                });
        }


        // --- Setup Date/Time/Therapist fields for ASSIGNING from waitlist ---
        function setupAssignDateTimeFields(settings, blockedDates, sessionType) {
            const dateInput = document.getElementById("assignDate");
            const timeDropdown = document.getElementById("assignTime");
            const therapistContainer = document.getElementById("assignTherapistContainer");

            if (!dateInput) {
                log("AssignDate input not found");
                return;
            }

            const minDate = new Date(); // Allow today? Or use settings.min_days_advance? Let's allow today.
            const maxDate = new Date();
            // minDate.setDate(minDate.getDate() + Number(settings.min_days_advance));
            maxDate.setDate(maxDate.getDate() + Number(settings.max_days_advance));

            flatpickr(dateInput, {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y", // User friendly format
                minDate: "today", // Allow today
                maxDate: maxDate,
                disable: blockedDates,
                onChange: function(selectedDates, dateStr, instance) {
                    // dateStr is the YYYY-MM-DD format because of dateFormat
                    log("Assign Date selected:", dateStr);
                    timeDropdown.innerHTML = '<option value="">Loading Times...</option>';
                    timeDropdown.disabled = true;
                    therapistContainer.innerHTML = '<p class="uk-text-muted uk-text-small">Select time first.</p>';

                    if (!dateStr) return;

                    // Fetch available SLOTS for this date & session type using the enhanced endpoint
                    fetch(`../app_data/get_available_slots_enhanced.php?date=${dateStr}&appointment_type=${encodeURIComponent(sessionType)}`)
                        .then(response => response.ok ? response.json() : Promise.reject('Failed to fetch slots'))
                        .then(slotData => {
                            log("Slots for Assign:", slotData);
                            timeDropdown.innerHTML = '<option value="" disabled selected>-- Select Time --</option>';
                            if (slotData.status === "success") {
                                const allSlots = [...(slotData.available_slots || []), ...(slotData.pending_slots || [])];
                                if (allSlots.length > 0) {
                                    allSlots.sort(); // Sort combined slots
                                    allSlots.forEach(slot => {
                                        const isPending = slotData.pending_slots?.includes(slot);
                                        const option = createTimeOption(slot, isPending); // Use helper
                                        timeDropdown.appendChild(option);
                                    });
                                    timeDropdown.disabled = false;
                                } else {
                                    timeDropdown.innerHTML = '<option value="">No Slots Found</option>';
                                }
                            } else {
                                timeDropdown.innerHTML = `<option value="">${slotData.message || 'Error Loading'}</option>`;
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching slots for assignment:", error);
                            timeDropdown.innerHTML = '<option value="">Error Loading</option>';
                        });
                }
            });

            // Time dropdown change listener
            timeDropdown.addEventListener("change", function() {
                const selectedDate = dateInput._flatpickr.selectedDates[0] ? dateInput._flatpickr.formatDate(dateInput._flatpickr.selectedDates[0], "Y-m-d") : null;
                const selectedTime = this.value; // Should be HH:MM:SS
                therapistContainer.innerHTML = '<p class="uk-text-muted uk-text-small">Loading therapists...</p>';

                if (!selectedDate || !selectedTime) {
                    therapistContainer.innerHTML = '<p class="uk-text-muted uk-text-small">Select date and time first.</p>';
                    return;
                }

                // Fetch available therapists for the selected date/time/type
                fetchTherapistsForAssign(selectedDate, selectedTime, sessionType); // Use a specific function
            });
        }

        // --- Fetch Therapists specifically for the ASSIGN modal ---
        function fetchTherapistsForAssign(date, time, sessionType) {
            const container = document.getElementById("assignTherapistContainer");
            if (!container) return;

            const therapistUrl = `../app_data/get_available_therapists.php?date=${date}&time=${time}&session_type=${encodeURIComponent(sessionType)}`;
            log("Fetching therapists for Assign using URL:", therapistUrl);

            fetch(therapistUrl)
                .then(response => response.ok ? response.json() : Promise.reject('Failed to fetch therapists'))
                .then(therapistsData => {
                    log("Therapists for Assign:", therapistsData);
                    if (therapistsData.status !== "success" || !therapistsData.therapists || therapistsData.therapists.length === 0) {
                        container.innerHTML = `<p class="uk-text-danger uk-text-small">No qualified therapists available at this time.</p>`;
                        return;
                    }

                    // Build therapist cards (similar to handleApproveAction)
                    let therapistCards = therapistsData.therapists.map(t => {
                        const status = t.status || "Unknown";
                        const isAvailable = t.status === "Available";
                        const statusColor = isAvailable ? "#27ae60" : "#e74c3c";
                        const disabled = !isAvailable ? "disabled" : "";
                        const schedule = t.schedule || "No schedule info";
                        const tooltip = !isAvailable ? `Reason: ${status}` : "Available for this slot";
                        return `
                          <div class="therapist-option" style="padding:8px; border:1px solid ${disabled ? '#eee' : '#ccc'}; background-color: ${disabled ? '#fefefe' : '#fff'}; border-radius:6px; margin-bottom:8px; opacity: ${disabled ? 0.7 : 1};">
                              <label style="display:flex; gap:8px; align-items:flex-start; cursor:${disabled ? 'not-allowed' : 'pointer'};" title="${tooltip}">
                                  <input type="radio" name="therapist" value="${t.id}" class="uk-radio" style="margin-top:4px;" ${disabled} />
                                  <div class="uk-width-expand uk-text-small">
                                      <div class="uk-text-bold uk-text-emphasis">${t.name}</div>
                                      <div style="color:${statusColor}; font-weight: bold;">${status}</div>
                                      <div class="uk-text-meta">Schedule: ${schedule}</div>
                                  </div>
                              </label>
                          </div>`;
                    }).join('');
                    container.innerHTML = therapistCards;

                })
                .catch(error => {
                    console.error("Error fetching therapists for assignment:", error);
                    container.innerHTML = `<p class="uk-text-danger uk-text-small">Error loading therapists.</p>`;
                });
        }


        // --- ASSIGN SLOT FROM WAITLIST (Playgroup) ---
        // Apply similar logic: reset button after Swal interaction or in catch
        function handleAssignPlaygroupAction(appointmentId, buttonElement) {
            log(`Handling Assign Playgroup from Waitlist: ID=${appointmentId}`);
            Swal.fire({
                title: 'Loading...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                showConfirmButton: false
            });

            fetch(API_ENDPOINTS.getPlaygroupSessions)
                .then(response => response.ok ? response.json() : Promise.reject('Failed sessions fetch'))
                .then(slotData => {
                    if (slotData.status !== "success" || !slotData.sessions || slotData.sessions.length === 0) {
                        Swal.fire("No Sessions", "No open sessions.", "warning");
                        resetButtonState(buttonElement); // Reset on fetch error/no data
                        return Promise.reject("No sessions found"); // Stop promise chain
                    }
                    let options = slotData.sessions.map(session => {
                        /* format */
                        return `<option value="${session.pg_session_id}">...</option>`;
                    }).join('');
                    // Show Select Session Swal and RETURN its promise
                    return Swal.fire({
                        title: "Assign to Playgroup Session",
                        html: `<p>Assigning ID: ${appointmentId}</p><label ...>Select Session:</label><select id="pgSlotSelectAssign">...</select>`,
                        showCancelButton: true,
                        confirmButtonText: "Assign & Approve",
                        cancelButtonText: "Cancel",
                        preConfirm: () => {
                            /* validate selection */
                            return {
                                pg_session_id: selected
                            };
                        }
                    });
                })
                .then((result) => { // Receives result from Swal
                    resetButtonState(buttonElement); // Reset after interaction
                    if (result.isConfirmed) {
                        log("Assign Playgroup Swal confirmed.");
                        updateAppointmentStatus({
                            appointment_id: appointmentId,
                            status: "approved",
                            pg_session_id: result.value.pg_session_id,
                            validation_notes: "Assigned from waitlist."
                        }, "Assigned...", "Failed...", buttonElement);
                    } else {
                        log("Playgroup assignment cancelled.");
                    }
                })
                .catch(error => { // Catches errors from fetch OR "No sessions found" rejection
                    Swal.close(); // Close any loading Swal if fetches failed
                    console.error("Error assigning playgroup from waitlist:", error);
                    // Avoid showing Swal again if it was already shown (like "No Sessions") unless it's a new error
                    if (error !== "No sessions found") {
                        Swal.fire("Error", "Failed to assign Playgroup slot.", "error");
                    }
                    resetButtonState(buttonElement); // Reset on error/catch
                });
        }


        // --- Universal Update Status Function ---
        function updateAppointmentStatus(data, successMessage, errorMessage, buttonElement = null) {
            log("Updating status with data:", data);
            if (!buttonElement || buttonElement.length === 0) {
                buttonElement = $(`button[data-id="${data.appointment_id}"]`).first();
            }

            Swal.fire({
                title: 'Processing Update...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                showConfirmButton: false
            });

            // *** Check endpoint URL again ***
            log("Calling updateStatus endpoint:", API_ENDPOINTS.updateStatus);

            fetch(API_ENDPOINTS.updateStatus, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(data)
                })
                // *** ADD MORE LOGGING TO FETCH RESPONSE ***
                .then(response => {
                    log("Update Status Raw Response Status:", response.status);
                    if (!response.ok) {
                        // Try to get text even for errors
                        return response.text().then(text => {
                            log("Update Status Raw Error Response Text:", text);
                            throw new Error(`Update Error (${response.status})`);
                        });
                    }
                    // If OK, try to parse JSON
                    return response.json().catch(parseError => {
                        log("Update Status JSON Parse Error:", parseError);
                        // We might have received OK status but non-JSON body (e.g., HTML redirect)
                        throw new Error("Invalid JSON received from update status endpoint.");
                    });
                })
                .then(responseData => {
                    log("Update Status Parsed Response:", responseData);
                    if (responseData.status === "success") {
                        Swal.fire("Success!", responseData.message || successMessage, "success").then(() => location.reload());
                    } else {
                        Swal.fire("Error", responseData.message || errorMessage, "error");
                        resetButtonState(buttonElement); // Reset on specific error from server
                    }
                })
                .catch(error => {
                    // This catches fetch errors, bad JSON parse, or thrown errors from !response.ok
                    console.error("Error updating appointment status:", error);
                    Swal.fire("Error", `${errorMessage}. ${error.message}`, "error");
                    resetButtonState(buttonElement); // Reset on any catch
                });
        }


        // --- createTimeOption helper (from booking form) ---
        // Used in setupAssignDateTimeFields
        function createTimeOption(slot_hhmmss, isPending) {
            const [hours, minutes] = slot_hhmmss.split(':');
            const hoursInt = parseInt(hours);
            const ampm = hoursInt >= 12 ? 'PM' : 'AM';
            const formattedHour = hoursInt % 12 === 0 ? 12 : hoursInt % 12;
            let displayTime = `${formattedHour}:${minutes} ${ampm}`;

            const option = new Option();
            option.value = slot_hhmmss; // HH:MM:SS
            option.dataset.isPending = isPending ? "true" : "false";

            if (isPending) {
                option.textContent = `${displayTime} (* Pending Request Exists)`; // Make it clear
                option.style.color = '#e67e22'; // Orange color for pending
            } else {
                option.textContent = displayTime;
            }
            return option;
        }

        // $(document).on('click', '.action-btn, .assign-btn, .assign-playgroup-btn, .remove-waitlist-btn', function(e) {
        //     e.preventDefault();
        //     const button = $(this);
        //     const appointmentId = button.data('id');
        //     let action = button.data('action'); // Get data-action first

        //     // Determine action based on class ONLY IF data-action is missing
        //     if (!action) {
        //          log("data-action attribute missing, checking class...");
        //          if (button.hasClass('assign-btn')) action = 'Assign';
        //          else if (button.hasClass('assign-playgroup-btn')) action = 'AssignPlaygroup';
        //          else if (button.hasClass('remove-waitlist-btn')) action = 'RemoveWaitlist';
        //          else if (button.hasClass('action-btn')) {
        //              // This case is ambiguous if data-action is missing, log error
        //              console.error("Button has class 'action-btn' but missing data-action attribute!", this);
        //              action = null; // Force error below
        //          }
        //     }

        //     if (!appointmentId || !action) {
        //         console.error("FATAL: Missing appointment ID or Could not determine action.", {button_element: this, determined_action: action, id: appointmentId});
        //         Swal.fire("Error", "Action failed: Cannot identify action or appointment.", "error");
        //         // Try to reset state just in case
        //         if (button.data('processing')) { resetButtonState(button); }
        //         return;
        //     }

        //     if (button.data('processing')) { log("Already processing..."); return; }
        //     button.data('processing', true).prop('disabled', true);
        //     log(`>>> Action Triggered: ${action}, Appointment ID: ${appointmentId}`); // Clear start log

        //     try {
        //          // Explicitly log which branch is being taken
        //          log(`--- Routing Action: ${action} ---`);
        //          switch (action) {
        //              case 'Approve':
        //              case 'Decline':
        //              case 'Waitlist':
        //                  log(`Calling fetchAppointmentDetailsAndAct for ${action}...`);
        //                  fetchAppointmentDetailsAndAct(appointmentId, action, button);
        //                  break;
        //              case 'Assign':
        //                  log(`Calling handleAssignAction...`); // Should NOT see this for Waitlist click
        //                  handleAssignAction(appointmentId, button);
        //                  break;
        //              case 'AssignPlaygroup':
        //                  log(`Calling handleAssignPlaygroupAction...`);
        //                  handleAssignPlaygroupAction(appointmentId, button);
        //                  break;
        //              case 'RemoveWaitlist':
        //                  log(`Calling handleRemoveWaitlistAction...`);
        //                  handleRemoveWaitlistAction(appointmentId, button);
        //                  break;
        //              default:
        //                  throw new Error(`Unrecognized action routed: ${action}`);
        //          }
        //      } catch (error) {
        //           console.error("Error routing action:", error);
        //           Swal.fire("Error", "Action failed.", "error");
        //           resetButtonState(button);
        //      }
        // });
    </script>

</body>

</html>
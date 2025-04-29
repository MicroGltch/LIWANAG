<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins, Head Therapists, and Therapists
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../../loginpage.php");
    exit();
}

$userid = $_SESSION['account_ID'];

$stmt = $connection->prepare("SELECT account_FName, account_LName, account_Email, account_PNum, profile_picture FROM users WHERE account_ID = ?");
$stmt->bind_param("s", $userid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $firstName = $userData['account_FName'];
    $lastName = $userData['account_LName'];
    $email = $userData['account_Email'];
    $phoneNumber = $userData['account_PNum'];

    if ($userData['profile_picture']) {
        $profilePicture = '../../uploads/client_profile_pictures/' . $userData['profile_picture'];
    } else {
        $profilePicture = '../../CSS/default.jpg';
    }
} else {
    echo "No Data Found.";
}

$stmt->close();

// ✅ Fetch Filters
$statusFilter = $_GET['status'] ?? "";
$sessionTypeFilter = $_GET['session_type'] ?? "";
$therapistFilter = $_GET['therapist'] ?? "";
$startDate = $_GET['start_date'] ?? "";
$endDate = $_GET['end_date'] ?? "";

// ✅ Base Query
$query = "SELECT a.appointment_id, a.date, a.time, a.status, a.session_type,
                    p.first_name AS patient_firstname, p.last_name AS patient_lastname, p.profile_picture AS patient_picture,
                    u.account_FName AS client_firstname, u.account_LName AS client_lastname, u.profile_picture AS client_picture,
                    t.account_FName AS therapist_firstname, t.account_LName AS therapist_lastname
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users u ON a.account_id = u.account_ID
            LEFT JOIN users t ON a.therapist_id = t.account_ID
            WHERE 1=1";

// ✅ Apply Filters
$params = [];
$types = "";

if (!empty($statusFilter)) {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if (!empty($sessionTypeFilter)) {
    $query .= " AND a.session_type = ?";
    $params[] = $sessionTypeFilter;
    $types .= "s";
}
if (!empty($therapistFilter)) {
    $query .= " AND a.therapist_id = ?";
    $params[] = $therapistFilter;
    $types .= "i";
}
if (!empty($startDate) && !empty($endDate)) {
    $query .= " AND a.date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}
$query .= " ORDER BY a.date DESC, a.time DESC";

// ✅ Prepare and Execute Query
$stmt = $connection->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Fetch Therapist List
$therapistQuery = "SELECT account_ID, account_FName, account_LName FROM users WHERE account_Type = 'therapist'";
$therapistResult = $connection->query($therapistQuery);
$therapists = $therapistResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Appointments</title>

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
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css" />
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

        .no-break {
            white-space: nowrap;
        }

        /* General Card Styles */
        .appointment-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .appointment-card h3 {
            font-size: 14px;
            font-weight: bold;
            margin: auto;
            flex: 1;
        }

        .appointment-card .details-button {
            background-color: #1e87f0;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            text-align: center;
            margin-left: 10px;
        }

        .appointment-card .details-button:hover {
            background-color: #0056b3;
        }

        @media (max-width: 640px) {
            .uk-table {
                display: none;
            }

            #appointmentsCards {
                display: block !important;
            }

            .appointment-card {
                margin-bottom: 15px;
                display: none;
                /* Hide all cards by default */
            }

            .appointment-card.visible {
                display: flex;
                /* Show only visible cards */
            }
        }
    </style>

</head>

<body>
    <!-- Main Content -->
    <!-- 🔹 Filters Section -->
    <form method="GET" class="uk-width-1-1">
        <div class="uk-grid-small uk-flex uk-flex-middle uk-grid-match uk-visible@m" uk-grid>
            <div class="uk-width-1-5@m">
                <label class="uk-form-label">Status:</label>
                <select class="uk-select" name="status">
                    <option value="">All</option>
                    <option value="Pending" <?= $statusFilter === "Pending" ? "selected" : "" ?>>Pending</option>
                    <option value="Approved" <?= $statusFilter === "Approved" ? "selected" : "" ?>>Approved</option>
                    <option value="Waitlisted" <?= $statusFilter === "Waitlisted" ? "selected" : "" ?>>Waitlisted</option>
                    <option value="Completed" <?= $statusFilter === "Completed" ? "selected" : "" ?>>Completed</option>
                    <option value="Cancelled" <?= $statusFilter === "Cancelled" ? "selected" : "" ?>>Cancelled</option>
                    <option value="Declined" <?= $statusFilter === "Declined" ? "selected" : "" ?>>Declined</option>
                </select>
            </div>

            <div class="uk-width-1-5@m">
                <label class="uk-form-label">Session Type:</label>
                <select class="uk-select" name="session_type">
                    <option value="">All</option>
                    <option value="Initial Evaluation" <?= $sessionTypeFilter === "Initial Evaluation" ? "selected" : "" ?>>Initial Evaluation</option>
                    <option value="Playgroup" <?= $sessionTypeFilter === "Playgroup" ? "selected" : "" ?>>Playgroup</option>
                </select>
            </div>

            <div class="uk-width-1-5@m">
                <label class="uk-form-label">Therapist:</label>
                <select class="uk-select" name="therapist">
                    <option value="">All</option>
                    <?php foreach ($therapists as $therapist): ?>
                        <option value="<?= $therapist['account_ID']; ?>" <?= $therapistFilter == $therapist['account_ID'] ? "selected" : "" ?>>
                            <?= htmlspecialchars($therapist['account_FName'] . " " . $therapist['account_LName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="uk-width-1-5@m">
                <label class="uk-form-label">Start Date:</label>
                <input class="uk-input" type="date" name="start_date" value="<?= $startDate; ?>">
            </div>

            <div class="uk-width-1-5@m">
                <label class="uk-form-label">End Date:</label>
                <input class="uk-input" type="date" name="end_date" value="<?= $endDate; ?>">
            </div>
        </div>

        <div class="uk-text-right uk-margin-top uk-visible@m">
            <button class="uk-button" type="submit" style="border-radius: 15px; background-color:#1e87f0; color:white;">Apply Filters</button>
            <a href="view_all_appointments.php" class="uk-button uk-button-default" style="border-radius: 15px;">Reset</a>
        </div>
    </form>

    <!-- 🔹 Appointments Table -->
    <div class="uk-width-1-1 uk-margin-top uk-visible@m">
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
        <div class="uk-overflow-auto">
            <table id="appointmentsTable" class="uk-table uk-table-striped uk-table-hover uk-table-responsive uk-table-middle">
                <thead>
                    <tr>
                        <th class="uk-table-shrink"><span class="no-break">Patient <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th class="uk-table-shrink"><span class="no-break">Client <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th class="uk-table-shrink"><span class="no-break">Date <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th class="uk-table-shrink"><span class="no-break">Time <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th class="uk-table-shrink"><span class="no-break">Session Type <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th class="uk-table-shrink"><span class="no-break">Therapist <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                        <th class="uk-table-shrink"><span class="no-break">Status <span uk-icon="icon: arrow-down-arrow-up"></span></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <img src="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../CSS/default.jpg'; ?>"
                                    alt="Patient Picture" class="uk-border-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                <?= htmlspecialchars($appointment['patient_firstname'] . " " . $appointment['patient_lastname']); ?>
                            </td>
                            <td>
                                <img src="<?= !empty($appointment['client_picture']) ? '../../uploads/profile_pictures/' . $appointment['client_picture'] : '../../CSS/default.jpg'; ?>"
                                    alt="Client Picture" class="uk-border-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                <?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?>
                            </td>
                            <td><?= date('F j, Y', strtotime($appointment['date'])); ?></td>
                            <td><?= date('g:i A', strtotime($appointment['time'])); ?></td>
                            <td><?= ucfirst(htmlspecialchars($appointment['session_type'])); ?></td>
                            <td><?= !empty($appointment['therapist_firstname']) ? htmlspecialchars($appointment['therapist_firstname'] . " " . $appointment['therapist_lastname']) : "Not Assigned"; ?></td>
                            <td><?= htmlspecialchars(ucwords($appointment['status'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Card layout for mobile -->
    <div id="appointmentsCardsSearch" class="uk-margin uk-hidden@m">
        <div class="uk-flex uk-flex-left" style="width: 100%; gap: 10px; display: flex;">
            <div class="uk-inline" style="flex: 1;">
                <span class="uk-form-icon" uk-icon="icon: search"></span>
                <input type="text" id="appointmentsCardsSearchInput" class="uk-input" placeholder="Search..." style="border-radius: 25px; padding-left: 40px; border: 1px solid #e5e5e5; width: 100%;">
            </div>
            <div class="uk-inline" style="width: auto;">
                <select id="appointmentsCardsEntries" class="uk-select" style="border-radius: 25px; padding: 0 10px; border: 1px solid #e5e5e5; min-width: 80px;">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>
    <div id="appointmentsCards" class="uk-hidden@m">
        <?php if (isset($appointments) && !empty($appointments)) : ?>
            <?php foreach ($appointments as $index => $appointment) : ?>
                <div class="appointment-card"
                    data-index="<?= $index; ?>"
                    data-appointment-id="<?= htmlspecialchars($appointment['appointment_id']); ?>"
                    data-patient="<?= htmlspecialchars($appointment['patient_firstname'] . ' ' . $appointment['patient_lastname']); ?>"
                    data-client="<?= htmlspecialchars($appointment['client_firstname'] . ' ' . $appointment['client_lastname']); ?>"
                    data-date="<?= date('F j, Y', strtotime($appointment['date'])); ?>"
                    data-time="<?= date('g:i A', strtotime($appointment['time'])); ?>"
                    data-session="<?= htmlspecialchars($appointment['session_type']); ?>"
                    data-therapist="<?= !empty($appointment['therapist_firstname']) ? htmlspecialchars($appointment['therapist_firstname'] . ' ' . $appointment['therapist_lastname']) : 'Not Assigned'; ?>"
                    data-status="<?= htmlspecialchars($appointment['status']); ?>"
                    data-patient-picture="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . htmlspecialchars($appointment['patient_picture']) : '../../CSS/default.jpg'; ?>"
                    data-client-picture="<?= !empty($appointment['client_picture']) ? '../../uploads/profile_pictures/' . htmlspecialchars($appointment['client_picture']) : '../../CSS/default.jpg'; ?>"
                    style="display: <?= $index < 5 ? 'flex' : 'none'; ?>;">
                    <div class="uk-flex uk-flex-left uk-flex-between" style="width: 100%;">
                        <div class="uk-flex uk-flex-left">
                            <img src="<?= !empty($appointment['patient_picture']) ? '../../uploads/profile_pictures/' . htmlspecialchars($appointment['patient_picture']) : '../../CSS/default.jpg'; ?>"
                                alt="Patient"
                                class="uk-border-circle"
                                style="width: 40px; height: 40px; object-fit: cover; margin-right: 15px;">
                            <h3><?= htmlspecialchars($appointment['patient_firstname'] . ' ' . $appointment['patient_lastname']); ?></h3>
                        </div>
                        <button class="details-button" onclick="showAppointmentDetails('<?= $appointment['appointment_id']; ?>')" style="border-radius:15px">More Details</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p>No appointments found.</p>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTable for appointments
            const table = $('#appointmentsTable').DataTable({
                pageLength: 5,
                lengthMenu: [5, 10, 25, 50],
                order: [
                    [2, 'asc']
                ], // Sort by date column by default
                dom: 'rtip', // Disable default search and length menu
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

            // Custom Search
            $('#customSearch').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Custom Entries Dropdown
            $('#customEntries').on('change', function() {
                table.page.len(this.value).draw();
            });
        });

        window.showAppointmentDetails = function(appointmentId) {
            // Find the appointment card using the provided ID
            const appointmentCard = document.querySelector(`.appointment-card[data-appointment-id="${appointmentId}"]`);

            if (appointmentCard) {
                // Retrieve appointment data from the card's dataset
                const patient = appointmentCard.dataset.patient || "N/A";
                const client = appointmentCard.dataset.client || "N/A";
                const date = appointmentCard.dataset.date || "N/A";
                const time = appointmentCard.dataset.time || "N/A";
                const session = appointmentCard.dataset.session || "N/A";
                const therapist = appointmentCard.dataset.therapist || "Not Assigned";
                const status = appointmentCard.dataset.status || "N/A";
                const patientPic = appointmentCard.dataset.patientPicture || '../../CSS/default.jpg';
                const clientPic = appointmentCard.dataset.clientPicture || '../../CSS/default.jpg';

                // Debugging: Log the retrieved data to the console
                console.log("Appointment Details:", {
                    patient,
                    client,
                    date,
                    time,
                    session,
                    therapist,
                    status,
                    patientPic,
                    clientPic
                });

                // Construct the modal content
                const modalContent = `
                <div>
                    <table class="uk-table uk-table-striped uk-text-left" style="font-size: 14px; width: 100%; margin-top: 15px; display: flex !important;">
                        <tr>
                            <td style="text-align:left; width: 35%;"><strong>Patient:</strong></td>
                            <td>
                                <div class="uk-flex uk-flex-middle">
                                    <img class="uk-border-circle" 
                                        src="${patientPic}" 
                                        alt="Patient Profile" 
                                        style="width: 30px; height: 30px; object-fit: cover; margin-right: 10px;">
                                    <span>${patient}</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align:left"><strong>Client:</strong></td>
                            <td>
                                <div class="uk-flex uk-flex-middle">
                                    <img class="uk-border-circle" 
                                        src="${clientPic}" 
                                        alt="Client Profile" 
                                        style="width: 30px; height: 30px; object-fit: cover; margin-right: 10px;">
                                    <span>${client}</span>
                                </div>
                            </td>
                        </tr>
                        <tr><td style="text-align:left"><strong>Date:</strong></td><td>${date}</td></tr>
                        <tr><td style="text-align:left"><strong>Time:</strong></td><td>${time}</td></tr>
                        <tr><td style="text-align:left"><strong>Session:</strong></td><td>${session}</td></tr>
                        <tr><td style="text-align:left"><strong>Therapist:</strong></td><td>${therapist}</td></tr>
                        <tr><td style="text-align:left"><strong>Status:</strong></td><td>${status}</td></tr>
                    </table>
                </div>
            `;

                // Display the modal using SweetAlert2
                Swal.fire({
                    title: '<h3 style="font-size: 20px; font-weight: bold; text-align: left;">Appointment Details</h3>',
                    html: modalContent,
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: '90%',
                    customClass: {
                        container: 'appointment-modal'
                    }
                });
            } else {
                // Debugging: Log an error if the appointment card is not found
                console.error("Appointment card not found for ID:", appointmentId);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Appointment details could not be found.',
                    showConfirmButton: true
                });
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Get DOM elements
            const searchInput = document.getElementById('appointmentsCardsSearchInput');
            const entriesDropdown = document.getElementById('appointmentsCardsEntries');
            const appointmentsContainer = document.getElementById('appointmentsCards');
            const appointmentCards = document.querySelectorAll('.appointment-card');

            function filterCards() {
                if (!searchInput || !entriesDropdown || !appointmentsContainer) return;

                const searchTerm = searchInput.value.toLowerCase().trim();
                const maxEntries = parseInt(entriesDropdown.value, 10);
                let visibleCount = 0;
                let hasVisibleCards = false;

                // Remove existing no results message if present
                const existingMessage = appointmentsContainer.querySelector('.no-results-message');
                if (existingMessage) {
                    existingMessage.remove();
                }

                // Filter cards
                appointmentCards.forEach(card => {
                    // Get all searchable data
                    const patient = card.getAttribute('data-patient')?.toLowerCase() || '';
                    const client = card.getAttribute('data-client')?.toLowerCase() || '';
                    const date = card.getAttribute('data-date')?.toLowerCase() || '';
                    const time = card.getAttribute('data-time')?.toLowerCase() || '';
                    const session = card.getAttribute('data-session')?.toLowerCase() || '';
                    const therapist = card.getAttribute('data-therapist')?.toLowerCase() || '';
                    const status = card.getAttribute('data-status')?.toLowerCase() || '';

                    // Check if card matches search criteria
                    const matches = patient.includes(searchTerm) ||
                        client.includes(searchTerm) ||
                        date.includes(searchTerm) ||
                        time.includes(searchTerm) ||
                        session.includes(searchTerm) ||
                        therapist.includes(searchTerm) ||
                        status.includes(searchTerm);

                    // Show/hide card based on search and entry limit
                    if (matches && visibleCount < maxEntries) {
                        card.style.display = 'flex';
                        visibleCount++;
                        hasVisibleCards = true;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Show no results message if needed
                if (!hasVisibleCards) {
                    const noResults = document.createElement('p');
                    noResults.className = 'no-results-message';
                    noResults.style.textAlign = 'center';
                    noResults.style.padding = '20px';
                    noResults.style.width = '100%';
                    noResults.textContent = searchTerm ? 'No matching appointments found.' : 'No appointments to display.';
                    appointmentsContainer.appendChild(noResults);
                }
            }

            // Add event listeners
            if (searchInput && entriesDropdown) {
                searchInput.addEventListener('input', filterCards);
                entriesDropdown.addEventListener('change', filterCards);

                // Initial filter
                filterCards();
            }

            // Clear search
            const clearSearch = () => {
                if (searchInput) {
                    searchInput.value = '';
                    filterCards();
                }
            };

            // Reset entries
            const resetEntries = () => {
                if (entriesDropdown) {
                    entriesDropdown.value = '5';
                    filterCards();
                }
            };
        });
    </script>
</body>

</html>
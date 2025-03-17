<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins, Head Therapists, and Therapists
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../../loginpage.php");
    exit();
}

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

</head>

<body>
    <script>
        console.log('Session Username:', <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>);
    </script>
    <!-- Navbar -->
    <nav class="uk-navbar-container logged-in">
        <div class="uk-container">
            <div uk-navbar>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li>
                            <a href="#" class="uk-navbar-item">
                            <img class="profile-image" src="../../CSS/default.jpg" alt="Profile Image" uk-img>
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;"> <?php echo $_SESSION['username']; ?>
                        </li>
                        <li><a href="../../Accounts/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <hr class="solid">

    <!-- Main Content -->
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-height-viewport">
        <!--Sidebar-->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
            <button class="uk-button uk-button-default uk-hidden@m uk-width-1-1 uk-margin-bottom sidebar-toggle" type="button">
                Menu <span uk-navbar-toggle-icon></span>
            </button>
            <div class="sidebar-nav">
                <ul class="uk-nav uk-nav-default">
                    <?php
                    if (isset($_SESSION['account_Type'])) {
                        $accountType = strtolower($_SESSION['account_Type']);
                        if ($accountType === "admin") {
                            $dashboardLink = "../../Dashboards/admindashboard.php";
                        } elseif ($accountType === "head therapist") {
                            $dashboardLink = "../../Dashboards/headtherapistdashboard.php";
                        }
                    }
                    ?>
                    <li><a href="<?= $dashboardLink ?>">Dashboard</a></li>
                    <?php if (strtolower($_SESSION['account_Type']) !== "head therapist") : ?>
                    <li><a href="../../Dashboards/forAdmin/manageWebpage/timetable_settings.php">Manage Timetable Settings</a></li>
                    <?php endif; ?>                    
                    <?php if (strtolower($_SESSION['account_Type']) !== "admin") : ?>
                    <li><a href="manage_appointments.php">View & Manage Appointments</a></li>
                    <?php endif; ?>                    
                    <li class="uk-active"><a href="view_all_appointments.php">View All Appointments</a></li>
                    <?php if (strtolower($_SESSION['account_Type']) !== "head therapist") : ?>
                    <li><a href="../../Dashboards/forAdmin/add_therapist.php">Manage Therapists (Adding Only)</a></li>
                    <?php endif; ?>                    
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <div class="uk-width-1-1">
                <h2>View All Appointments</h2>

                <!-- 🔹 Filters Section -->
                <form method="GET" class="uk-width-1-1">
                    <div class="uk-grid-small uk-flex uk-flex-middle uk-grid-match" uk-grid>
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

                    <div class="uk-text-right uk-margin-top">
                        <button class="uk-button uk-button-primary" type="submit">Apply Filters</button>
                        <a href="view_all_appointments.php" class="uk-button uk-button-default">Reset</a>
                    </div>
                </form>

                <!-- 🔹 Appointments Table -->
                <div class="uk-width-1-1 uk-margin-top">
                    <div class="uk-overflow-auto">
                        <table id="appointmentsTable" class="uk-table uk-table-striped uk-table-hover uk-table-responsive uk-table-middle">
                            <thead>
                                <tr>
                                    <th class="uk-table-shrink">Patient <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Client <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Date <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Time <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Session Type <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Therapist <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                    <th class="uk-table-shrink">Status <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <img src="<?= !empty($appointment['patient_picture']) ? '../../../../../uploads/profile_pictures/' . $appointment['patient_picture'] : '../../../../CSS/default.jpg'; ?>"
                                                alt="Patient Picture" class="uk-border-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?= htmlspecialchars($appointment['patient_firstname'] . " " . $appointment['patient_lastname']); ?>
                                        </td>
                                        <td>
                                            <img src="<?= !empty($appointment['client_picture']) ? '../../../../../uploads/profile_pictures/' . $appointment['client_picture'] : '../../../../CSS/default.jpg'; ?>"
                                                alt="Client Picture" class="uk-border-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?>
                                        </td>
                                        <td><?= htmlspecialchars($appointment['date']); ?></td>
                                        <td><?= htmlspecialchars($appointment['time']); ?></td>
                                        <td><?= htmlspecialchars($appointment['session_type']); ?></td>
                                        <td><?= !empty($appointment['therapist_firstname']) ? htmlspecialchars($appointment['therapist_firstname'] . " " . $appointment['therapist_lastname']) : "Not Assigned"; ?></td>
                                        <td><?= htmlspecialchars($appointment['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#appointmentsTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50],
                order: [
                    [2, 'desc']
                ], // Sort by date column by default (descending)
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
                        orderable: true,
                        targets: '_all'
                    }, // Make all columns sortable
                    {
                        type: 'date',
                        targets: 2
                    } // Specify date type for date column
                ]
            });
        });
    </script>
</body>

</html>
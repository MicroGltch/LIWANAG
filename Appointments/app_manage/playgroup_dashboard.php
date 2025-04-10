<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "head therapist") {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

function getPatientsForSession($pg_session_id, $connection, $session_status = 'approved') {
    $query = "SELECT a.patient_id, p.first_name, p.last_name
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              WHERE a.pg_session_id = ? AND a.status = ?"; // ✅ Dynamic status filtering

    $stmt = $connection->prepare($query);
    $stmt->bind_param("ss", $pg_session_id, $session_status);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$today = date("Y-m-d");

// Fetch sessions
$openToday = $connection->query("SELECT * FROM playgroup_sessions WHERE date = '$today' AND status = 'Open'")->fetch_all(MYSQLI_ASSOC);
$openSessions = $connection->query("SELECT * FROM playgroup_sessions WHERE status = 'Open' ORDER BY date ASC, time ASC")->fetch_all(MYSQLI_ASSOC);
$completedToday = $connection->query("SELECT * FROM playgroup_sessions WHERE date = '$today' AND status = 'Completed'")->fetch_all(MYSQLI_ASSOC);

$pastSessionsQuery = "SELECT * FROM playgroup_sessions
                      WHERE date < CURDATE() AND status = 'Completed'
                      ORDER BY date DESC";
$pastSessions = $connection->query($pastSessionsQuery)->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Playgroup Dashboard</title>
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .uk-container {
        min-height: 700px; /* Adjust this value as needed */
        }
        .session-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .session-title {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        #create-session-btn {
            background-color: white;
            color: black; /* Changed color to black */
            border: 1px solid black; /* Changed border color to black */
            transition: background-color 0.3s ease; /* Smooth transition for hover effect */
        }

        #create-session-btn:hover {
            background-color:black; /* A light gray for highlight */
            color: white; /* Changed hover color to black */
            border-color: white; /* Changed hover border color to black */
        }
                .patient-list {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .patient-list th, .patient-list td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .patient-list th {
            background-color: #f7f7f7;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .uk-button-group .uk-button {
            margin-right: 5px;
        }
        .session-info {
            font-size: 0.9em;
            color: #777;
            margin-bottom: 5px;
        }
        .no-data {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <div class="uk-flex uk-flex-middle uk-margin-bottom uk-flex-between">
            <h1 class="uk-text-bold uk-margin-remove-bottom">
                <span class="uk-margin-right"></span>
                🧩 Playgroup Management
            </h1>
            <div>
                <button id="create-session-btn" class="uk-button" uk-tooltip="title: Start a new playgroup session">
                    <span class="uk-margin-right"></span>
                    ➕ Create New Session
                </button>
            </div>
        </div>

        <ul class="uk-tab" uk-tab>
            <li class="uk-active"><a href="#">📝 Today's Sessions</a></li>
            <li><a href="#">📂 All Open Sessions</a></li>
            <li><a href="#">🕰️ Past Sessions</a></li>
            <li><a href="#">✅ Completed Today</a></li>
        </ul>

        <ul class="uk-switcher uk-margin">
            <li>
                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title">📝 Today's Sessions for Attendance</h3>
                    <?php if (empty($openToday)): ?>
                        <p class="no-data">No open sessions today.</p>
                    <?php else: ?>
                        <ul class="uk-list">
                            <?php foreach ($openToday as $session):
                                $patients = getPatientsForSession($session['pg_session_id'], $connection, 'approved');
                            ?>
                                <li class="session-card">
                                    <h4 class="session-title"><?= date('h:i A', strtotime($session['time'])) ?> Session <span class="session-info">(<?= $session['current_count'] ?>/<?= $session['max_capacity'] ?>)</span></h4>
                                    <?php if (empty($patients)): ?>
                                        <p class="uk-text-muted">No approved patients for this session yet.</p>
                                    <?php else: ?>
                                        <form method="POST" action="playgroup_attendance.php">
                                            <input type="hidden" name="pg_session_id" value="<?= $session['pg_session_id'] ?>">
                                            <table class="uk-table uk-table-divider patient-list">
                                                <thead>
                                                    <tr>
                                                        <th>Patient</th>
                                                        <th>Attendance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($patients as $p): ?>
                                                        <tr>
                                                            <td><?= $p['first_name'] . ' ' . $p['last_name'] ?></td>
                                                            <td>
                                                                <select name="attendance[<?= $p['patient_id'] ?>]" class="uk-select">
                                                                    <option value="Present">Present</option>
                                                                    <option value="Absent">Absent</option>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <button type="submit" name="submit_attendance" class="uk-button uk-button-primary">Submit Attendance</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </li>
            <li>
                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title">📂 All Open Playgroup Sessions</h3>
                    <?php if (empty($openSessions)): ?>
                        <p class="no-data">No open sessions found.</p>
                    <?php else: ?>
                        <ul class="uk-list">
                            <?php foreach ($openSessions as $session):
                                $patients = getPatientsForSession($session['pg_session_id'], $connection, 'approved');
                            ?>
                                <li class="session-card">
                                    <h4 class="session-title"><?= date('F j, Y', strtotime($session['date'])) ?> at <?= date('h:i A', strtotime($session['time'])) ?> <span class="session-info">(<span id="current-count-<?= $session['pg_session_id'] ?>"><?= $session['current_count'] ?></span>/<?= $session['max_capacity'] ?>)</span></h4>
                                    <?php if (empty($patients)): ?>
                                        <p class="uk-text-muted">No approved patients assigned yet.</p>
                                    <?php else: ?>
                                        <form method="POST" action="playgroup_attendance.php">
                                            <input type="hidden" name="pg_session_id" value="<?= $session['pg_session_id'] ?>">
                                            <table class="uk-table uk-table-divider patient-list">
                                                <thead>
                                                    <tr>
                                                        <th>Patient</th>
                                                        <th>Attendance</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($patients as $p): ?>
                                                        <tr id="patientRow-<?= $p['patient_id'] ?>">
                                                            <td><?= $p['first_name'] . ' ' . $p['last_name'] ?></td>
                                                            <td>
                                                                <select name="attendance[<?= $p['patient_id'] ?>]" class="uk-select">
                                                                    <option value="Present">Present</option>
                                                                    <option value="Absent">Absent</option>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="uk-button uk-button-danger uk-button-small kick-btn"
                                                                        data-patient-id="<?= $p['patient_id'] ?>"
                                                                        data-session-id="<?= $session['pg_session_id'] ?>"
                                                                        data-patient-name="<?= $p['first_name'] . ' ' . $p['last_name'] ?>">
                                                                    Kick
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <button type="submit" name="submit_attendance" class="uk-button uk-button-primary">Submit Attendance</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </li>
            <li>
                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title">🕰️ Past Completed Sessions</h3>
                    <?php if (empty($pastSessions)): ?>
                        <p class="no-data">No past sessions found.</p>
                    <?php else: ?>
                        <ul class="uk-list">
                            <?php foreach ($pastSessions as $session):
                                $patients = getPatientsForSession($session['pg_session_id'], $connection, 'Completed');
                            ?>
                                <li class="session-card">
                                    <h4 class="session-title"><?= date('F j, Y', strtotime($session['date'])) ?> at <?= date('h:i A', strtotime($session['time'])) ?></h4>
                                    <table class="uk-table uk-table-divider patient-list">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patients as $p):
                                                $att = $connection->prepare("SELECT status FROM playgroup_attendance WHERE pg_session_id = ? AND patient_id = ?");
                                                $att->bind_param("si", $session['pg_session_id'], $p['patient_id']);
                                                $att->execute();
                                                $status = $att->get_result()->fetch_assoc()['status'] ?? "Not marked";
                                            ?>
                                                <tr>
                                                    <td><?= $p['first_name'] . ' ' . $p['last_name'] ?></td>
                                                    <td><?= $status ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="uk-text-right">
                                        <form method="GET" action="edit_attendance.php" class="uk-inline">
                                            <input type="hidden" name="session_id" value="<?= $session['pg_session_id'] ?>">
                                            <button class="uk-button uk-button-secondary uk-button-small">Edit Attendance</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </li>
            <li>
                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title">✅ Completed Today's Sessions</h3>
                    <?php if (empty($completedToday)): ?>
                        <p class="no-data">No completed sessions today.</p>
                    <?php else: ?>
                        <ul class="uk-list">
                            <?php foreach ($completedToday as $session): ?>
                                <li class="session-card">
                                    <h4 class="session-title"><?= date('h:i A', strtotime($session['time'])) ?> Session</h4>
                                    <p class="session-info">Session ID: <?= $session['pg_session_id'] ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </li>
        </ul>
    </div>
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const createSessionBtn = document.getElementById('create-session-btn');

        createSessionBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Create New Playgroup Session',
                    html: `<form id="create-session-form" class="uk-grid-small" uk-grid>
                                <div class="uk-width-1-1">
                                    <label class="uk-form-label" for="swal-date">Date:</label>
                                    <div class="uk-form-controls">
                                        <input type="date" id="swal-date" name="date" class="uk-input" required min="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <div class="uk-width-1-1">
                                    <label class="uk-form-label" for="swal-time">Time:</label>
                                    <div class="uk-form-controls">
                                        <input type="time" id="swal-time" name="time" class="uk-input" required>
                                    </div>
                                </div>
                                <div class="uk-width-1-1">
                                    <label class="uk-form-label" for="swal-capacity">Capacity:</label>
                                    <div class="uk-form-controls">
                                        <input type="number" id="swal-capacity" name="max_capacity" value="6" min="1" max="12" class="uk-input" required>
                                    </div>
                                </div>
                            </form>`,
                    showCancelButton: true,
                    confirmButtonText: 'Create',
                    position: 'top', // Add this line
                    preConfirm: () => {
                        const date = Swal.getPopup().querySelector('#swal-date').value;
                        const time = Swal.getPopup().querySelector('#swal-time').value;
                        const max_capacity = Swal.getPopup().querySelector('#swal-capacity').value;
                        if (!date || !time || !max_capacity) {
                            Swal.showValidationMessage(`Please fill in all fields`);
                        }
                        return { date: date, time: time, max_capacity: max_capacity };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('date', result.value.date);
                        formData.append('time', result.value.time);
                        formData.append('max_capacity', result.value.max_capacity);

                        fetch('create_playgroup_session.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                position: 'top'
                            }).then(() => {
                                window.location.reload(); // Reload after user sees success message
                            });
                            } else {
                                Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                position: 'top'
                            }).then(() => {
                                window.location.reload(); // Reload after user sees success message
                            });
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Something went wrong while creating the session.', 'error', position, 'top');
                        });
                    } else if (result.isDismissed) { // Add this else if block
                    // Optional: Handle the dismissal, e.g., log it or show a message
                    console.log('Creation cancelled');
                    }
                });
            });

        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".kick-btn").forEach(button => {
                button.addEventListener("click", function () {
                    let patientId = this.getAttribute("data-patient-id");
                    let sessionId = this.getAttribute("data-session-id");
                    let patientName = this.getAttribute("data-patient-name");

                    Swal.fire({
                        title: `Kick ${patientName}?`,
                        text: `Are you sure you want to remove ${patientName} from the session?`,
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, Kick",
                        cancelButtonText: "Cancel"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch("../app_process/kick_patient_playgroup.php", {
                                method: "POST",
                                headers: { "Content-Type": "application/json" },
                                body: JSON.stringify({ patient_id: patientId, pg_session_id: sessionId })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === "success") {
                                    Swal.fire("Removed!", data.message, "success");
                                    document.getElementById("patientRow-" + patientId).remove();

                                    // 👇 Update current count in the session header
                                    const countSpan = document.getElementById("current-count-" + sessionId);
                                    if (countSpan) {
                                        const current = parseInt(countSpan.textContent);
                                        if (!isNaN(current) && current > 0) {
                                            countSpan.textContent = current - 1;
                                        }
                                    }
                                } else {
                                    Swal.fire("Error", data.message, "error");
                                }
                            })
                            .catch(error => Swal.fire("Error", "Something went wrong.", "error"));
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
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

</head>
<body>
<div class="uk-container uk-margin-top">
<?php if (isset($_SESSION['playgroup_success'])): ?>
    <div class="uk-alert-success" uk-alert>
        <a class="uk-alert-close" uk-close></a>
        <p><?= $_SESSION['playgroup_success']; ?></p>
    </div>
    <?php unset($_SESSION['playgroup_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['playgroup_error'])): ?>
    <div class="uk-alert-danger" uk-alert>
        <a class="uk-alert-close" uk-close></a>
        <p><?= $_SESSION['playgroup_error']; ?></p>
    </div>
    <?php unset($_SESSION['playgroup_error']); ?>
<?php endif; ?>


    <h2 class="uk-heading-line"><span>🧩 Playgroup Management Dashboard</span></h2>

    <!-- ✅ CREATE NEW SESSION -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h4>Create New Session</h4>
        <form action="create_playgroup_session.php" method="POST" class="uk-grid-small" uk-grid>
            <div class="uk-width-1-3@s">
                <input type="date" name="date" class="uk-input" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="uk-width-1-3@s">
                <input type="time" name="time" class="uk-input" required>
            </div>
            <div class="uk-width-1-6@s">
                <input type="number" name="max_capacity" value="6" min="1" max="12" class="uk-input" required>
            </div>
            <div class="uk-width-1-6@s">
                <button type="submit" class="uk-button uk-button-primary">Create</button>
            </div>
        </form>
    </div>

    <!-- ✅ TODAY’S OPEN SESSIONS (MARK ATTENDANCE) -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h4>📝 Today's Open Sessions</h4>
        <?php if (empty($openToday)): ?>
            <p>No open sessions.</p>
        <?php else: ?>
            <?php foreach ($openToday as $session): 
                $patients = getPatientsForSession($session['pg_session_id'], $connection, 'approved');
            ?>
            <div class="uk-margin">
                <h5><?= $session['time'] ?> Session (<?= $session['current_count'] ?>/<?= $session['max_capacity'] ?>)</h5>
                <form method="POST" action="playgroup_attendance.php">
                    <input type="hidden" name="pg_session_id" value="<?= $session['pg_session_id'] ?>">
                    <table class="uk-table uk-table-divider">
                        <thead><tr><th>Patient</th><th>Attendance</th></tr></thead>
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
                    <button type="submit" name="submit_attendance" class="uk-button uk-button-primary">Finalize</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ✅ TODAY’S COMPLETED SESSIONS -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h4>✅ Completed Today</h4>
        <?php if (empty($completedToday)): ?>
            <p>No completed sessions today.</p>
        <?php else: ?>
            <?php foreach ($completedToday as $session): ?>
                <div class="uk-margin-small-bottom">
                    <?= $session['time'] ?> (<?= $session['pg_session_id'] ?>)
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ✅ ALL OPEN SESSIONS -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
    <h4>📝 Open Playgroup Sessions</h4>
    <?php if (empty($openSessions)): ?>
        <p>No open sessions found.</p>
    <?php else: ?>
        <?php 
        $hasDisplay = false;
        foreach ($openSessions as $session): 
            $patients = getPatientsForSession($session['pg_session_id'], $connection, 'approved');
            $hasDisplay = true;
        ?>
            <div class="uk-margin">
                <h5>
                    <?= $session['date'] ?> — <?= $session['time'] ?> 
                    (<span id="current-count-<?= $session['pg_session_id'] ?>"><?= $session['current_count'] ?></span>/<?= $session['max_capacity'] ?>)
                </h5>

                <?php if (empty($patients)): ?>
                    <p class="uk-text-muted">No approved patients assigned yet.</p>
                <?php else: ?>
                    <form method="POST" action="playgroup_attendance.php">

                        <input type="hidden" name="pg_session_id" value="<?= $session['pg_session_id'] ?>">
                        <table class="uk-table uk-table-divider">
                            <thead><tr><th>Patient</th><th>Attendance</th><th>Action</th></tr></thead>
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
                                        <button type="button" class="uk-button uk-button-danger kick-btn" 
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
                        <button type="submit" name="submit_attendance" class="uk-button uk-button-primary">Finalize</button>
                    </form>
                <?php endif; ?>
            </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

<script>
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
                        }else {
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



    <!-- 📂 PAST SESSIONS AND EDIT -->
    <div class="uk-card uk-card-default uk-card-body uk-margin-bottom">
        <h4>📂 Past Completed Sessions</h4>
       
        <?php if (empty($pastSessions)): ?>
            <p>No past sessions.</p>
        <?php else: ?>
            <?php foreach ($pastSessions as $session): 
                $patients = getPatientsForSession($session['pg_session_id'], $connection, 'Completed');
            ?>
                <div class="uk-margin-bottom">
                    <h5><?= $session['date'] ?> at <?= $session['time'] ?></h5>
                    <table class="uk-table uk-table-divider">
                        <thead><tr><th>Patient</th><th>Status</th></tr></thead>
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
                    <form method="GET" action="edit_attendance.php" class="uk-text-right">
                        <input type="hidden" name="session_id" value="<?= $session['pg_session_id'] ?>">
                        <button class="uk-button uk-button-secondary">Edit Attendance</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="../../Dashboards/headtherapistdashboard.php" class="uk-button uk-button-default">Back to Main Dashboard</a>
</div>
</body>
</html>

<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins & Head Therapists Only
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin", "head therapist"])) {
    header("Location: ../../../loginpage.php");
    exit();
}

// ✅ Fetch all active Playgroup sessions (not completed)
$query = "SELECT DISTINCT pg_session_id, date, time 
          FROM appointments 
          WHERE session_type = 'Playgroup' AND status != 'Completed' 
          ORDER BY date, time";
$result = $connection->query($query);
$sessions = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Playgroup Sessions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.9.6/css/uikit.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <h2>Manage Playgroup Sessions</h2>

        <?php if (empty($sessions)): ?>
            <p>No active Playgroup sessions.</p>
        <?php else: ?>
            <?php foreach ($sessions as $session): ?>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h4>Session on <?= htmlspecialchars($session['date'] . " at " . $session['time']); ?></h4>
                    <table class="uk-table uk-table-striped">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // ✅ Fetch patients for this session
                            $sessionID = $session['pg_session_id'];
                            $patientQuery = "SELECT a.appointment_id, p.first_name, p.last_name, a.pg_attendance 
                                             FROM appointments a 
                                             JOIN patients p ON a.patient_id = p.patient_id
                                             WHERE a.pg_session_id = ?";
                            $stmt = $connection->prepare($patientQuery);
                            $stmt->bind_param("s", $sessionID);
                            $stmt->execute();
                            $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                            foreach ($patients as $patient): ?>
                                <tr>
                                    <td><?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?></td>
                                    <td>
                                        <select class="uk-select attendance-select" data-appointment-id="<?= $patient['appointment_id']; ?>">
                                            <option value="Pending" <?= ($patient['pg_attendance'] == "Pending") ? "selected" : ""; ?>>Pending</option>
                                            <option value="Present" <?= ($patient['pg_attendance'] == "Present") ? "selected" : ""; ?>>Present</option>
                                            <option value="Absent" <?= ($patient['pg_attendance'] == "Absent") ? "selected" : ""; ?>>Absent</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="uk-button uk-button-primary complete-session-btn" data-session-id="<?= $sessionID; ?>">Complete Session</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // ✅ Handle Attendance Updates
            document.querySelectorAll(".attendance-select").forEach(select => {
                select.addEventListener("change", function () {
                    let appointmentID = this.getAttribute("data-appointment-id");
                    let attendanceValue = this.value;

                    fetch("../app_process/update_attendance.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: `appointment_id=${appointmentID}&pg_attendance=${attendanceValue}`
                    });
                });
            });

            // ✅ Handle Session Completion
            document.querySelectorAll(".complete-session-btn").forEach(button => {
                button.addEventListener("click", function () {
                    let pg_sessionID = this.getAttribute("data-session-id");

                    fetch("../app_process/process_playgroup_completion.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: `pg_session_id=${pg_sessionID}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire("Success!", data.message, "success").then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire("Error!", data.message, "error");
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

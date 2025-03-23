<?php
require_once "../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "head therapist") {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

$session_id = $_GET["session_id"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $session_id = $_POST["pg_session_id"];
    $attendances = $_POST["attendance"];

    // Update attendance records
    foreach ($attendances as $patient_id => $status) {
        $stmt = $connection->prepare("UPDATE playgroup_attendance SET status = ? WHERE pg_session_id = ? AND patient_id = ?");
        $stmt->bind_param("ssi", $status, $session_id, $patient_id);
        $stmt->execute();
    }

    $success = "Attendance updated successfully!";
}

// Load session info and assigned patients
$stmt = $connection->prepare("SELECT * FROM playgroup_sessions WHERE pg_session_id = ?");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    $session = null; // So we can handle it gracefully in the UI
}


$patients = getPatientsForSession($session_id, $connection);

function getPatientsForSession($pg_session_id, $connection) {
    $query = "SELECT a.patient_id, p.first_name, p.last_name
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              WHERE a.pg_session_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $pg_session_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Attendance</title>
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
</head>
<body>
<div class="uk-container uk-margin-top uk-width-2-3@m">
        <?php if (!$session): ?>
            <div class="uk-alert-danger" uk-alert>
                <p>Error: Playgroup session not found or invalid session ID.</p>
            </div>
        <?php else: ?>
            <h2>Edit Attendance for <?= $session['date'] ?> at <?= $session['time'] ?></h2>
        <?php endif; ?>


    <?php if (isset($success)): ?>
        <div class="uk-alert-success" uk-alert>
            <p><?= $success ?></p>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="pg_session_id" value="<?= $session_id ?>">
        <table class="uk-table uk-table-divider">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $p): 
                    $attQuery = $connection->prepare("SELECT status FROM playgroup_attendance WHERE pg_session_id = ? AND patient_id = ?");
                    $attQuery->bind_param("si", $session_id, $p['patient_id']);
                    $attQuery->execute();
                    $attStatus = $attQuery->get_result()->fetch_assoc();
                    $currentStatus = $attStatus['status'] ?? "Present";
                ?>
                    <tr>
                        <td><?= htmlspecialchars($p['first_name'] . " " . $p['last_name']) ?></td>
                        <td>
                            <select class="uk-select" name="attendance[<?= $p['patient_id'] ?>]">
                                <option value="Present" <?= $currentStatus === "Present" ? "selected" : "" ?>>Present</option>
                                <option value="Absent" <?= $currentStatus === "Absent" ? "selected" : "" ?>>Absent</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="uk-button uk-button-primary">Update Attendance</button>
        <a href="playgroup_dashboard.php" class="uk-button uk-button-default">Back</a>
    </form>
</div>
</body>
</html>

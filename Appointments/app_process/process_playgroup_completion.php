<?php
    require_once "../../dbconfig.php";

    session_start();

    if (!isset($_SESSION['account_ID']) || !in_array($_SESSION['account_Type'], ['admin', 'head_therapist'])) {
        echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
        exit();
    }

    $pg_sessionID = $_POST['pg_session_id'];

    $checkAttendanceQuery = "SELECT COUNT(*) AS pending_count FROM appointments 
                            WHERE pg_session_id = ? AND pg_attendance = 'Pending'";
    $stmt = $connection->prepare($checkAttendanceQuery);
    $stmt->bind_param("s", $pg_sessionID);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending = $result->fetch_assoc();

    if ($pending['pending_count'] > 0) {
        echo json_encode(["status" => "error", "message" => "Please mark attendance for all patients before completing the session."]);
        exit();
    }

    $updateQuery = "UPDATE appointments SET status = 'Completed' WHERE pg_session_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("s", $pg_sessionID);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Playgroup session marked as completed."]);
?>

<?php
require_once "../../../dbconfig.php";
session_start();

if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ['admin', 'head therapist'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['exception_date'])) {
    $date = $_POST['exception_date'];

    $stmt = $connection->prepare("DELETE FROM business_hours_exceptions WHERE exception_date = ?");
    $stmt->bind_param("s", $date);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Override for $date removed."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to remove override."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>

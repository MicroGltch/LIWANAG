<?php
require_once "../../../../dbconfig.php";
session_start();

// ✅ Ensure Therapist Access
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$therapistID = $_SESSION['account_ID'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['override_id'])) {
    $overrideID = $_POST['override_id'];

    $query = "DELETE FROM therapist_overrides WHERE override_id = ? AND therapist_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ii", $overrideID, $therapistID);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Override deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete override."]);
    }
}
?>

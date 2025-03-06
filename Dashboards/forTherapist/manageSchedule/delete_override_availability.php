<?php
require_once "../../../dbconfig.php";
session_start();

// ✅ Ensure Therapist Access
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

$therapistID = $_SESSION['account_ID'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['override_id'])) {
    $overrideID = intval($_POST['override_id']); // Ensure it's an integer

    if ($overrideID <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid override ID."]);
        exit();
    }

    // ✅ Check if override exists and belongs to the therapist
    $checkQuery = "SELECT override_id FROM therapist_overrides WHERE override_id = ? AND therapist_id = ?";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param("ii", $overrideID, $therapistID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Override not found or unauthorized."]);
        exit();
    }

    // ✅ Delete the override
    $deleteQuery = "DELETE FROM therapist_overrides WHERE override_id = ? AND therapist_id = ?";
    $deleteStmt = $connection->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $overrideID, $therapistID);

    if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Override deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete override."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>

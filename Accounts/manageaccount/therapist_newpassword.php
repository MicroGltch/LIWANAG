<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../../dbconfig.php";

header('Content-Type: application/json');

if (isset($_POST['account_ID']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    date_default_timezone_set('Asia/Manila');

    $accountID = $_POST['account_ID'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $changedAt = date("Y-m-d H:i:s");

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateSql = "UPDATE users SET account_Password = ?, account_Status = 'Active', updated_at = ? WHERE account_ID = ?";
    $stmt = $connection->prepare($updateSql);
    $stmt->bind_param("ssi", $hashedPassword, $changedAt, $accountID); // Added $changedAt to bind_param

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update password']);
    }
    $stmt->close();
    $connection->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data received']);
}
?>
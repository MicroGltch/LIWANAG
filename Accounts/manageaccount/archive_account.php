<?php
require_once "../../dbconfig.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_id'])) {
    $accountId = $_POST['account_id'];

    try {
        $stmt = $connection->prepare("UPDATE users SET account_status = 'Archived' WHERE account_ID = ?");
        $stmt->bind_param("i", $accountId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Failed to archive user.");
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $connection->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
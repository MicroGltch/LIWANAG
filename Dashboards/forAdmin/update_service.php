<?php
require_once "../../dbconfig.php";
session_start();

// ✅ Restrict Access to Admins
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin"])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}

// Handle the service type update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['therapist_id']) && isset($_POST['service_type'])) {
    global $connection;
    
    $therapist_id = $_POST['therapist_id'];
    $service_type = $_POST['service_type'];
    
    // Validate service type
    if (!in_array($service_type, ['Both', 'Occupational', 'Behavioral'])) {
        $_SESSION['swalType'] = 'error';
        $_SESSION['swalTitle'] = 'Error';
        $_SESSION['swalText'] = 'Invalid service type.';
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Update the service type in the database
    $stmt = $connection->prepare("UPDATE users SET service_Type = ? WHERE account_ID = ?");
    $stmt->bind_param("si", $service_type, $therapist_id);
    
    if ($stmt->execute()) {
        // Success
        $_SESSION['swalType'] = 'success';
        $_SESSION['swalTitle'] = 'Success';
        $_SESSION['swalText'] = 'Service type updated successfully.';
    } else {
        // Error
        $_SESSION['swalType'] = 'error';
        $_SESSION['swalTitle'] = 'Error';
        $_SESSION['swalText'] = 'Failed to update service type: ' . $connection->error;
    }
    
    $stmt->close();
    $connection->close();
    
    // Redirect back to the previous page
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
} else {
    // Invalid request
    $_SESSION['swalType'] = 'error';
    $_SESSION['swalTitle'] = 'Error';
    $_SESSION['swalText'] = 'Invalid request.';
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
?>
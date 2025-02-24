<?php
require_once "../../dbconfig.php";

$admin_email = "therapist3@example.com";
$admin_password = password_hash("Therapist123", PASSWORD_DEFAULT); // Secure hashing
$admin_role = "Therapist";
$admin_status = "Active";

$query = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status)
          VALUES ('Therapist3', 'Test3', ?, ?, 'Admin Office', '09124456789', ?, ?)";

$stmt = $connection->prepare($query);
$stmt->bind_param("ssss", $admin_email, $admin_password, $admin_role, $admin_status);

if ($stmt->execute()) {
    echo "Admin account created successfully!";
} else {
    echo "Error creating admin account: " . $stmt->error;
}

$stmt->close();
?>

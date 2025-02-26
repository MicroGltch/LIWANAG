<?php
require_once "../../dbconfig.php";

/**
 * Function to create a new user account.
 * 
 * @param string $first_name
 * @param string $last_name
 * @param string $email
 * @param string $password
 * @param string $address
 * @param string $phone_number
 * @param string $role ("Client", "Therapist", "Admin", etc.)
 * @param string $status ("Active", "Inactive", etc.)
 * @return string Success or error message.
 */
function create_account($first_name, $last_name, $email, $password, $address, $phone_number, $role, $status) {
    global $connection;

    // Hash the password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssssssss", $first_name, $last_name, $email, $hashed_password, $address, $phone_number, $role, $status);
    
    if ($stmt->execute()) {
        return "Account for $role ($email) created successfully!";
    } else {
        return "Error creating account: " . $stmt->error;
    }

    $stmt->close();
}

// ✅ Example Usage
echo create_account("Therapist3", "Test3", "therapist3@example.com", "Therapist123", "Admin Office", "09124456789", "Therapist", "Active");
?>

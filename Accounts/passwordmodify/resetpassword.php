<?php
// require_once "../../dbconfig.php";

// if (!isset($_GET['token'])) {
//     die("Invalid request.");
// }

// $token = $_GET['token'];

// // Check if token exists and is valid
// $query = "SELECT account_Email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()";
// $stmt = $connection->prepare($query);
// $stmt->bind_param("s", $token);
// $stmt->execute();
// $result = $stmt->get_result();

// if ($result->num_rows == 1) {
//     $row = $result->fetch_assoc();
//     $email = $row['account_Email'];
// } else {
//     die("Invalid or expired token.");
// }
?>

<!-- <form method="POST" action="resetpasswordprocess.php">
    <input type="hidden" name="email" value=" <?php echo $email; ?> ">
    <label>New Password:</label>
    <input type="password" name="password" required>
    <label>Confirm Password:</label>
    <input type="password" name="confirm_password" required>
    <button type="submit">Reset Password</button>
</form> -->

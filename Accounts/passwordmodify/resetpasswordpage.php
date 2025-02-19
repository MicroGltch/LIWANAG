<?php
require_once "../../dbconfig.php";

// Debugging: Check if token and email are present
if (!isset($_GET['token']) || !isset($_GET['email'])) {
    die("Invalid request. Debug Info: Token - " . ($_GET['token'] ?? "Missing") . " | Email - " . ($_GET['email'] ?? "Missing"));
}

$token = $_GET['token'];
$email = $_GET['email'];

// Retrieve the stored hashed token from the database
$query = "SELECT s.reset_token 
          FROM security_tokens s
          INNER JOIN users u ON s.account_ID = u.account_ID
          WHERE u.account_Email = ? 
          AND s.reset_token_expiry > NOW()";

$stmt = $connection->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $row = $result->fetch_assoc();
    $storedHashedToken = $row['reset_token'];

    // Verify the token using password_verify()
    if (!password_verify($token, $storedHashedToken)) {
        die("Invalid or expired token.");
    }
} else {
    die("Invalid or expired token.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width" />
    <title>LIWANAG - Reset Password</title>
    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css"/>
</head>

<body>
    <div class="uk-flex uk-flex-center uk-flex-middle uk-height-viewport">
        <div class="create-acc-card uk-card uk-card-default uk-card-body uk-width-1-2 form-card">
            <h3 class="uk-card-title uk-flex uk-flex-center">Reset Password</h3>
            <p class="uk-flex uk-flex-center uk-text-center">To finalize your password reset, please provide your new password below.</p>

            <form method="POST" action="resetpasswordprocess.php" class="uk-form-stacked uk-grid-medium" uk-grid>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <!-- New Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label">New Password</label>
                    <div class="uk-form-controls">
                        <input name="password" class="uk-input" type="password" placeholder="Input your Password..." required>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label">Confirm Password</label>
                    <div class="uk-form-controls">
                        <input name="confirm_password" class="uk-input" type="password" placeholder="Reinput your Password..." required>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button class="uk-button uk-button-primary uk-width-1@s uk-width-1@l">Submit</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

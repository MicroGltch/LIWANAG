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

// Retrieve the old password from the database
$query = "SELECT u.account_Password FROM users u
          INNER JOIN security_tokens s ON u.account_ID = s.account_ID
          WHERE u.account_Email = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $row = $result->fetch_assoc();
    $existingPassword = $row['account_Password'];
    $jsExistingPassword = $existingPassword; // Store it for JavaScript
} else {
    $jsExistingPassword = ''; // Handle the case where the email is not found
}

$error_message = isset($_GET['error']) ? urldecode($_GET['error']) : '';


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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>
    <div class="uk-flex uk-flex-center uk-flex-middle uk-height-viewport">
        <div class="create-acc-card uk-card uk-card-default uk-card-body uk-width-1-2 form-card">
            <h3 class="uk-card-title uk-flex uk-flex-center">Reset Password</h3>
            <p class="uk-flex uk-flex-center uk-text-center">To finalize your password reset, please provide your new password below.</p>

            <form id="resetPasswordForm" method="POST" action="resetpasswordprocess.php" class="uk-form-stacked uk-grid-medium" uk-grid>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <?php if (!empty($error_message)) : ?>
                    <div class="uk-alert-danger" uk-alert style="background-color: #ffcccc; color: #cc0000; text-align: center; align-items: center;">
                        <a class="uk-alert-close" uk-close></a>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>

                <!-- New Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label">New Password</label>
                    <div class="uk-form-controls uk-position-relative">
                        <input id="password" name="password" class="uk-input" type="password" placeholder="Input your Password..." required>
                        <span id="togglePassword" class="toggle-password uk-position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                            <i class="fa-solid fa-eye"></i>
                        </span>
                        <span id="passwordError" class="uk-text-danger"></span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label">Confirm Password</label>
                    <div class="uk-form-controls uk-position-relative">
                        <input id="confirm_password" name="confirm_password" class="uk-input" type="password" placeholder="Reinput your Password..." required>
                        <span id="toggleConfirmPassword" class="toggle-password uk-position-absolute" style="right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                            <i class="fa-solid fa-eye"></i>
                        </span>
                        <span id="confirmPasswordError" class="uk-text-danger"></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" class="uk-button uk-button-primary uk-width-1@s uk-width-1@l">Submit</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.querySelectorAll(".toggle-password").forEach(item => {
            item.addEventListener("click", function () {
                let input = this.previousElementSibling;
                let icon = this.querySelector("i");

                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.remove("fa-eye");
                    icon.classList.add("fa-eye-slash");
                } else {
                    input.type = "password";
                    icon.classList.remove("fa-eye-slash");
                    icon.classList.add("fa-eye");
                }
            });
        });
        document.getElementById("resetPasswordForm").addEventListener("submit", function (event) {
            let valid = true;
            let password = document.getElementById("password").value;
            let confirmPassword = document.getElementById("confirm_password").value;
            let passwordError = document.getElementById("passwordError");
            let confirmPasswordError = document.getElementById("confirmPasswordError");
            let passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\-_])[A-Za-z\d@$!%*?&\-_]{8,20}$/;

            // Password Regex Validation
            if (!passwordRegex.test(password)) {
                passwordError.textContent = "Password must be 8-20 chars, with uppercase, lowercase, number, and special char.";
                valid = false;
            } else {
                passwordError.textContent = "";
            }

            // Confirm Password Validation
            if (password !== confirmPassword) {
                confirmPasswordError.textContent = "Passwords do not match.";
                valid = false;
            } else {
                confirmPasswordError.textContent = "";
            }

            // Check if new password is the same as the old password
            let existingPassword = '<?php echo $jsExistingPassword; ?>';

            if (password === existingPassword) {
                passwordError.textContent = "New password cannot be the same as your old password.";
                valid = false;
            }

            if (!valid) {
                event.preventDefault(); // Prevent form submission
                Swal.fire({
                    title: "Error",
                    text: "Please correct the errors in the form.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
            }
        });
    </script>

    <?php
    session_start(); 
    if (isset($_SESSION['status']) && isset($_SESSION['message'])) {
        echo "<script>
            Swal.fire({
                icon: '" . ($_SESSION['status'] == 'success' ? 'success' : 'error') . "',
                title: '" . ($_SESSION['status'] == 'success' ? 'Success' : 'Error') . "',
                text: '" . $_SESSION['message'] . "',
                allowOutsideClick: false
            }).then((result) => {
                window.location.href = '../loginpage.php'; 
            });
        </script>";
        unset($_SESSION['status']); 
        unset($_SESSION['message']);
    }
    ?>

</body>
</html>

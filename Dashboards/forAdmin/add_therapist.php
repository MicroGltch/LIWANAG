<?php

require_once "../../dbconfig.php";
require_once "../../Accounts/signupverify/vendor/autoload.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();

// ✅ Restrict Access to Admins
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["admin"])) {
    header("Location: ../../Accounts/loginpage.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Therapist</title>
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> html, body { background-color: #ffffff !important; } </style>
</head>

<body>
    <h2>Add a New Therapist Form</h2>

    <form id="addTherapist" class="uk-form-stacked" method="POST" action="add_therapist.php">
    <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s" uk-grid>
        <div>
            <label class="uk-form-label">First Name</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="text" name="therapist_fname" required value="<?php echo isset($_SESSION['form_data']['therapist_fname']) ? htmlspecialchars($_SESSION['form_data']['therapist_fname']) : ''; ?>">
                <?php if(isset($_SESSION['update_errors']['firstName'])): ?>
                    <div class="uk-text-danger uk-text-small"><?php echo $_SESSION['update_errors']['firstName']; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <label class="uk-form-label">Last Name</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="text" name="therapist_lname" required value="<?php echo isset($_SESSION['form_data']['therapist_lname']) ? htmlspecialchars($_SESSION['form_data']['therapist_lname']) : ''; ?>">
                <?php if(isset($_SESSION['update_errors']['lastName'])): ?>
                    <div class="uk-text-danger uk-text-small"><?php echo $_SESSION['update_errors']['lastName']; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <label class="uk-form-label">Email</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="email" name="therapist_email" required value="<?php echo isset($_SESSION['form_data']['therapist_email']) ? htmlspecialchars($_SESSION['form_data']['therapist_email']) : ''; ?>">
                <?php if(isset($_SESSION['update_errors']['email'])): ?>
                    <div class="uk-text-danger uk-text-small"><?php echo $_SESSION['update_errors']['email']; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <label class="uk-form-label">Phone Number (Format: 09XXXXXXXXX)</label>
            <div class="uk-form-controls">
                <input class="uk-input" type="text" name="therapist_phone" required value="<?php echo isset($_SESSION['form_data']['therapist_phone']) ? htmlspecialchars($_SESSION['form_data']['therapist_phone']) : ''; ?>">
                <?php if(isset($_SESSION['update_errors']['phoneNumber'])): ?>
                    <div class="uk-text-danger uk-text-small"><?php echo $_SESSION['update_errors']['phoneNumber']; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <label class="uk-form-label">Role</label>
            <div class="uk-form-controls">
                <select class="uk-select" name="therapist_role" required>
                    <option value="therapist" <?php echo (isset($_SESSION['form_data']['therapist_role']) && $_SESSION['form_data']['therapist_role'] == 'therapist') ? 'selected' : ''; ?>>Therapist</option>
                    <option value="head therapist" <?php echo (isset($_SESSION['form_data']['therapist_role']) && $_SESSION['form_data']['therapist_role'] == 'head therapist') ? 'selected' : ''; ?>>Head Therapist</option>
                </select>
            </div>
        </div>
        <div>
            <label class="uk-form-label">Service Type</label>
            <div class="uk-form-controls">
                <select class="uk-select" name="service_type" required>
                    <option value="Both" <?php echo (isset($_SESSION['form_data']['service_type']) && $_SESSION['form_data']['service_type'] == 'Both') ? 'selected' : ''; ?>>Both</option>
                    <option value="Occupational" <?php echo (isset($_SESSION['form_data']['service_type']) && $_SESSION['form_data']['service_type'] == 'Occupational') ? 'selected' : ''; ?>>Occupational</option>
                    <option value="Behavioral" <?php echo (isset($_SESSION['form_data']['service_type']) && $_SESSION['form_data']['service_type'] == 'Behavioral') ? 'selected' : ''; ?>>Behavioral</option>
                </select>
            </div>
        </div>
    </div>
    <div class="uk-margin-top">
        <button class="uk-button uk-button-primary uk-align-right" type="submit" id="registerTherapist" style="border-radius: 15px; background-color:#1e87f0; color:white;">Register</button>
    </div>
</form>

    <?php
    // Display SweetAlert based on session status
    if(isset($_SESSION['swalType']) && isset($_SESSION['swalTitle']) && isset($_SESSION['swalText'])) {
        echo "<script>
            Swal.fire({
                title: '" . $_SESSION['swalTitle'] . "',
                text: '" . $_SESSION['swalText'] . "',
                icon: '" . $_SESSION['swalType'] . "',
                confirmButtonText: 'OK'
            });
        </script>";
        // Clear the SweetAlert session variables
        unset($_SESSION['swalType']);
        unset($_SESSION['swalTitle']);
        unset($_SESSION['swalText']);
    }
    
    // Clear form data and errors if needed
    if(isset($_SESSION['clearForm']) && $_SESSION['clearForm']) {
        unset($_SESSION['update_errors']);
        unset($_SESSION['form_data']);
        unset($_SESSION['clearForm']);
    }
    ?>
</body>
</html>

<?php
// ✅ Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    global $connection;
    date_default_timezone_set('Asia/Manila');

    // Store form data in session to preserve it in case of errors
    $_SESSION['form_data'] = $_POST;
    $_SESSION['update_errors'] = [];

    $firstName = ucwords(strtolower(trim($_POST["therapist_fname"])));
    $lastName = ucwords(strtolower(trim($_POST["therapist_lname"])));
    $email = trim($_POST["therapist_email"]);
    $phone = trim($_POST["therapist_phone"]);
    $role = $_POST["therapist_role"] ?? "therapist";
    $service_type = $_POST["service_type"] ?? "Both";
    
    // ** Validate First Name **
    if (!preg_match("/^[A-Za-z ]{2,30}$/", $firstName)) {
        $_SESSION['update_errors']['firstName'] = "Only letters allowed (2-30 characters).";
    }

    // ** Validate Last Name **
    if (!preg_match("/^[A-Za-z ]{2,30}$/", $lastName)) {
        $_SESSION['update_errors']['lastName'] = "Only letters allowed (2-30 characters).";
    }

    // ** Validate Email **
    if (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
        $_SESSION['update_errors']['email'] = "Invalid email format.";
    }

    // ** Validate Mobile Number (Auto-convert format) **
    $phone = preg_replace('/\s+/', '', $phone); // Remove spaces

    if (!preg_match("/^09\d{9}$/", $phone)) {  
        $_SESSION['update_errors']['phoneNumber'] = "Phone number must be in the format 09XXXXXXXXX.";  
    }

    // If errors exist, redirect back to form
    if (!empty($_SESSION['update_errors'])) {
        $_SESSION['swalType'] = 'error';
        $_SESSION['swalTitle'] = 'Validation Error';
        $_SESSION['swalText'] = 'Please fix the validation errors.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Proceed with creating therapist
    $therapist_name = $firstName . " " . $lastName;
    $created = date("Y-m-d H:i:s");
    $defaultPassword = password_hash("Liwanag@2025", PASSWORD_DEFAULT);
    $accountAddress = "Admin Office";
    $accountType = $role;
    $accountStatus = "Pending";

    // Check if email or phone already exists
    $checkDuplicate = $connection->prepare("SELECT * FROM users WHERE account_Email = ? OR account_PNum = ?");
    $checkDuplicate->bind_param("ss", $email, $phone);
    $checkDuplicate->execute();
    $result = $checkDuplicate->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['account_Email'] == $email) {
            $_SESSION['swalType'] = 'error';
            $_SESSION['swalTitle'] = 'Error';
            $_SESSION['swalText'] = 'Email is already registered.';
        } else {
            $_SESSION['swalType'] = 'error';
            $_SESSION['swalTitle'] = 'Error';
            $_SESSION['swalText'] = 'Phone number is already registered.';
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        // Insert new therapist into database
        $stmt = $connection->prepare("INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status, service_Type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssss", $firstName, $lastName, $email, $defaultPassword, $accountAddress, $phone, $accountType, $accountStatus, $service_type, $created, $created);

        if ($stmt->execute()) {
            // Clear form data on success
            $_SESSION['clearForm'] = true;
            
            if (send_email_notification($email, $therapist_name, $accountType, $service_type)) {
                $_SESSION['swalType'] = 'success';
                
                // Change the SweetAlert title and message based on role
                if ($role == "head therapist") {
                    $_SESSION['swalTitle'] = 'Head Therapist Added';
                    $_SESSION['swalText'] = "Successfully registered $therapist_name as a Head Therapist. They will receive login credentials via email.";
                } else {
                    $_SESSION['swalTitle'] = 'Therapist Added';
                    $_SESSION['swalText'] = "Successfully registered $therapist_name as a Therapist. They will receive login credentials via email.";
                }
            } else {
                $_SESSION['swalType'] = 'warning';
                
                // Role-specific warning message for email failure
                if ($role == "head therapist") {
                    $_SESSION['swalTitle'] = 'Head Therapist Added - Email Failed';
                    $_SESSION['swalText'] = "$therapist_name has been registered as a Head Therapist, but the email notification failed. Please contact them manually.";
                } else {
                    $_SESSION['swalTitle'] = 'Therapist Added - Email Failed';
                    $_SESSION['swalText'] = "$therapist_name has been registered as a Therapist, but the email notification failed. Please contact them manually.";
                }
            }
        } else {
            $_SESSION['swalType'] = 'error';
            $_SESSION['swalTitle'] = 'Database Error';
            $_SESSION['swalText'] = 'Failed to add ' . ($role == "head therapist" ? "Head Therapist" : "Therapist") . ': ' . $connection->error;
        }
        $stmt->close();
    }
    $checkDuplicate->close();
    $connection->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

    // ✅ Email Sender Function
    function send_email_notification($email, $therapist_name, $accountType, $service_type) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@myliwanag.com';
        $mail->Password = '[l/+1V/B4'; // Replace with actual password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
        $mail->addAddress($email, $therapist_name);
        $mail->isHTML(true);
        $mail->Subject = "Welcome to Little Wanderer's Therapy Center - " . ucwords($accountType) . " Account Created";

        // Different welcome message based on role
        $roleSpecificWelcome = ($accountType == "head therapist") 
            ? "We are excited to welcome you as a <strong>Head Therapist</strong> at <strong>Little Wanderer's Therapy Center</strong>. As a Head Therapist, you will have additional responsibilities in overseeing therapy sessions and other therapists."
            : "We are excited to welcome you as a <strong>Therapist</strong> at <strong>Little Wanderer's Therapy Center</strong>. We look forward to your contribution in providing quality therapy services.";
        
        // Service type description
        $serviceTypeInfo = "";
        switch($service_type) {
            case "Both":
                $serviceTypeInfo = "You are registered to provide <strong>both Occupational and Behavioral therapy services</strong>. This means you will be able to handle a wide range of therapy needs for our clients.";
                break;
            case "Occupational":
                $serviceTypeInfo = "You are registered to provide <strong>Occupational therapy services</strong>. Your expertise will help our clients develop the skills needed for daily living and working.";
                break;
            case "Behavioral":
                $serviceTypeInfo = "You are registered to provide <strong>Behavioral therapy services</strong>. Your expertise will help our clients improve their behaviors, reactions, and interactions.";
                break;
        }

        $emailBody = "
            <h3>Welcome to Little Wanderer's Therapy Center</h3>
            <p>Dear <strong>$therapist_name</strong>,</p>
            <p>$roleSpecificWelcome Your account has been successfully created and is currently <strong>not yet activated</strong>.</p>
            
            <p>$serviceTypeInfo</p>

            <h4>Login Credentials:</h4>
            <ul>
                <li><strong>Email:</strong> $email</li>
                <li><strong>Temporary Password:</strong> Liwanag@2025</li>
                <li><strong>Service Type:</strong> $service_type</li>
            </ul>

            <p>To activate your account, please log in using the credentials above and change your password immediately.</p>

            <h4>Next Steps:</h4>
            <ol>
                <li>Go to the website to Login</li>
                <li>Enter your email and temporary password.</li>
                <li>Follow the prompts to update your password.</li>
            </ol>

            <p>If you encounter any issues, please reach out to our support team.</p>

            <p>We look forward to working with you!</p>

            <p>Best Regards,<br>
            <strong>Little Wanderer's Therapy Center Team</strong></p>
        ";


        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
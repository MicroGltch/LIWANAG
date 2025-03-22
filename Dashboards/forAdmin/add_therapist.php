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
        <div class="uk-width-1-2@s">
            <label class="uk-form-label">First Name</label>
            <input class="uk-input" type="text" name="therapist_fname" required>
        </div>
        <div class="uk-width-1-2@s">
            <label class="uk-form-label">Last Name</label>
            <input class="uk-input" type="text" name="therapist_lname" required>
        </div>
        <div class="uk-width-1-2@s">
            <label class="uk-form-label">Email</label>
            <input class="uk-input" type="email" name="therapist_email" required>
        </div>
        <div class="uk-width-1-2@s">
            <label class="uk-form-label">Phone Number</label>
            <input class="uk-input" type="text" name="therapist_phone" required>
        </div>
        <div class="uk-width-1-2@s">
            <label class="uk-form-label">Role</label>
            <select class="uk-select" name="therapist_role" required>
                <option value="therapist">Therapist</option>
                <option value="head therapist">Head Therapist</option>
            </select>
        </div>
        <div class="uk-width-1-1 uk-text-right uk-margin-top">
            <button class="uk-button uk-button-primary" type="submit" id="registerTherapist">Register</button>
        </div>
    </form>
</body>
</html>

<?php
// ✅ Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    global $connection;
    date_default_timezone_set('Asia/Manila');

    $firstName = ucfirst(strtolower($_POST["therapist_fname"]));
    $lastName = ucfirst(strtolower($_POST["therapist_lname"]));
    $therapist_name = $firstName . " " . $lastName;
    $email = trim($_POST["therapist_email"]);
    $phone = trim($_POST["therapist_phone"]);
    $role = $_POST["therapist_role"] ?? "therapist";
    $created = date("Y-m-d H:i:s");

    $defaultPassword = password_hash("Liwanag@2025", PASSWORD_DEFAULT);
    $accountAddress = "Admin Office";
    $accountType = $role;
    $accountStatus = "Pending";

    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($accountType)) {
        echo "<script>
            window.parent.postMessage({
                'type': 'swal',
                'title': 'Error',
                'text': 'All fields are required.',
                'icon': 'error'
            }, '*');
        </script>";
    } else {
        $checkEmail = $connection->prepare("SELECT * FROM users WHERE account_Email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $result = $checkEmail->get_result();

        if ($result->num_rows > 0) {
            echo "<script>Swal.fire('Error', 'Email is already registered.', 'error');</script>";
        } else {
            $stmt = $connection->prepare("INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, account_Status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $firstName, $lastName, $email, $defaultPassword, $accountAddress, $phone, $accountType, $accountStatus, $created, $created);

            if ($stmt->execute()) {
                send_email_notification($email, $therapist_name, $accountType);
                echo "<script>
                    console.log('postMessage sent: Success');
                    window.parent.postMessage({
                        'type': 'swal',
                        'title': 'Success',
                        'text': 'Therapist added and emailed successfully!',
                        'icon': 'success'
                    }, '*');
                </script>";
            } else {
                echo "<script>
                    window.parent.postMessage({
                        'type': 'swal',
                        'title': 'Error',
                        'text': 'Failed to add therapist.',
                        'icon': 'error'
                    }, '*');
                </script>";
            }
            $stmt->close();
        }
        $checkEmail->close();
    }
    $connection->close();
}

// ✅ Email Sender Function
function send_email_notification($email, $therapist_name, $accountType) {
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

        $emailBody = "
            <h3>Welcome to Little Wanderer's Therapy Center</h3>
            <p>Dear <strong>$therapist_name</strong>,</p>
            <p>We are excited to welcome you as a <strong>" . ucwords($accountType) . "</strong> at <strong>Little Wanderer's Therapy Center</strong>. Your account has been successfully created and is currently <strong>not yet activated</strong>.</p>

            <h4>Login Credentials:</h4>
            <ul>
                <li><strong>Email:</strong> $email</li>
                <li><strong>Temporary Password:</strong> Liwanag@2025</li>
            </ul>

            <p>To activate your account, please log in using the credentials above and change your password immediately.</p>

            <h4>Next Steps:</h4>
            <ol>
                <li>Go to the website to Login</li>
                <li>Enter your email and temporary password.</li>
                <li>Follow the prompts to update your password.</li>
            </ol>

            <p>If you encounter any issues, please reach out to our support team.</p>

            <p>We look forward to working with you in providing quality therapy services.</p>

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

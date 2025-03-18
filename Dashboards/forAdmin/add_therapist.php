<?php
require_once "../../dbconfig.php";
require_once "../../Accounts/signupverify/vendor/autoload.php"; // ✅ Load PHPMailer
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
    <title>Add Therapist - Test</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!--SWAL-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>
    <script>
        console.log('Session Username:', <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>);
    </script>
    <!-- Navbar -->
    <nav class="uk-navbar-container logged-in">
        <div class="uk-container">
            <div uk-navbar>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li>
                            <a href="#" class="uk-navbar-item">
                            <img class="profile-image" src="../../CSS/default.jpg" alt="Profile Image" uk-img>
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;"> <?php echo $_SESSION['username']; ?>
                        </li>
                        <li><a href="../../Accounts/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <hr class="solid">

    <!-- Main Content -->
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-height-viewport">
        <!--Sidebar-->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
            <button class="uk-button uk-button-default uk-hidden@m uk-width-1-1 uk-margin-bottom sidebar-toggle" type="button">
                Menu <span uk-navbar-toggle-icon></span>
            </button>
            <div class="sidebar-nav">
                <ul class="uk-nav uk-nav-default">
                    <li><a href="../admindashboard.php">Dashboard</a></li>
                    <li><a href="manageWebpage/timetable_settings.php">Manage Timetable Settings</a></li>
                    <li><a href="../../Appointments/app_manage/view_all_appointments.php">View All Appointments</a></li>
                    <li class="uk-active"><a href="add_therapist.php">Manage Therapists (Adding Only)</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <div class="uk-width-1-1">
                <h2>Add Therapist Form</h2>

                <form id="addTherapist" class="uk-form-stacked uk-grid-medium" uk-grid method="POST" action="add_therapist.php">

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


                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button class="uk-button uk-button-primary" type="submit" id="registerTherapist">Register</button>
                        </div>
                    </form>

            </div>
        </div>
</body>

</html>

<?php
// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    global $connection; // ✅ Use the existing connection
    date_default_timezone_set('Asia/Manila');

    $firstName = ucfirst(strtolower($_POST["therapist_fname"]));
    $lastName = ucfirst(strtolower($_POST["therapist_lname"]));
    $therapist_name = $firstName . " " . $lastName;
    $email = trim($_POST["therapist_email"]);
    $phone = trim($_POST["therapist_phone"]);
    $created  = date("Y-m-d H:i:s");

    // ✅ Secure Default Password
    $defaultPassword = password_hash("Liwanag@2025", PASSWORD_DEFAULT);

    $accountAddress = "Admin Office";
    $accountType = "therapist";
    $accountStatus = "Pending";

    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone)) {
        echo "<script>Swal.fire('Error', 'All fields are required.', 'error');</script>";
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
                send_email_notification($email, $therapist_name);
                echo "<script>Swal.fire('Success', 'Therapist added and emailed successfully!', 'success');</script>";
            } else {
                echo "<script>Swal.fire('Error', 'Failed to add therapist.', 'error');</script>";
            }
            $stmt->close();
        }
        $checkEmail->close();
    }
    $connection->close();
}

// ✅ Function to Send Email Notification for New Appointments
function send_email_notification($email, $therapist_name) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com'; // Change this to your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@myliwanag.com'; // Change to your email
        $mail->Password = '[l/+1V/B4'; // Change to your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('no-reply@myliwanag.com', "Little Wanderer's Therapy Center");
        $mail->addAddress($email, $therapist_name);
        $mail->isHTML(true);
        $mail->Subject = "Welcome to Little Wanderer's Therapy Center - Therapist Account Created";

        $emailBody = "
            <h3>Welcome to Little Wanderer's Therapy Center</h3>
            <p>Dear <strong>$therapist_name</strong>,</p>
            <p>We are excited to welcome you as a therapist at <strong>Little Wanderer's Therapy Center</strong>. Your account has been successfully created and is currently <strong>pending approval</strong>.</p>

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

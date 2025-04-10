<?php
include "../dbconfig.php";
session_start();

// Check if the user is logged in (basic check)
if (!isset($_SESSION['account_ID'])) {
    header("Location: ../Accounts/loginpage.php");
    exit;
}

$userid = $_SESSION['account_ID'];

// Fetch user data from the database
$stmt = $connection->prepare("SELECT account_FName, account_LName, account_Email, account_PNum, profile_picture FROM users WHERE account_ID = ?");
$stmt->bind_param("s", $userid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $firstName = $userData['account_FName'];
    $lastName = $userData['account_LName'];
    $email = $userData['account_Email'];
    $phoneNumber = $userData['account_PNum'];
    // Determine the profile picture path
    if ($userData['profile_picture']) {
        $profilePicture = '/LIWANAG/uploads/profile_pictures/' . $userData['profile_picture']; // Corrected path
    } else {
        $profilePicture = '../CSS/default.jpg';
    }
    // $profilePicture = $userData['profile_picture'] ? '../uploads/' . $userData['profile_picture'] : '../CSS/default.jpg';
} else {
    echo "No Data Found.";
}

$stmt->close();

// Fetch appointments for the logged-in client (from client_view_appointments.php)
$client_id = $_SESSION['account_ID'];
$query = "SELECT a.appointment_id, a.date, a.time, a.status, a.session_type, a.edit_count, p.first_name AS patient_name FROM appointments a JOIN patients p ON a.patient_id = p.patient_id WHERE a.account_id = ? ORDER BY a.date ASC, a.time ASC";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);


// EDIT PATIENT FORM
// Fetch patients for the dropdown
$patientsQuery = "SELECT patient_id, first_name, last_name, bday FROM patients WHERE account_id = ?";
$stmt = $connection->prepare($patientsQuery);
$stmt->bind_param("i", $_SESSION['account_ID']);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();




// Fetch settings from database
$settingsQuery = "SELECT max_days_advance, min_days_advance, blocked_dates,
                          initial_eval_duration, playgroup_duration, service_ot_duration, service_bt_duration 
                   FROM settings LIMIT 1";

$result = $connection->query($settingsQuery);
$settings = $result->fetch_assoc();

// Convert blocked dates into an array
$blockedDates = !empty($settings["blocked_dates"]) ? explode(",", $settings["blocked_dates"]) : [];

// Set up PHP arrays for JS conversion
$sessionDurations = [
    "playgroup" => (int) $settings["playgroup_duration"],
    "initial_evaluation" => (int) $settings["initial_eval_duration"],
    "occupational_therapy" => (int) $settings["service_ot_duration"],
    "behavioral_therapy" => (int) $settings["service_bt_duration"]
];

$timetableSettings = [
    "maxDaysAdvance" => (int) $settings["max_days_advance"],
    "minDaysAdvance" => (int) $settings["min_days_advance"],
    "blockedDates" => $blockedDates
];

// Send data to JavaScript
echo "<script>
        const sessionDurations = " . json_encode($sessionDurations) . ";
        const timetableSettings = " . json_encode($timetableSettings) . ";
      </script>";



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width" />
    <title>LIWANAG - Dashboard</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../CSS/style.css" type="text/css" />

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        .button-container {
            display: flex;
            flex-direction: column;
            align-items: center;

        }

        .button-container .uk-button {
            width: 100%;
            max-width: 300px;
            margin-bottom: 5px;
            border-radius: 15px;
            
            /* Adjust the spacing between buttons as needed */
        }

        .button-container .uk-button:last-child {
            margin-bottom: 0;
            /* Remove margin from the last button */
        }
    </style>
</head>

</head>

<body>
    <script>
        console.log('Session User ID:', <?php echo isset($_SESSION['account_ID']) ? json_encode($_SESSION['account_ID']) : 'null'; ?>);
    </script>
    <!-- Navbar -->
    <nav class="uk-navbar-container logged-in">
        <div class="uk-container">
            <div uk-navbar>
                <div class="uk-navbar-left">
                    <ul class="uk-navbar-nav">
                    <li><a href="#faqs-modal" uk-toggle>FAQs</a></li>
                    <li><a href="#tnc-modal" uk-toggle>Terms and Conditions</a></li>
                    </ul>
                </div>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="../homepage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li>
                            <a href="#" class="uk-navbar-item">
                                <img src="<?php echo $profilePicture . '?t=' . time(); ?>" alt="Profile" class="navbar-profile-pic profile-image">
                            </a>
                        </li>
                        <li style="display: flex; align-items: center;">
                            <?php
                            if (isset($_SESSION['account_ID'])) {

                                $account_ID = $_SESSION['account_ID'];
                                $query = "SELECT account_FName FROM users WHERE account_ID = ?";
                                $stmt = $connection->prepare($query);
                                $stmt->bind_param("i", $account_ID);
                                $stmt->execute();
                                $stmt->bind_result($account_FN);
                                $stmt->fetch();
                                $stmt->close();
                                $connection->close();


                                echo htmlspecialchars($account_FN);
                            } else {
                                echo '<a href="../Accounts/loginpage.php">Login</a>';
                            }
                            ?>
                        </li>
                        <?php if (isset($_SESSION['account_ID'])): ?>
                            <li><a href="../Accounts/logout.php">Logout</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </nav>



    <hr class="solid">

    <!-- Main Content -->
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-height-viewport">
        <!-- Sidebar -->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
            <button class="uk-button uk-button-default uk-hidden@m uk-width-1-1 uk-margin-bottom sidebar-toggle" type="button">
                Menu <span uk-navbar-toggle-icon></span>
            </button>

            <div class="sidebar-nav">
                <ul class="uk-nav uk-nav-default">


                    <h4 style="font-weight: bold;">Client Dashboard</h4>

                    <li class="uk-parent">
                    <li>
                        <span>Appointments</span>
                    </li>

                    <li>
                        <a href="#appointments" onclick="showSection('appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span>Your Appointments</a>
                    </li>

                    <li>
                        <a href="#book-appointment" onclick="showSection('book-appointment')"><span class="uk-margin-small-right" uk-icon="user"></span> Book Appointment</a>
                    </li>

                    </li>

                    <hr>

                    <li class="uk-parent">
                    <li>
                        <span>Patients</span>
                    </li>
                    <li>
                        <a href="#register-patient" onclick="showSection('register-patient')"><span class="uk-margin-small-right" uk-icon="user"></span> Register a Patient</a>
                    </li>

                    <li><a href="#view-registered-patients" onclick="showSection('view-registered-patients')"><span class="uk-margin-small-right" uk-icon="user"></span> View Registered Patients</a></li>

                    </li>

                    <hr>

                    <li class="uk-parent">
                    <li>
                        <span>Your Account</span>
                    </li>
                    <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Account Details</a></li>

                </ul>
            </div>
        </div>


        <!-- Content Area -->
        
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <div id="appointments" class="section">
                <h1 class="uk-text-bold">Appointments</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <div class="uk-margin-small-bottom uk-flex uk-flex-between uk-flex-wrap">
                        <div>
                            <button class="uk-button uk-button-default filter-btn" data-filter="all" style="margin-right: 10px;border-radius: 15px;" >All</button>
                            <button class="uk-button uk-button-primary filter-btn" data-filter="upcoming" style="margin-right: 10px;border-radius: 15px;" >Upcoming</button>
                            <button class="uk-button uk-button-secondary filter-btn" data-filter="past" style="margin-right: 10px;border-radius: 15px;">Past</button>
                        </div>
                    </div>

                    <table class="uk-table uk-table-striped">
                    <input class="uk-input uk-margin-bottom" type="text" id="appointmentSearch" placeholder="Search appointments...">

                    <thead>
                            <tr>
                                <th data-sort="date" style="text-align: left;" class="table-head"><span class="no-break">Date <span uk-icon="icon: arrow-down-arrow-up"></span></span> </th>
                                <th data-sort="time" style="text-align: left;" class="table-head"><span class="no-break"></span>Time <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th data-sort="session" style="text-align: left;" class="table-head"><span class="no-break"></span>Session Type <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th data-sort="patient" style="text-align: left;" class="table-head"><span class="no-break"></span>Patient <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th data-sort="status" style="text-align: left;" class="table-head"><span class="no-break"></span>Status <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                                <th class="table-head">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="table-body">
                            <?php foreach ($appointments as $appointment): ?>
                                <tr data-date="<?= $appointment['date']; ?>">
                                    <td style="text-align: left;"><?= date('F j, Y', strtotime($appointment['date'])); ?></td>
                                    <td style="text-align: left;"><?= date('g:i A', strtotime($appointment['time'])); ?></td>
                                    <td style="text-align: left;"><?= ucwords(htmlspecialchars($appointment['session_type'])); ?></td>
                                    <td style="text-align: left;" ><?= htmlspecialchars($appointment['patient_name']); ?></td>
                                    <td style="text-align: left;"><?= ucfirst($appointment['status']); ?></td>
                                    <td>
                                        <div class="button-container">
                                            <!-- ✅ Edit button logic -->
                                            <?php if (
                                                $appointment['status'] === "pending" &&
                                                $appointment['edit_count'] < 2 &&
                                                strtolower($appointment['session_type']) !== "playgroup"
                                            ): ?>
                                                <button class="uk-button uk-button-primary edit-btn action-button" data-id="<?= $appointment['appointment_id']; ?>"
                                                    data-date="<?= $appointment['date']; ?>" data-time="<?= $appointment['time']; ?>">
                                                    Reschedule (<?= 2 - $appointment['edit_count']; ?> left)
                                                </button>
                                            <?php else: ?>
                                                <?php if (
                                                    strtolower($appointment['session_type']) === "playgroup" &&
                                                    !in_array(strtolower($appointment['status']), ["completed", "cancelled", "declined"])
                                                ): ?>
                                                    <button class="uk-button uk-button-default action-button" disabled>Reschedule Not Allowed for Playgroup</button>
                                                <?php elseif ($appointment['edit_count'] >= 2 && $appointment['status'] === "pending"): ?>
                                                    <button class="uk-button uk-button-default action-button" disabled>Reschedule Limit Reached</button>
                                                <?php else: ?>
                                                    <button class="uk-button uk-button-default action-button" disabled>Reschedule Is Not Allowed</button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- ✅ Cancel button (Allowed only for "Pending" or "Waitlisted") -->
                                            <?php if (in_array($appointment['status'], ["pending", "waitlisted"])): ?>
                                                <button class="uk-button uk-button-danger cancel-btn action-button" data-id="<?= $appointment['appointment_id']; ?>">Cancel</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Register Patient -->
            <div id="register-patient" class="section" style="display: none;">
                <h1 class="uk-text-bold">Patient Registration</h1>

                <div class="uk-card uk-card-default uk-card-body">
                    <h2 class="uk-card-title uk-text-bold">Patient Information</h2>

                    <form id="patientRegistrationForm" enctype="multipart/form-data" class="uk-grid-small" uk-grid>
                        <input type="hidden" name="action" value="update_user_details">

                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" name="patient_fname" required>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" name="patient_lname" required>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Birthday</label>
                            <input class="uk-input" type="date" name="patient_birthday" id="patient_birthday" min="2008-01-01" max="2024-12-31" required>
                        </div>

                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Gender</label>
                            <select class="uk-select" name="patient_gender" required>
                                <option value="" disabled selected>Select Patient Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <!--
                        <div class="uk-width-1@s uk-width-1-2@l">
                            <label class="uk-form-label">Profile Picture</label>
                            <div class="js-upload uk-placeholder uk-text-center" id="profile-picture-placeholder">
                                <span uk-icon="icon: cloud-upload"></span>
                                <span class="uk-text-middle">Drag and drop a file or</span>
                                <div uk-form-custom>
                                    <input type="file" multiple name="profile_picture" accept=".jpg, .jpeg, .png" required id="profile-picture-input">
                                    <span class="uk-link">Browse</span>
                                    <span class="uk-text-middle" id="file-name-display">to choose a file</span>
                                </div>
                            </div>
                        </div>
                        -->

                        <div class="uk-width-1-2@s uk-width-1-2@l">
                            <label class="uk-form-label">Profile Picture</label>
                            <input class="uk-input" type="file" name="referral_file" id="profile-picture-input" required accept=".jpg,.jpeg,.png,.pdf" style="padding-top: 5px;padding-bottom: 5px;">
                        </div>        

                        <div class="uk-width-1-1 uk-margin-top">
                        <hr class=" uk-margin-top">
                            <h2 class="uk-margin-small-bottom uk-card-title uk-text-bold">Upload Doctor's Referral</h2>
                        </div>

                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Referral Type</label>
                            <select class="uk-select" name="referral_type" id="referral_type_select" required>
                                <option value="" disabled selected>Select Referral Type</option>
                                <option value="official">Official Referral</option>
                                <option value="proof_of_booking">Proof of Booking</option>
                            </select>
                        </div>

                        <div class="uk-width-1-2@s uk-width-1-2@l">
                            <label class="uk-form-label">Upload Referral File</label>
                            <input class="uk-input" type="file" name="referral_file" id="referral_file_input" required accept=".jpg,.jpeg,.png,.pdf" style="padding-top: 5px;padding-bottom: 5px;">
                        </div>


                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button class="uk-button uk-button-primary" type="button" id="registerPatientButton" style="border-radius: 15px;">Register</button>
                        </div>
                    </form>
                </div>
            </div>


            <!-- View Registered Patients -->
            <div id="view-registered-patients" class="section" style="display: none;">
                <h1 class="uk-text-bold">View Registered Patients</h1>

                <div class="uk-card uk-card-default uk-card-body">
                    <p>Choose a patient to view.</p>
                    <select class="uk-select" id="patientDropdown">
                        <option value="" disabled selected>Select a Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['patient_id']; ?>">
                                <?= htmlspecialchars($patient['first_name'] . " " . $patient['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <hr>
                    <!-- 🔹 Patient Details Form (Initially Hidden) -->
                   
                   
                    <form id="editPatientForm" class="uk-grid-small uk-grid" action="../Appointments/patient/patient_data/update_patient_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked" style="display: none;">


                        <input type="hidden" name="patient_id" id="patient_id">

                        <input type="hidden" name="existing_profile_picture" id="existing_profile_picture"> <!-- Store existing picture -->


                        <!------ LEMME COOK ------->
                        <div class="uk-flex uk-flex-middle">
                            <div class="profile-upload-container uk-width-1@s " style="padding: 25px; ">
                            
                            <img id="profile_picture_preview" src="" class="uk-border-rounded uk-margin-top" style="width: 150px; height: 150px; display: none;">
                            
                            <div class="uk-flex uk-flex-column uk-margin-left">
                            
                                <button class="uk-button uk-button-primary uk-margin-small-bottom" type="file" name="profile_picture" id="profile_picture_input" style="border-radius: 15px;">Upload Photo</button>

                            </div>

                            
                            <div class="uk-margin-large-left">
                                    <h4>Image requirements:</h4>
                                    <ul class="uk-list">
                                        <li>1. Min. 400 x 400px</li>
                                        <li>2. Max. 2MB</li>
                                        <li>3. Your child's face.</li>
                                    </ul>
                                </div>


                            </div>
                        </div>

                        <div class="uk-grid-small" uk-grid>
                        
                        
                        <div class="uk-width-1-2@s">
                                <label class="uk-form-label">First Name</label>
                                <input class="uk-input" type="text" name="first_name" id="first_name" required>
                        </div>
                            

                        <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Last Name</label>
                        <input class="uk-input" type="text" name="last_name" id="last_name" required>
                        </div>

                        <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Birthday</label>
                        <input class="uk-input" type="date" name="bday" id="bday" min="2008-01-01" max="2024-12-31" required>
                        </div>

                        <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Gender</label>
                        <select class="uk-select" name="gender" id="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        </div>

                        <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Official Referral</label>
                        <a id="official_referral_link" href="#" class="uk-button uk-button-link" target="_blank" style="display: none;">View File</a>
                        </div>

                        <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Proof of Booking</label>
                        <a id="proof_of_booking_link" href="#" class="uk-button uk-button-link" target="_blank" style="display: none;">View File</a>
                        </div>

                        
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Upload New Official Referral</label>
                            <input class="uk-input" type="file" name="official_referral" id="official_referral_input" accept=".pdf,.jpg,.jpeg,.png" disabled style="padding-top: 5px;padding-bottom: 5px;">
                        </div>

                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Upload New Proof of Booking</label>
                            <input class="uk-input" type="file" name="proof_of_booking" id="proof_of_booking_input" accept=".pdf,.jpg,.jpeg,.png" disabled style="padding-top: 5px;padding-bottom: 5px;">
                        </div>


                        <div class="uk-width-1-1 uk-text-right uk-margin-top" style="margin-bottom: 15px;">
                        
                        <button id="editPatientBtn" class="uk-button uk-button-secondary uk-margin-top" type="button" style="margin-right: 10px;border-radius: 15px;" >Edit</button>
                        
                        <button class="uk-button uk-button-primary uk-margin-top" type="submit" style="margin-right: 10px;border-radius: 15px;">Save Profile Changes</button>

                        
                            </div> 
            

                        </div>

                    </form>
                </div>
            </div>


            <!-- Book Appointment -->
            <div id="book-appointment" class="section" style="display: none;">
                <h1 class="uk-text-bold">Book Appointment</h1>
                <div class="uk-card uk-card-default uk-card-body">
                    <iframe id="appointmentFormFrame" src="../Appointments/book_appointment_form.php" style="width: 100%; height: 800px; border: none;">
                    </iframe>
                </div>
            </div>

            <!-- Account Details Card -->
            <div id="account-details" class="section" style="display: none;">
                <h1 class="uk-text-bold">Account Details</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <form action="settings.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_profile_picture">
                        <div class="uk-flex uk-flex-middle">
                            <div class="profile-upload-container">
                                <img class="uk-border-circle profile-preview" src="<?php echo $profilePicture; ?>" alt="Profile Photo">
                                <div class="uk-flex uk-flex-column uk-margin-left">
                                    <input type="file" name="profile_picture" id="profileUpload" class="uk-hidden">
                                    <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" id="uploadButton" disabled style="border-radius: 15px;">
                                        Upload Photo
                                    </button>
                                    <div class="uk-text-center">
                                        <a href="#" class="uk-link-muted" onclick="removeProfilePhoto();" id="removePhotoButton" style="pointer-events: none; color: grey;">remove</a>
                                    </div>
                                </div>
                                <div class="uk-margin-large-left">
                                    <h4>Image requirements:</h4>
                                    <ul class="uk-list">
                                        <li>1. Min. 400 x 400px</li>
                                        <li>2. Max. 2MB</li>
                                        <li>3. Your face</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title uk-text-bold">User Details</h3>
                    <form id="settingsvalidate" action="../Accounts/manageaccount/updateinfo.php" method="post" class="uk-grid-small" uk-grid>
                        <input type="hidden" name="action" id="formAction" value="update_user_details">

                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" name="firstName" id="firstName" value="<?php echo $firstName; ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="firstName"></small>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" name="lastName" id="lastName" value="<?php echo $lastName; ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="lastName"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" name="email" id="email" value="<?php echo $email; ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="email"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel" name="phoneNumber" id="mobileNumber" value="<?php echo '0' . $phoneNumber; ?>" disabled>
                            <small style="color: red;" class="error-message" data-error="phoneNumber"></small>
                        </div>

                        <small style="color: red;" class="error-message" data-error="duplicate"></small>
                        <small style="color: green;" class="error-message" id="successMessage"></small>

                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button type="button" class="uk-button uk-button-secondary" id="editButton" style="margin-right: 10px;border-radius: 15px;">Edit</button>
                            <button class="uk-button uk-button-primary" uk-toggle="target: #change-password-modal" style="margin-right: 10px;border-radius: 15px;">Change Password</button>
                            <button class="uk-button uk-button-primary" type="submit" id="saveButton" disabled style="margin-right: 10px;border-radius: 15px;">Save Changes</button>
                        </div>

                        <div id="otpSection" class="uk-width-1-1" style="display: none;">
                            <h3 class="uk-card-title uk-text-bold">Enter OTP</h3>
                            <p class="uk-text-muted">A verification code has been sent to your new email address. Please enter it below to complete the change.</p>
                            <div class="uk-margin">
                                <input class="uk-input" type="text" name="otp" id="otp" placeholder="Enter OTP">
                                <small style="color: red;" class="error-message" data-error="otp"></small>
                            </div>
                            <!-- The buttons will be dynamically added here by JavaScript -->
                        </div>

                </div>
            </div>
        </div>
        </form>

        <?php unset($_SESSION['update_errors']); ?>
        <?php unset($_SESSION['update_success']); ?>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="tnc-modal" uk-modal>
        <div class="uk-modal-dialog uk-modal-body" style="border-radius: 15px">
            <h2 class="uk-modal-title">Terms and Conditions</h2>
            <!-- TESTING FOR MANAGE CONTENT -->
            <!-- <p> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum placerat convallis placerat. Etiam dictum malesuada dui. Sed et tortor viverra, lobortis nibh eu, pulvinar purus. Vivamus vel lacus vitae magna blandit posuere sit amet ac neque. Aliquam consequat posuere lectus a varius. Mauris a lorem pulvinar, feugiat nunc in, varius nisi. Nunc nulla risus, ornare ultricies eleifend a, tincidunt vitae diam. Nulla metus dolor, egestas id condimentum quis, maximus sit amet urna. Nunc ac mollis augue. Phasellus tincidunt leo sed dolor molestie malesuada. Duis suscipit feugiat elit, eu viverra nisi porttitor ut. Mauris vitae imperdiet nibh. Pellentesque mattis ex condimentum erat mattis blandit. Aliquam ac venenatis tellus. Nunc in interdum nibh. Phasellus varius ornare purus ut volutpat.

                Donec vehicula, augue non mattis venenatis, nisl quam tincidunt velit, id mattis quam quam nec justo. Nullam efficitur tempor volutpat. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Pellentesque gravida elit non libero porta, in euismod arcu aliquet. Praesent posuere posuere dolor. Nullam mattis lectus nisl, at aliquam urna interdum vitae. Etiam non lobortis urna. Donec elementum, urna sed lobortis maximus, nulla metus elementum nibh, sit amet egestas libero tellus vel nibh. Sed sapien ex, tincidunt pellentesque magna a, condimentum lobortis ante. Quisque sed sollicitudin arcu, a congue elit. Vestibulum dictum elit vitae eleifend gravida. Nulla suscipit felis at eros dignissim convallis. Mauris ut tincidunt justo. Vivamus velit leo, ornare vitae nibh eget, congue tempus quam. Duis vehicula eu erat ac fringilla.

                Vivamus eleifend, risus sed iaculis tincidunt, urna dui hendrerit lacus, et pharetra nulla massa a urna. Interdum et malesuada fames ac ante ipsum primis in faucibus. Morbi pretium eget turpis id pulvinar. Nullam in imperdiet sem, et consectetur dui. Pellentesque mattis ex in feugiat tempus. In at nunc orci. Sed accumsan scelerisque ipsum, vel lobortis sem gravida non. Curabitur facilisis, felis molestie ornare consectetur, nibh ex congue nisi, in mattis erat leo at nisi. Vestibulum et lorem nec lorem elementum eleifend. </p>
             -->
             <div><?php echo nl2br(htmlspecialchars_decode($content['terms'] ?? '', ENT_QUOTES)); ?></div>

                <p class="uk-text-right">
                <button class="uk-button uk-button-primary uk-modal-close" type="button" style="border-radius: 15px">Close</button>
            </p>
        </div>
    </div>

    <!-- FAQs Modal -->
    <div id="faqs-modal" uk-modal>
        <div class="uk-modal-dialog uk-modal-body" style="border-radius: 15px">
            <h2 class="uk-modal-title">FAQs</h2>
            <!-- TESTING FOR MANAGE CONTENT -->
            <!-- <p> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum placerat convallis placerat. Etiam dictum malesuada dui. Sed et tortor viverra, lobortis nibh eu, pulvinar purus. Vivamus vel lacus vitae magna blandit posuere sit amet ac neque. Aliquam consequat posuere lectus a varius. Mauris a lorem pulvinar, feugiat nunc in, varius nisi. Nunc nulla risus, ornare ultricies eleifend a, tincidunt vitae diam. Nulla metus dolor, egestas id condimentum quis, maximus sit amet urna. Nunc ac mollis augue. Phasellus tincidunt leo sed dolor molestie malesuada. Duis suscipit feugiat elit, eu viverra nisi porttitor ut. Mauris vitae imperdiet nibh. Pellentesque mattis ex condimentum erat mattis blandit. Aliquam ac venenatis tellus. Nunc in interdum nibh. Phasellus varius ornare purus ut volutpat.

                Donec vehicula, augue non mattis venenatis, nisl quam tincidunt velit, id mattis quam quam nec justo. Nullam efficitur tempor volutpat. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Pellentesque gravida elit non libero porta, in euismod arcu aliquet. Praesent posuere posuere dolor. Nullam mattis lectus nisl, at aliquam urna interdum vitae. Etiam non lobortis urna. Donec elementum, urna sed lobortis maximus, nulla metus elementum nibh, sit amet egestas libero tellus vel nibh. Sed sapien ex, tincidunt pellentesque magna a, condimentum lobortis ante. Quisque sed sollicitudin arcu, a congue elit. Vestibulum dictum elit vitae eleifend gravida. Nulla suscipit felis at eros dignissim convallis. Mauris ut tincidunt justo. Vivamus velit leo, ornare vitae nibh eget, congue tempus quam. Duis vehicula eu erat ac fringilla.

                Vivamus eleifend, risus sed iaculis tincidunt, urna dui hendrerit lacus, et pharetra nulla massa a urna. Interdum et malesuada fames ac ante ipsum primis in faucibus. Morbi pretium eget turpis id pulvinar. Nullam in imperdiet sem, et consectetur dui. Pellentesque mattis ex in feugiat tempus. In at nunc orci. Sed accumsan scelerisque ipsum, vel lobortis sem gravida non. Curabitur facilisis, felis molestie ornare consectetur, nibh ex congue nisi, in mattis erat leo at nisi. Vestibulum et lorem nec lorem elementum eleifend. </p>
             -->
             <div><?php echo nl2br(htmlspecialchars_decode($content['faqs'] ?? '', ENT_QUOTES)); ?></div>

                <p class="uk-text-right">
                <button class="uk-button uk-button-primary uk-modal-close" type="button" style="border-radius: 15px">Close</button>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
        
        document.addEventListener("DOMContentLoaded", function () {
        if (window.location.hash === "#book-appointment") {
            document.getElementById("book-appointment").style.display = "block";
            let appointmentsSection = document.getElementById("appointments"); // Adjust ID if necessary
            if (appointmentsSection) {
                appointmentsSection.style.display = "none"; // Hide Appointments section
            }
        }
    });
 
    //sorting n search n filter past upcoming
    document.addEventListener("DOMContentLoaded", function () {
        const table = document.querySelector("table.uk-table");
        const headers = table.querySelectorAll("th[data-sort]");
        const tbody = table.querySelector("tbody");

        headers.forEach(header => {
            header.style.cursor = "pointer";
            header.addEventListener("click", () => {
                const type = header.getAttribute("data-sort");
                const rows = Array.from(tbody.querySelectorAll("tr"));
                const colIndex = Array.from(header.parentNode.children).indexOf(header);
                const ascending = header.classList.toggle("asc");

                rows.sort((a, b) => {
                    let valA = a.children[colIndex].textContent.trim();
                    let valB = b.children[colIndex].textContent.trim();

                    if (type === "date") {
                        valA = new Date(valA);
                        valB = new Date(valB);
                    } else {
                        valA = valA.toLowerCase();
                        valB = valB.toLowerCase();
                    }

                    if (valA < valB) return ascending ? -1 : 1;
                    if (valA > valB) return ascending ? 1 : -1;
                    return 0;
                });

                // Clear and reinsert sorted rows
                tbody.innerHTML = "";
                rows.forEach(row => tbody.appendChild(row));
            });
        });

        const searchInput = document.getElementById("appointmentSearch");
        const rows = document.querySelectorAll("table.uk-table tbody tr");

        searchInput.addEventListener("keyup", function () {
            const keyword = this.value.toLowerCase();

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(keyword) ? "" : "none";
            });
        });



    });

            //upcoming past
            document.querySelectorAll(".filter-btn").forEach(button => {
        button.addEventListener("click", function () {
            const filter = this.dataset.filter;
            const rows = document.querySelectorAll("table.uk-table tbody tr");
            const today = new Date().toISOString().split("T")[0];

            rows.forEach(row => {
                const rowDate = row.dataset.date;
                if (filter === "all") {
                    row.style.display = "";
                } else if (filter === "upcoming") {
                    row.style.display = rowDate >= today ? "" : "none";
                } else if (filter === "past") {
                    row.style.display = rowDate < today ? "" : "none";
                }
            });
        });
    });

    document.querySelector(".filter-btn[data-filter='all']").click();




        document.addEventListener("DOMContentLoaded", function() {
            const editButton = document.getElementById("editButton");
            const saveButton = document.getElementById("saveButton");
            const form = document.getElementById("settingsvalidate");
            const inputs = document.querySelectorAll("#settingsvalidate input:not([type=hidden])");
            const otpSection = document.getElementById("otpSection");
            const otpInput = document.getElementById("otp");
            const successMessage = document.getElementById("successMessage");
            const profileUploadInput = document.getElementById("profileUpload");
            const uploadButton = document.getElementById("uploadButton");
            const emailInput = document.getElementById("email"); // Select the email input
            const removePhotoButton = document.getElementById("removePhotoButton");

            // Create all three buttons with the exact styling from Image 2
            const resendOtpButton = document.createElement("button");
            resendOtpButton.id = "resendOtpButton";
            resendOtpButton.textContent = "RESEND OTP";
            resendOtpButton.className = "uk-button";
            resendOtpButton.style.backgroundColor = "#1e88e5"; // Bright blue
            resendOtpButton.style.color = "white";
            resendOtpButton.style.fontWeight = "bold";
            resendOtpButton.style.padding = "8px 20px";
            resendOtpButton.style.margin = "0 10px 0 0";
            resendOtpButton.style.border = "none";
            resendOtpButton.style.borderRadius = "4px";
            resendOtpButton.style.textTransform = "uppercase";
            resendOtpButton.style.transition = ".1s ease-in-out";
            resendOtpButton.style.transitionProperty = "color, background-color, border-color";

            const editEmailButton = document.createElement("button");
            editEmailButton.id = "editEmailButton";
            editEmailButton.textContent = "EDIT EMAIL";
            editEmailButton.className = "uk-button";
            editEmailButton.style.backgroundColor = "#212121"; // Dark gray/black
            editEmailButton.style.color = "white";
            editEmailButton.style.fontWeight = "bold";
            editEmailButton.style.padding = "8px 20px";
            editEmailButton.style.margin = "0 10px 0 0";
            editEmailButton.style.border = "none";
            editEmailButton.style.borderRadius = "4px";

            const cancelVerificationButton = document.createElement("button");
            cancelVerificationButton.id = "cancelVerificationButton";
            cancelVerificationButton.textContent = "CANCEL VERIFICATION";
            cancelVerificationButton.className = "uk-button";
            cancelVerificationButton.style.backgroundColor = "#e91e63"; // Pink
            cancelVerificationButton.style.color = "white";
            cancelVerificationButton.style.fontWeight = "bold";
            cancelVerificationButton.style.padding = "8px 20px";
            cancelVerificationButton.style.margin = "0";
            cancelVerificationButton.style.border = "none";
            cancelVerificationButton.style.borderRadius = "4px";

            // Create a container for the buttons
            const buttonContainer = document.createElement("div");
            buttonContainer.className = "uk-margin-medium-top";
            buttonContainer.style.display = "flex";
            buttonContainer.style.justifyContent = "flex-start";
            buttonContainer.style.marginTop = "20px";

            // Add buttons to container in the correct order
            buttonContainer.appendChild(resendOtpButton);
            buttonContainer.appendChild(editEmailButton);
            buttonContainer.appendChild(cancelVerificationButton);

            // Insert these buttons after the OTP input
            if (otpSection) {
                // Append the button container to the OTP section (after all existing elements)
                otpSection.appendChild(buttonContainer);
            }

            // Store initial values
            let originalValues = {};
            inputs.forEach(input => originalValues[input.id] = input.value);
            // Original email value to restore if verification is canceled
            let originalEmail = emailInput ? emailInput.value : '';

            // Modify Edit Button Click Event
            if (editButton) {
                editButton.addEventListener("click", function() {
                    if (editButton.textContent === "Edit") {
                        inputs.forEach(input => input.disabled = false);
                        saveButton.disabled = false;
                        editButton.textContent = "Cancel";
                        uploadButton.disabled = false;
                        removePhotoButton.style.pointerEvents = "auto";
                        removePhotoButton.style.color = "";

                        // Enable Change Password button
                        if (changePasswordButton) {
                            changePasswordButton.disabled = false;
                        }

                        // Enable password form inputs
                        if (passwordForm) {
                            const passwordInputs = passwordForm.querySelectorAll("input");
                            passwordInputs.forEach(input => input.disabled = false);
                        }
                    } else {
                        inputs.forEach(input => {
                            input.value = originalValues[input.id];
                            input.disabled = true;
                        });
                        saveButton.disabled = true;
                        editButton.textContent = "Edit";
                        otpSection.style.display = "none";
                        uploadButton.disabled = true;
                        removePhotoButton.style.pointerEvents = "none";
                        removePhotoButton.style.color = "grey";

                        saveButton.textContent = "Save Changes";
                        saveButton.dataset.step = "";

                        // Disable Change Password button
                        if (changePasswordButton) {
                            changePasswordButton.disabled = true;
                        }

                        // Disable password form inputs
                        if (passwordForm) {
                            const passwordInputs = passwordForm.querySelectorAll("input");
                            passwordInputs.forEach(input => input.disabled = true);
                        }
                    }
                });
            }

            // Save Button Click Event
            if (saveButton) {
                saveButton.addEventListener("click", function(event) {
                    if (saveButton.dataset.step === "verify") {
                        event.preventDefault();
                        verifyOTP();
                    } else {
                        event.preventDefault();
                        saveChanges();
                    }
                });
            }

            // Edit Email Button Click Event - Allows user to go back and edit their email
            if (editEmailButton) {
                editEmailButton.addEventListener("click", function(event) {
                    event.preventDefault();

                    // Hide OTP section
                    otpSection.style.display = "none";

                    // Enable email input
                    emailInput.disabled = false;

                    // Reset save button state
                    saveButton.textContent = "Save";
                    saveButton.dataset.step = "";
                });
            }

            // Add event listener for Resend OTP button
            if (resendOtpButton) {
                resendOtpButton.addEventListener("click", function(event) {
                    event.preventDefault();
                    resendOtpButton.disabled = true;

                    // Make the button transparent
                    resendOtpButton.style.opacity = "0.4"; // Higher transparency (lower opacity)

                    // Add a countdown timer to prevent spam
                    let timeLeft = 60;
                    const originalText = resendOtpButton.textContent;
                    resendOtpButton.textContent = `WAIT (${timeLeft}s)`;

                    const countdownTimer = setInterval(() => {
                        timeLeft--;
                        resendOtpButton.textContent = `WAIT (${timeLeft}s)`;

                        if (timeLeft <= 0) {
                            clearInterval(countdownTimer);
                            resendOtpButton.textContent = originalText;
                            resendOtpButton.disabled = false;
                            resendOtpButton.style.opacity = "1"; // Restore full opacity
                        }
                    }, 1000);

                    // Send request to resend OTP
                    const email = document.getElementById("email").value.trim();

                    let formData = new URLSearchParams({
                        action: "resend_otp",
                        email: email
                    });

                    fetch("../Accounts/manageaccount/updateinfo.php", {
                            method: "POST",
                            body: formData,
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'OTP Resent',
                                    text: 'A new verification code has been sent to your email.',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                });
                                // Keep the button disabled and transparent during the countdown
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.error || 'Failed to resend OTP. Please try again.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });

                                // Reset the button immediately on error
                                clearInterval(countdownTimer);
                                resendOtpButton.textContent = originalText;
                                resendOtpButton.disabled = false;
                                resendOtpButton.style.opacity = "1"; // Restore full opacity
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred. Please try again.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });

                            // Reset the button immediately on error
                            clearInterval(countdownTimer);
                            resendOtpButton.textContent = originalText;
                            resendOtpButton.disabled = false;
                            resendOtpButton.style.opacity = "1"; // Restore full opacity
                        });
                });
            }

            // Cancel Verification Button Click Event - Cancels email verification and restores original email
            if (cancelVerificationButton) {
                cancelVerificationButton.addEventListener("click", function(event) {
                    event.preventDefault();

                    // Confirm cancellation with SweetAlert
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "Your email will not be changed.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, cancel verification'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Restore original email
                            emailInput.value = originalEmail;

                            // Hide OTP section
                            otpSection.style.display = "none";

                            // Reset save button state
                            saveButton.textContent = "Save";
                            saveButton.dataset.step = "";

                            // Disable inputs if we're not in edit mode
                            if (editButton.textContent === "Edit") {
                                inputs.forEach(input => input.disabled = true);
                            }

                            Swal.fire(
                                'Cancelled',
                                'Email verification has been cancelled.',
                                'info'
                            );
                        }
                    });
                });
            }

            // Function to Save Changes
            function saveChanges() {
                let firstName = document.getElementById("firstName").value.trim();
                let lastName = document.getElementById("lastName").value.trim();
                let email = emailInput.value.trim(); // Use the selected email input
                let phoneNumber = document.getElementById("mobileNumber").value.trim();

                document.querySelectorAll(".error-message").forEach(error => error.textContent = "");

                if (!firstName || !lastName || !email || !phoneNumber) {
                    Swal.fire({
                        title: 'Error!',
                        text: 'All fields are required.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Store the original email in case user cancels verification later
                originalEmail = emailInput.value;

                let formData = new URLSearchParams({
                    action: "update_user_details",
                    firstName: firstName,
                    lastName: lastName,
                    email: email,
                    phoneNumber: phoneNumber
                });

                fetch("../Accounts/manageaccount/updateinfo.php", {
                        method: "POST",
                        body: formData,
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.errors) {
                            Object.entries(data.errors).forEach(([key, message]) => {
                                let errorElement = document.querySelector(`[data-error="${key}"]`);
                                if (errorElement) errorElement.textContent = message;
                            });

                            Swal.fire({
                                title: 'Validation Error',
                                text: 'Please check the form for errors.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        } else if (data.otp_required) {
                            Swal.fire({
                                title: 'OTP Sent',
                                text: 'OTP sent to your new email. Please enter the OTP to verify.',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });

                            otpSection.style.display = "block";
                            saveButton.textContent = "Verify OTP";
                            saveButton.dataset.step = "verify";
                        } else if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: data.success,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: 'Something went wrong.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            }
            // Function to Verify OTP
            function verifyOTP() {
                let otp = otpInput.value.trim();
                if (!otp) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Please enter OTP.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Get the user details to update along with the OTP
                let firstName = document.getElementById("firstName").value.trim();
                let lastName = document.getElementById("lastName").value.trim();
                let phoneNumber = document.getElementById("mobileNumber").value.trim();

                // Create form data with all necessary information
                let formData = new URLSearchParams({
                    action: "verify_otp",
                    otp: otp,
                    firstName: firstName,
                    lastName: lastName,
                    phoneNumber: phoneNumber
                });

                fetch("../Accounts/manageaccount/updateinfo.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Email updated successfully!',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else if (data.error) {
                            Swal.fire({
                                title: 'Error',
                                text: data.error,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                title: 'Invalid OTP',
                                text: 'Invalid OTP. Please try again.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred during OTP verification.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            }



            // Profile Picture Upload Handling
            if (uploadButton && profileUploadInput) {
                uploadButton.addEventListener("click", function() {
                    profileUploadInput.click();
                });

                profileUploadInput.addEventListener("change", function() {
                    let formData = new FormData();
                    formData.append("action", "upload_profile_picture");
                    formData.append("profile_picture", profileUploadInput.files[0]);

                    fetch("../Accounts/manageaccount/updateinfo.php", {
                            method: "POST",
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.querySelector(".profile-preview").src = data.imagePath;
                            } else {
                                alert("Error: " + data.error);
                            }
                        })
                        .catch(error => console.error("Error:", error));
                });
            }

            // Form Submission Event (For Validation)
            if (form) {
                form.addEventListener("submit", function(event) {
                    event.preventDefault();

                    let formData = new FormData(this);

                    fetch("../Accounts/manageaccount/updateinfo.php", {
                            method: "POST",
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            document.querySelectorAll(".error-message").forEach(el => el.textContent = "");

                            if (data.errors) {
                                Object.keys(data.errors).forEach(key => {
                                    let errorElement = document.querySelector(`small[data-error="${key}"]`);
                                    if (errorElement) {
                                        errorElement.textContent = data.errors[key];
                                    }
                                });
                            } else if (data.success) {
                                alert(data.success);
                                location.reload();
                            }
                        })
                        .catch(error => console.error("Error:", error));
                });
            }
            // Change Password Button
            const changePasswordButton = document.querySelector('[uk-toggle="target: #change-password-modal"]');

            if (changePasswordButton) {
                // Initially disable the Change Password button
                changePasswordButton.disabled = true;
            }

            // Change Password
            const passwordForm = document.getElementById("change-password-form");

            // Prevent default form submission and save actions
            changePasswordButton.addEventListener('click', function(event) {
                // Prevent any default behavior that might submit a form or trigger save actions
                event.preventDefault();

                // Create a function to show the password change form
                function showPasswordChangeForm(errorMessage = null) {
                    Swal.fire({
                        title: 'Change Password',
                        html: `
                <form id="swal-password-form" class="swal-form">
                    <div class="swal-input-group">
                        <label for="swal-current-password">Current Password</label>
                        <input type="password" id="swal-current-password" class="swal2-input" placeholder="Current password">
                    </div>
                    
                    <div class="swal-input-group">
                        <label for="swal-new-password">New Password</label>
                        <input type="password" id="swal-new-password" class="swal2-input" placeholder="New password">
                    </div>
                    
                    <div class="swal-input-group">
                        <label for="swal-confirm-password">Confirm New Password</label>
                        <input type="password" id="swal-confirm-password" class="swal2-input" placeholder="Confirm new password">
                    </div>
                </form>
            `,
                        showCancelButton: true,
                        confirmButtonText: 'Change Password',
                        cancelButtonText: 'Cancel',
                        focusConfirm: false,
                        showLoaderOnConfirm: true,
                        didOpen: () => {
                            // If there's an error message, show it as a validation message
                            if (errorMessage) {
                                Swal.showValidationMessage(errorMessage);
                            }
                        },
                        preConfirm: () => {
                            const currentPassword = document.getElementById('swal-current-password').value.trim();
                            const newPassword = document.getElementById('swal-new-password').value.trim();
                            const confirmPassword = document.getElementById('swal-confirm-password').value.trim();

                            // Frontend validation
                            if (!currentPassword || !newPassword || !confirmPassword) {
                                Swal.showValidationMessage('All fields are required');
                                return false;
                            }

                            if (newPassword !== confirmPassword) {
                                Swal.showValidationMessage('New passwords do not match');
                                return false;
                            }

                            if (newPassword.length < 8) {
                                Swal.showValidationMessage('Password must be at least 8 characters');
                                return false;
                            }

                            if (!/[A-Z]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one uppercase letter');
                                return false;
                            }

                            if (!/[a-z]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one lowercase letter');
                                return false;
                            }

                            if (!/[0-9]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one number');
                                return false;
                            }

                            if (!/[^A-Za-z0-9]/.test(newPassword)) {
                                Swal.showValidationMessage('Password must contain at least one special character');
                                return false;
                            }

                            // Return values for the next step
                            return {
                                currentPassword: currentPassword,
                                newPassword: newPassword,
                                confirmPassword: confirmPassword
                            };
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        // If user clicked "Change Password" and validation passed
                        if (result.isConfirmed) {
                            const {
                                currentPassword,
                                newPassword,
                                confirmPassword
                            } = result.value;

                            // Send password change request
                            fetch("../Accounts/manageaccount/updateinfo.php", {
                                    method: "POST",
                                    headers: {
                                        "Content-Type": "application/x-www-form-urlencoded"
                                    },
                                    body: new URLSearchParams({
                                        action: "change_password",
                                        current_password: currentPassword,
                                        new_password: newPassword,
                                        confirm_password: confirmPassword
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    console.log("Response received:", data);
                                    if (data.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Success!',
                                            text: data.success,
                                            timer: 2000,
                                            timerProgressBar: true
                                        });
                                    } else if (data.error) {
                                        // Reopen the form with the error message but no values preserved
                                        showPasswordChangeForm(data.error);
                                    }
                                })
                                .catch(error => {
                                    console.error("Error:", error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'An unexpected error occurred. Please try again.'
                                    });
                                });
                        } else {
                            // User clicked cancel or outside the modal, reset the page (reload)
                            location.reload();
                        }
                    });
                }

                // Show the initial password change form
                showPasswordChangeForm();
            });

            // Add some CSS to improve the SweetAlert form
            const style = document.createElement('style');
            style.textContent = `
.swal-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin: 15px auto;
}
.swal-input-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
}
.swal-input-group label {
    margin-bottom: 5px;
    font-weight: 500;
    text-align: left;
}
.swal2-input {
    width: 100%;
    margin: 0;
}
.swal-validation-error {
    color: #f27474;
    margin-top: 10px;
    text-align: left;
    font-size: 14px;
}
`;
            document.head.appendChild(style);
            // Initialize OTP section to be hidden
            otpSection.style.display = "none";
        });




        function removeProfilePhoto() {
            if (confirm("Are you sure you want to remove your profile picture?")) {
                fetch("../Accounts/manageaccount/updateinfo.php", {
                        method: "POST",
                        body: JSON.stringify({
                            action: "remove_profile_picture"
                        }),
                        headers: {
                            "Content-Type": "application/json"
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelector('.profile-preview').src = '../CSS/default.jpg'; // Set to default image
                        } else {
                            alert("Error: " + data.error);
                        }
                    })
                    .catch(error => console.error("Error:", error));
            }
        }


        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar-nav').classList.toggle('uk-open');
        });

        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById(sectionId).style.display = 'block';
        }

        function previewProfilePhoto(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const preview = document.querySelector('.profile-preview');
                preview.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }


        // APPOINTMENTS SCRIPT

        document.addEventListener("DOMContentLoaded", function() {
            // ✅ Cancel Appointment
            document.querySelectorAll(".cancel-btn").forEach(button => {
                button.addEventListener("click", function() {
                    let appointmentId = this.getAttribute("data-id");

                    Swal.fire({
                        title: "Cancel Appointment?",
                        text: "Please provide a reason for cancellation:",
                        icon: "warning",
                        input: "text",
                        inputPlaceholder: "Enter cancellation reason",
                        showCancelButton: true,
                        confirmButtonText: "Yes, Cancel",
                        cancelButtonText: "No, Keep Appointment",
                        preConfirm: (reason) => {
                            if (!reason) {
                                Swal.showValidationMessage("A cancellation reason is required.");
                            }
                            return reason;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch("../Appointments/app_manage/client_edit_appointment.php", {
                                    method: "POST",
                                    headers: {
                                        "Content-Type": "application/json"
                                    },
                                    body: JSON.stringify({
                                        appointment_id: appointmentId,
                                        action: "cancel",
                                        validation_notes: result.value
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === "success") {
                                        Swal.fire({
                                            title: data.title,
                                            text: data.message,
                                            icon: "success",
                                            confirmButtonText: "OK"
                                        }).then(() => {
                                            location.reload(); // Reload after user sees the message
                                        });
                                    } else {
                                        Swal.fire({
                                            title: data.title,
                                            text: data.message,
                                            icon: "error"
                                        });
                                    }
                                })
                                .catch(error => {
                                    Swal.fire({
                                        title: "Error",
                                        text: "Something went wrong. Please try again.",
                                        icon: "error"
                                    });
                                });
                        }
                    });
                });
            });


            function generateTimeOptions(start, end, sessionType) {
                const [startHour, startMin] = start.split(":").map(Number);
                const [endHour, endMin] = end.split(":").map(Number);
                const interval = sessionDurations[sessionType] || 60; // Default 60 mins if not found
                const timeDropdown = document.getElementById("appointmentTime");

                timeDropdown.innerHTML = '<option value="">Select Time</option>'; // Reset

                let current = new Date();
                current.setHours(startHour, startMin, 0, 0);
                const endTime = new Date();
                endTime.setHours(endHour, endMin, 0, 0);

                while (current < endTime) {
                    const hh = current.getHours().toString().padStart(2, '0');
                    const mm = current.getMinutes().toString().padStart(2, '0');
                    const option = document.createElement("option");
                    option.value = `${hh}:${mm}`;
                    option.textContent = `${hh}:${mm}`;
                    timeDropdown.appendChild(option);
                    current.setMinutes(current.getMinutes() + interval);
                }
            }
            // ✅ Edit Appointment (Reschedule)
            document.querySelectorAll(".edit-btn").forEach(button => {
                button.addEventListener("click", function() {
                    let appointmentId = this.getAttribute("data-id");
                    let currentStatus = this.getAttribute("data-status");

                    Swal.fire({
                        title: "Edit Appointment",
                        html: `
                        <label>New Date:</label> <input type="text" id="appointmentDate" class="swal2-input">
                        <label>New Time:</label> <select id="appointmentTime" class="swal2-select"></select>
                    `,
                        didOpen: () => {
                            flatpickr("#appointmentDate", {
                                minDate: new Date().fp_incr(timetableSettings.minDaysAdvance),
                                maxDate: new Date().fp_incr(timetableSettings.maxDaysAdvance),
                                disable: timetableSettings.blockedDates.map(date => new Date(date))
                            });

                            let sessionType = document.querySelector(".edit-btn").getAttribute("data-session-type");
                            generateTimeOptions(timetableSettings.businessHoursStart, timetableSettings.businessHoursEnd, sessionType);
                        },
                        showCancelButton: true,
                        confirmButtonText: "Save Changes",
                        preConfirm: () => {
                            return {
                                newDate: document.getElementById("appointmentDate").value,
                                newTime: document.getElementById("appointmentTime").value
                            };
                        }
                    }).then((result) => {
                        fetch("../Appointments/app_manage/client_edit_appointment.php", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json"
                                },
                                body: JSON.stringify({
                                    appointment_id: appointmentId,
                                    action: "edit",
                                    new_date: result.value.newDate,
                                    new_time: result.value.newTime
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                Swal.fire(data.title, data.message, data.status === "success" ? "success" : "error")
                                    .then(() => location.reload());
                            });
                    });
                });
            });




            // Book Appointment Form Submission Handling
            let appointmentFormFrame = document.getElementById("appointmentFormFrame");

            appointmentFormFrame.onload = function() {
                let appointmentForm = appointmentFormFrame.contentDocument.getElementById("appointmentForm");

                if (appointmentForm) {
                    appointmentForm.addEventListener("submit", function(e) {
                        e.preventDefault();

                        let formData = new FormData(this);

                        fetch("../Appointments/app_process/book_appointment_process.php", {
                                method: "POST",
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.swal) {
                                    Swal.fire({
                                        title: data.swal.title,
                                        text: data.swal.text,
                                        icon: data.swal.icon,
                                    }).then(() => {
                                        if (data.reload) {
                                            window.location.reload(true); // Hard reload the page
                                        }
                                    });
                                }
                            })
                            .catch(error => console.error("Error:", error));
                    });
                }
            };


            // REGISTER PATIENT JS

            document.getElementById("profile-picture-input").addEventListener("change", function() {
                if (this.files && this.files[0]) {
                    document.getElementById("profile-picture-placeholder").classList.remove("uk-placeholder");
                    document.getElementById("file-name-display").textContent = this.files[0].name;
                } else {
                    document.getElementById("profile-picture-placeholder").classList.add("uk-placeholder");
                    document.getElementById("file-name-display").textContent = "to choose a file";
                }
            });

            document.getElementById("registerPatientButton").addEventListener("click", function() {
                let form = document.getElementById("patientRegistrationForm");
                let formData = new FormData(form);

                let firstName = formData.get("patient_fname");
                let lastName = formData.get("patient_lname");
                let birthday = formData.get("patient_birthday");
                let gender = formData.get("patient_gender");
                let file = formData.get("profile_picture") ? formData.get("profile_picture").name : "No file selected";


                // Validation for first and last names
                const nameRegex = /^[A-Za-z ]{2,30}$/;
                if (!nameRegex.test(firstName)) {
                    Swal.fire("Validation Error", "First name must be between 2 and 30 characters and contain only letters and spaces.", "error");
                    return; // Stop the registration process
                }
                if (!nameRegex.test(lastName)) {
                    Swal.fire("Validation Error", "Last name must be between 2 and 30 characters and contain only letters and spaces.", "error");
                    return; // Stop the registration process
                }


                Swal.fire({
                    title: "Confirm Registration",
                    html: `
                    <strong>First Name:</strong> ${firstName} <br/>
                    <strong>Last Name:</strong> ${lastName} <br/>
                    <strong>Birthday:</strong> ${birthday} <br/>
                    <strong>Gender:</strong> ${gender} <br/>
                    <strong>Profile Picture:</strong> ${file} <br/>
                `,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Submit",
                    cancelButtonText: "Cancel",
                    confirmButtonColor: "#28a745",
                    cancelButtonColor: "#dc3545"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Proceed with form submission
                        fetch("../Appointments/patient/patient_manage/register_patient_form.php", {
                                method: "POST",
                                body: formData
                            })
                            .then(response => response.json()) // Parse JSON
                            .then(data => {
                                if (data.status === "success") {
                                    Swal.fire("Success!", data.message, "success").then(() => {
                                        form.reset();
                                        document.getElementById("file-name-display").textContent = "";

                                        // Hard reload the page
                                        location.reload(true);

                                        // Optional: Scroll to the registered patients section after reload
                                        setTimeout(() => {
                                            window.location.href = "#view-registered-patients"; // Adjust the ID accordingly
                                        }, 500);
                                    });
                                } else if (data.status === "duplicate") {
                                    Swal.fire("Duplicate!", data.message, "warning");
                                } else {
                                    Swal.fire("Error!", data.message, "error");
                                }
                            })
                            .catch(error => {
                                console.error("Error:", error);
                                Swal.fire("Error!", "An unexpected error occurred.", "error");
                            });
                    }
                });
            });

            // VIEW AND EDIT PATIENT JS

        let patientDropdown = document.getElementById("patientDropdown");
        let editForm = document.getElementById("editPatientForm");
        let patientIDInput = document.getElementById("patient_id");
        let firstNameInput = document.getElementById("first_name");
        let lastNameInput = document.getElementById("last_name");
        let birthdayInput = document.getElementById("bday");
        let genderInput = document.getElementById("gender");
        let profilePicPreview = document.getElementById("profile_picture_preview");
        let profilePicInput = document.getElementById("profile_picture_input");
        let existingProfilePicInput = document.getElementById("existing_profile_picture");
        let officialReferralInput = document.getElementById("official_referral_input");
        let proofReferralInput = document.getElementById("proof_of_booking_input");
        let editPatientBtn = document.getElementById("editPatientBtn");
        let saveProfileChangesBtn = document.querySelector("#editPatientForm button[type='submit']");

        
        // Referral Section
        // let uploadReferralSection = document.getElementById("uploadReferralForm");
        // uploadReferralSection.style.display = "none";

        // Initially disable form fields
        function toggleFormInputs(disable) {
            firstNameInput.disabled = disable;
            lastNameInput.disabled = disable;
            birthdayInput.disabled  = disable;
            genderInput.disabled = disable;
            profilePicInput.disabled = disable;

            officialReferralInput.disabled = disable;
    proofReferralInput.disabled = disable;

            // Instead of hiding the save button entirely, we’ll disable it to keep layout intact
            saveProfileChangesBtn.disabled = disable;
            saveProfileChangesBtn.style.opacity = disable ? "0.5" : "1";
            saveProfileChangesBtn.style.pointerEvents = disable ? "none" : "auto";
        }
        
        // Load patient details when selecting from dropdown
        patientDropdown.addEventListener("change", function () {
            officialReferralInput.value = "";
proofReferralInput.value = "";

            let patientID = this.value;
            if (!patientID) {
                editForm.style.display = "none";
                uploadReferralSection.style.display = "none"; // Hide referral section when no patient is selected
                return;
            }

            fetch("../Appointments/patient/patient_data/fetch_patient_details.php?patient_id=" + patientID)
            .then(response => response.json())
            .then(data => {

                if (data.status === "success") {
                    const patient = data.patient;

                    patientIDInput.value = patient.patient_id;
                    firstNameInput.value = patient.first_name;
                    lastNameInput.value = patient.last_name;
                    genderInput.value = patient.gender;
                    existingProfilePicInput.value = patient.profile_picture;

                    // ✅ Reset birthday properly
                    if (patient.bday && patient.bday !== "0000-00-00") {
                        birthdayInput.value = patient.bday;
                    } else {
                        birthdayInput.value = ""; // Leave blank
                    }

                    if (patient.profile_picture) {
                        profilePicPreview.src = "../uploads/profile_pictures/" + patient.profile_picture;
                        profilePicPreview.style.display = "block";
                    } else {
                        profilePicPreview.style.display = "none";
                    }

                   // ✅ Load latest referral file links
                    const latestReferrals = data.latest_referrals;

                    const officialLink = document.getElementById("official_referral_link");
                    if (latestReferrals && latestReferrals.official && latestReferrals.official.official_referral_file) {
                        officialLink.href = "../../uploads/doctors_referrals/" + latestReferrals.official.official_referral_file;
                        officialLink.style.display = "inline-block";
                    } else {
                        officialLink.href = "#";
                        officialLink.style.display = "none";
                    }

                    const proofLink = document.getElementById("proof_of_booking_link");
                    if (latestReferrals && latestReferrals.proof_of_booking && latestReferrals.proof_of_booking.proof_of_booking_referral_file) {
                        proofLink.href = "../../uploads/doctors_referrals/" + latestReferrals.proof_of_booking.proof_of_booking_referral_file;
                        proofLink.style.display = "inline-block";
                    } else {
                        proofLink.href = "#";
                        proofLink.style.display = "none";
                    }


                    // Show form and section
                    toggleFormInputs(true);
                    editForm.style.display = "block";
                    uploadReferralSection.style.display = "block";

                } else {
                    editForm.style.display = "none";
                    uploadReferralSection.style.display = "none";
                    Swal.fire("Error", "Patient details could not be loaded.", "error");
                }
            })
            .catch(error => console.error("Error fetching patient details:", error));
        });

            // Ensure the birthday value is preserved when submitting the form
            document.getElementById("editPatientForm").addEventListener("submit", function(event) {
                console.log("Birthday value before submission:", birthdayInput.value);

                // If birthday is empty but should be required, you can prevent submission
                if (birthdayInput.required && birthdayInput.value.trim() === "") {
                    event.preventDefault();
                    Swal.fire("Error", "Birthday field is required.", "error");
                    return false;
                }
            });

        // Toggle edit mode when clicking "Edit" button
        editPatientBtn.addEventListener("click", function () {
            let isDisabled = firstNameInput.disabled;
            toggleFormInputs(!isDisabled);
            
            // Change button text to "Save" if enabling edit mode
            editPatientBtn.textContent = isDisabled ? "Cancel" : "Edit";
            
            // Show/hide "Save" button
            saveProfileChangesBtn.style.display = isDisabled ? "inline-block" : "none";

                // If canceling, reload patient details to reset changes
                if (!isDisabled) {
                    patientDropdown.dispatchEvent(new Event("change"));
                }
            });


            // Show preview when selecting a new profile picture
            profilePicInput.addEventListener("change", function() {
                let file = this.files[0];
                if (file) {
                    let reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicPreview.src = e.target.result;
                        profilePicPreview.style.display = "block";
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Upload Referral Logic
            document.getElementById("uploadReferralBtn").addEventListener("click", function() {
                let patientID = document.getElementById("patientDropdown").value;
                let referralType = document.getElementById("referral_type_select").value;
                let referralFile = document.getElementById("referral_file_input").files[0];

                if (!patientID || !referralType || !referralFile) {
                    Swal.fire("Error", "Please select a patient, referral type, and upload a file.", "error");
                    return;
                }

                let formData = new FormData();
                formData.append("patient_id", patientID);
                formData.append("referral_type", referralType);
                formData.append("referral_file", referralFile);

            fetch("../Appointments/patient/patient_data/upload_referral.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    Swal.fire("Success!", data.message, "success").then(() => {
                        location.reload(); // Reload page to update referral display
                    });
                } else {
                    Swal.fire("Error!", data.message, "error");
                }
            })
            .catch(error => console.error("Error:", error));
        });
    });


</script>
</body>

</html>
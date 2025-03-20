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

// BOOK APPOINTMENT FORM PHP from book_appointment_form


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
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Services</a></li>
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

                <li class="uk-parent">
                <a href="#appointments" onclick="showSection('appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Appointments</a>
                <!-- Reference code: client_view_appointments.php -->

                        <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                            
                        <li style="padding:0px 0px 15px 0px"><a href="#book-appointment" onclick="showSection('book-appointment')"><span class="uk-margin-small-right" uk-icon="user"></span> Book Appointment</a></li>
                        <!-- Reference code: book_appointment_form.php -->

                        </ul>
                    </li>
                <hr>
                    
                <li class="uk-parent">
                <a href="#register-patient" onclick="showSection('register-patient')"><span class="uk-margin-small-right" uk-icon="user"></span> Register a Patient</a>
                <!-- Reference code: register_patient_form.php -->

                        <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                            
                        <li style="padding:0px 0px 15px 0px"><a href="#view-registered-patients" onclick="showSection('view-registered-patients')"><span class="uk-margin-small-right" uk-icon="user"></span> View Registered Patients</a></li>
                        <!-- Reference code: edit_patient_form.php -->

                        </ul>
                    </li>                    

                    <hr>
                    

                    <!-- <li><a href="../Appointments/book_appointment_form.php"><span class="uk-margin-small-right" uk-icon="user"></span> Book Appointment</a></li> -->
                    
                    
                    <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Account Details</a></li>

                    <hr>
                    
                    <li><a href="#settings" onclick="showSection('settings')"><span class="uk-margin-small-right" uk-icon="cog"></span> Settings</a></li>
                </ul>
            </div>
        </div>


        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <div id="appointments" class="section">
                <h1 class="uk-text-bold">Appointments</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <table class="uk-table uk-table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Session Type</th>
                                <th>Patient</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($appointment['date']); ?></td>
                                    <td><?= htmlspecialchars($appointment['time']); ?></td>
                                    <td><?= htmlspecialchars($appointment['session_type']); ?></td>
                                    <td><?= htmlspecialchars($appointment['patient_name']); ?></td>
                                    <td><?= ucfirst($appointment['status']); ?></td>
                                    <td>
                                        <!-- ✅ Cancel button (Allowed only for "Pending" or "Waitlisted") -->
                                        <?php if (in_array($appointment['status'], ["pending", "waitlisted"])): ?>
                                            <button class="uk-button uk-button-danger cancel-btn" data-id="<?= $appointment['appointment_id']; ?>">Cancel</button>
                                        <?php endif; ?>

                                        <!-- ✅ Edit button (Only for "Pending" & edit_count < 2) -->
                                        <?php if ($appointment['status'] === "pending" && $appointment['edit_count'] < 2): ?>
                                            <button class="uk-button uk-button-primary edit-btn" data-id="<?= $appointment['appointment_id']; ?>"
                                                data-date="<?= $appointment['date']; ?>" data-time="<?= $appointment['time']; ?>">
                                                Reschedule (<?= 2 - $appointment['edit_count']; ?> left)
                                            </button>
                                        <?php else: ?>
                                            <button class="uk-button uk-button-default" disabled>Reschedule Is Not Allowed</button>
                                        <?php endif; ?>
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
                            <select class="uk-select" name="patient_gender">
                                <option value="" disabled selected>Select Patient Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

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

                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button class="uk-button uk-button-primary" type="button" id="registerPatientButton">Register</button>
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
                    <form id="editPatientForm" action="../Appointments/patient/patient_data/update_patient_process.php" method="POST" enctype="multipart/form-data" class="uk-form-stacked" style="display: none;">
                        <input type="hidden" name="patient_id" id="patient_id">
                        <input type="hidden" name="existing_profile_picture" id="existing_profile_picture"> <!-- Store existing picture -->

                        <label>First Name:</label>
                        <input class="uk-input" type="text" name="first_name" id="first_name" required>

                        <label>Last Name:</label>
                        <input class="uk-input" type="text" name="last_name" id="last_name" required>

                        <label>Birthday:</label>
                        <input class="uk-input" type="date" name="bday" id="bday" min="2008-01-01" max="2024-12-31" required>

                        <label>Gender:</label>
                        <select class="uk-select" name="gender" id="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>

                        <label>Profile Picture:</label>
                        <input class="uk-input" type="file" name="profile_picture" id="profile_picture_input">
                        
                        <div class="uk-margin">
                            <img id="profile_picture_preview" src="" class="uk-border-rounded uk-margin-top" style="width: 100px; height: 100px; display: none;">
                        </div>

                        <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Changes</button>
                        <button id="editPatientBtn" class="uk-button uk-button-secondary uk-margin-top" type="button">Edit</button>
                    </form>

                    <!-- 🔹 Referral Upload Form -->
                    <form id="uploadReferralForm" action="../Appointments/patient/patient_data/upload_referral_process.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="patient_id" id="referral_patient_id">
                        <h4>Upload Doctor's Referral</h4>

                        <label>Referral Type:</label>
                        <select class="uk-select" name="referral_type" id="referral_type_select" required>
                            <option value="" disabled selected>Select Referral Type</option>
                            <option value="official">Official Referral</option>
                            <option value="proof_of_booking">Proof of Booking</option>
                        </select>

                        <label>Upload File:</label>
                        <input class="uk-input" type="file" name="referral_file" id="referral_file_input" required>

                        <button class="uk-button uk-button-primary uk-margin-top" type="submit">
                            Upload Referral
                        </button>

                        <div class="uk-margin">
                            <label>Official Referral:</label>
                            <a id="official_referral_link" href="#" class="uk-button uk-button-link" target="_blank" style="display: none;">View File</a>
                        </div>

                        <div class="uk-margin">
                            <label>Proof of Booking:</label>
                            <a id="proof_of_booking_link" href="#" class="uk-button uk-button-link" target="_blank" style="display: none;">View File</a>
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

            <!--Account Details Card-->
            <div id="account-details" style="display: none;" class="section uk-width-1-1 uk-width-4-5@m uk-padding">
                <h1 class="uk-text-bold">Account Details</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <div class="uk-flex uk-flex-center">
                        <div class="uk-width-1-4">
                            <img class="uk-border-circle" src="<?php echo $profilePicture; ?>" alt="Profile Photo">
                        </div>
                    </div>
                </div>

                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title uk-text-bold">User Details</h3>
                    <form class="uk-grid-small" uk-grid>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" value="<?php echo $firstName; ?>" disabled>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" value="<?php echo $lastName; ?>" disabled>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" value="<?php echo $email; ?>" disabled>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel"  value="<?php echo '0' . $phoneNumber; ?>" disabled>
                        </div>
                    </form>
                </div>
            </div>


             <!-- Settings -->
             <div id="settings" class="section" style="display: none;">
                <h1 class="uk-text-bold">Settings</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <form action="settings.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_profile_picture">
                        <div class="uk-flex uk-flex-middle">
                            <div class="profile-upload-container">
                                <img class="uk-border-circle profile-preview" src="<?php echo $profilePicture; ?>" alt="Profile Photo">
                                <div class="uk-flex uk-flex-column uk-margin-left">
                                    <input type="file" name="profile_picture" id="profileUpload" class="uk-hidden">
                                    <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" id="uploadButton" disabled>
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
                <button type="button" class="uk-button uk-button-secondary" id="editButton">Edit</button>
                <button class="uk-button uk-button-primary" type="submit" id="saveButton" disabled>Save Changes</button>
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
        </form>

        <?php unset($_SESSION['update_errors']); ?>
        <?php unset($_SESSION['update_success']); ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
     document.addEventListener("DOMContentLoaded", function () {
    // Select elements
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
    
    // Add new elements
    const editEmailButton = document.createElement("button");
    editEmailButton.id = "editEmailButton";
    editEmailButton.textContent = "Edit Email";
    editEmailButton.className = "uk-button uk-button-secondary";  // Using UK button classes for consistency
    editEmailButton.style.marginRight = "15px";
    editEmailButton.style.fontSize = "16px";
    editEmailButton.style.padding = "8px 20px";
    editEmailButton.style.fontWeight = "bold";
    
    const cancelVerificationButton = document.createElement("button");
    cancelVerificationButton.id = "cancelVerificationButton";
    cancelVerificationButton.textContent = "Cancel Verification";
    cancelVerificationButton.className = "uk-button uk-button-danger";  // Using UK button classes for consistency
    cancelVerificationButton.style.fontSize = "16px";
    cancelVerificationButton.style.padding = "8px 20px";
    cancelVerificationButton.style.fontWeight = "bold";
    
    // Create a container for the buttons
    const buttonContainer = document.createElement("div");
    buttonContainer.className = "uk-margin-medium-top";  // Using UK margin class
    buttonContainer.style.display = "flex";
    buttonContainer.style.justifyContent = "flex-start";
    buttonContainer.style.marginTop = "20px";
    buttonContainer.appendChild(editEmailButton);
    buttonContainer.appendChild(cancelVerificationButton);
    
    // Insert these buttons after the OTP input
    if (otpSection) {
        // Append the button container to the OTP section (after all existing elements)
        otpSection.appendChild(buttonContainer);
    }

    let originalValues = {};
    inputs.forEach(input => originalValues[input.id] = input.value);
    
    // Original email value to restore if verification is canceled
    let originalEmail = emailInput ? emailInput.value : '';

    // Edit Button Click Event
    if (editButton) {
        editButton.addEventListener("click", function () {
            if (editButton.textContent === "Edit") {
                inputs.forEach(input => input.disabled = false);
                saveButton.disabled = false;
                editButton.textContent = "Cancel";
                uploadButton.disabled = false; // Enable upload button
                removePhotoButton.style.pointerEvents = "auto";
                removePhotoButton.style.color = "";
            } else {
                inputs.forEach(input => {
                    input.value = originalValues[input.id];
                    input.disabled = true;
                });
                saveButton.disabled = true;
                editButton.textContent = "Edit";
                otpSection.style.display = "none"; // Hide OTP section on cancel
                uploadButton.disabled = true; // Disable upload button
                removePhotoButton.style.pointerEvents = "none";
                removePhotoButton.style.color = "grey";
                
                // Reset save button state
                saveButton.textContent = "Save";
                saveButton.dataset.step = "";
            }
        });
    }

    // Save Button Click Event
    if (saveButton) {
        saveButton.addEventListener("click", function (event) {
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
    
    // Cancel Verification Button Click Event - Cancels email verification and restores original email
    if (cancelVerificationButton) {
        cancelVerificationButton.addEventListener("click", function(event) {
            event.preventDefault();
            
            // Confirm cancellation
            if (confirm("Are you sure you want to cancel? Your email will not be changed.")) {
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
            }
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
            alert("All fields are required.");
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
            headers: { "Content-Type": "application/x-www-form-urlencoded" }
        })
            .then(response => response.json())
            .then(data => {
                if (data.errors) {
                    Object.entries(data.errors).forEach(([key, message]) => {
                        let errorElement = document.querySelector(`[data-error="${key}"]`);
                        if (errorElement) errorElement.textContent = message;
                    });
                } else if (data.otp_required) {
                    alert("OTP sent to your new email. Please enter the OTP to verify.");
                    otpSection.style.display = "block";
                    saveButton.textContent = "Verify OTP";
                    saveButton.dataset.step = "verify";
                } else if (data.success) {
                    alert(data.success);
                    location.reload();
                } else {
                    alert("Something went wrong.");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred. Please try again.");
            });
    }

    // Function to Verify OTP
    function verifyOTP() {
        let otp = otpInput.value.trim();
        if (!otp) {
            alert("Please enter OTP.");
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
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
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
                alert("Email updated successfully!");
                location.reload();
            } else if (data.error) {
                alert(data.error);
            } else {
                alert("Invalid OTP. Please try again.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("An error occurred during OTP verification.");
        });
    }



        // Profile Picture Upload Handling
        if (uploadButton && profileUploadInput) {
            uploadButton.addEventListener("click", function () {
                profileUploadInput.click();
            });

            profileUploadInput.addEventListener("change", function () {
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
            form.addEventListener("submit", function (event) {
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

        // Initialize OTP section to be hidden
        otpSection.style.display = "none";
    });




    function removeProfilePhoto() {
    if (confirm("Are you sure you want to remove your profile picture?")) {
        fetch("../Accounts/manageaccount/updateinfo.php", {
            method: "POST",
            body: JSON.stringify({ action: "remove_profile_picture" }),
            headers: { "Content-Type": "application/json" }
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

        // ✅ Edit Appointment (Reschedule)
        document.querySelectorAll(".edit-btn").forEach(button => {
            button.addEventListener("click", function() {
                let appointmentId = this.getAttribute("data-id");
                let currentStatus = this.getAttribute("data-status"); // Get status from dataset

                Swal.fire({
                    title: "Edit Appointment",
                    html: `<label>New Date:</label> <input type="date" id="appointmentDate" class="swal2-input">
                           <label>New Time:</label> <input type="time" id="appointmentTime" class="swal2-input">`,
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
                appointmentForm.addEventListener("submit", function (e) {
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
        let editPatientBtn = document.getElementById("editPatientBtn");
        let saveProfileChangesBtn = document.querySelector("#editPatientForm button[type='submit']");
        
        // Referral Section
        let uploadReferralSection = document.getElementById("uploadReferralForm");
        // Initially hide referral section
        uploadReferralSection.style.display = "none";

        // Initially disable form fields
        function toggleFormInputs(disable) {
            firstNameInput.disabled = disable;
            lastNameInput.disabled = disable;
            birthdayInput.disabled = disable;
            genderInput.disabled = disable;
            profilePicInput.disabled = disable;
            saveProfileChangesBtn.style.display = disable ? "none" : "inline-block"; // Hide "Save" when disabled
        }
        
        // Load patient details when selecting from dropdown
        patientDropdown.addEventListener("change", function () {
            let patientID = this.value;
            if (!patientID) {
                editForm.style.display = "none";
                uploadReferralSection.style.display = "none"; // Hide referral section when no patient is selected
                return;
            }

            fetch("../Appointments/patient/patient_data/fetch_patient_details.php?patient_id=" + patientID)
            .then(response => response.json())
            .then(data => {
                console.log("Fetched Data:", data); // Debugging
                if (data.status === "success") {            

                    patientIDInput.value = data.patient.patient_id;
                    firstNameInput.value = data.patient.first_name;
                    lastNameInput.value = data.patient.last_name;
                    genderInput.value = data.patient.gender;
                    existingProfilePicInput.value = data.patient.profile_picture;

                     // Simply assign the birthday value directly without reformatting
                     if (data.patient.bday && data.patient.bday !== "0000-00-00" && data.patient.bday.trim() !== "") {
                    birthdayInput.value = data.patient.bday;
                    console.log("Setting birthday value to:", data.patient.bday);
                    } else {
                        birthdayInput.value = "";
                    }
                    
                    if (data.patient.bday === null) {
                        console.log("Birthday is null for patient ID:", patientID);
                    }

                    if (data.patient.profile_picture) {
                        profilePicPreview.src = "../uploads/profile_pictures/" + data.patient.profile_picture;
                        profilePicPreview.style.display = "block";
                    } else {
                        profilePicPreview.style.display = "none";
                    }

                    // Disable form inputs initially
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
        profilePicInput.addEventListener("change", function () {
            let file = this.files[0];
            if (file) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    profilePicPreview.src = e.target.result;
                    profilePicPreview.style.display = "block";
                };
                reader.readAsDataURL(file);
            }
        });

        // Upload Referral Logic
        document.getElementById("uploadReferralBtn").addEventListener("click", function () {
            let patientID = document.getElementById("patientDropdown").value;
            let referralType = document.getElementById("referral_type_select").value;
            let referralFile = document.getElementById("referral_file_input").files[0];

            if (!patientID|| !referralType || !referralFile) {
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
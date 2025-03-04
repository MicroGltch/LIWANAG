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
        $profilePicture = '../uploads/client_profile_pictures/' . $userData['profile_picture']; // Corrected path
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

// EDIT PATIENT FORM PHP from edit_patient_form
// Fetch patients for the dropdown
$patientsQuery = "SELECT patient_id, first_name, last_name FROM patients WHERE account_id = ?";
$stmt = $connection->prepare($patientsQuery);
$stmt->bind_param("i", $_SESSION['account_ID']);
$stmt->execute();
$result = $stmt->get_result();
$patients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

    <!-- <script src="dashboardJS/client.js"></script>  -->
</head>

</head>

<body>
    <script>
        console.log('Session Username:', <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>);
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
                                <img class="profile-image" src="../CSS/default.jpg" alt="Profile Image" uk-img>
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
                    <li><a href="#appointments" onclick="showSection('appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Appointments</a></li>
                    <!-- Reference code: client_view_appointments.php -->

                    <li><a href="#register-patient" onclick="showSection('register-patient')"><span class="uk-margin-small-right" uk-icon="user"></span> Register Patient</a></li>
                    <!-- Reference code: register_patient_form.php -->

                    <li><a href="#view-registered-patients" onclick="showSection('view-registered-patients')"><span class="uk-margin-small-right" uk-icon="user"></span> View Registered Patients</a></li>
                    <!-- Reference code: edit_patient_form.php -->

                    <!-- <li><a href="../Appointments/book_appointment_form.php"><span class="uk-margin-small-right" uk-icon="user"></span> Book Appointment</a></li> -->
                    <li><a href="#book-appointment" onclick="showSection('book-appointment')"><span class="uk-margin-small-right" uk-icon="user"></span> Book Appointment</a></li>
                    <!-- Reference code: book_appointment_form.php -->

                    <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Account Details</a></li>
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
                            <label class="uk-form-label">Age</label>
                            <input class="uk-input" type="number" name="patient_age" required>
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



                    <!-- 🔹 Patient Details Form (Initially Hidden) -->
                    <form id="editPatientForm" class="uk-grid-small uk-grid" action="../Appointments/patient/patient_data/update_patient_process.php" method="POST" enctype="multipart/form-data" style="display: none;" uk-grid>




                        <input type="hidden" name="patient_id" id="patient_id">



                        <input type="hidden" name="existing_profile_picture" id="existing_profile_picture"> <!-- Store existing picture -->



                        <div class="uk-flex uk-flex-middle">

                            <div class="profile-upload-container uk-width-1@s " style="padding: 25px; ">


                                <img id="profile_picture_preview" src="" class="uk-border-rounded uk-margin-top" style="width: 150px; height: 150px; display: none;" alt="Profile Photo">


                                <div class="uk-flex uk-flex-column uk-margin-left">

                                    <input type="file" name="profile_picture" id="profileUpload" class="uk-hidden">


                                    <!-- onclick="document.getElementById('profileUpload').click();" -->
                                    <button id="profile_picture_input" class="uk-button uk-button-primary uk-margin-small-bottom">Upload Photo</button>

                                    <div class="uk-text-center">
                                        <a href="#" class="uk-link-muted" onclick="removeProfilePhoto();">remove</a>
                                    </div>
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

                            <!--
                        <div class="uk-margin">
                            <img id="profile_picture_preview" src="" class="uk-border-rounded uk-margin-top" style="width: 100px; height: 100px; display: none;">
                        </div>

                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Profile Picture</label>
                            <input class="uk-input" type="file" name="profile_picture" id="profile_picture_input">
                        </div>
                        -->

                            <div class="uk-width-1-2@s">
                                <label class="uk-form-label">First Name</label>
                                <input class="uk-input" type="text" name="first_name" id="first_name" required>
                            </div>

                            <div class="uk-width-1-2@s">
                                <label class="uk-form-label">Last Name</label>
                                <input class="uk-input" type="text" name="last_name" id="last_name" required>
                            </div>


                            <div class="uk-width-1-2@s">
                                <label class="uk-form-label">Age</label>
                                <input class="uk-input" type="number" name="age" id="age" required>
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

                            <div class="uk-width-1-1 uk-text-right uk-margin-top">
                                <button class="uk-button uk-button-primary uk-margin-top" type="submit">Save Profile Changes</button>
                            </div>


                            
                        <div class="uk-width-1@s">
                            <hr>
                            <h4>Upload Doctor's Referral </h4>
                        </div>
                            

                            <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Referral Type</label>
                                <select class="uk-select" name="referral_type" id="referral_type_select" required>
                                    <option value="" disabled selected>Select Referral Type</option>
                                    <option value="official">Official Referral</option>
                                    <option value="proof_of_booking">Proof of Booking</option>
                                </select>
                            </div>

                            <div class="uk-width-1@s">
                            <label class="uk-form-label">Upload File</label>
                            <input  type="file" name="referral_file" id="referral_file_input" required>
                            </div>


                        </div>


                        
                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                        <button class="uk-button uk-button-primary uk-margin-top" type="button" id="uploadReferralBtn">
                            Upload Referral
                        </button>

                        </div>
                    </form>


                </div>
            </div>



            <!-- Book Appointment -->
            <div id="book-appointment" style="display: none;" class="section uk-width-1-1 uk-width-4-5@m uk-padding">
                <h1 class="uk-text-bold">Book Appointment</h1>

            </div>

            <!--Account Details Card-->
            <div id="account-details" style="display: none;" class="section uk-width-1-1 uk-width-4-5@m uk-padding">
                <h1 class="uk-text-bold">Account Details</h1>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <div class="uk-flex uk-flex-center">
                        <div class="uk-width-1-4">
                            <img class="uk-border-circle" src="../CSS/default.jpg" alt="Profile Photo">
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
                            <input class="uk-input" type="tel" value="<?php echo $phoneNumber; ?>" disabled>
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

                        <!---->
                        <div class="uk-flex uk-flex-middle">
                            <div class="profile-upload-container">
                                <img class="uk-border-circle profile-preview" src="<?php echo $profilePicture; ?>" alt="Profile Photo">
                                <div class="uk-flex uk-flex-column uk-margin-left">
                                    <input type="file" name="profile_picture" id="profileUpload" class="uk-hidden">
                                    <button class="uk-button uk-button-primary uk-margin-small-bottom" onclick="document.getElementById('profileUpload').click();">Upload Photo</button>
                                    <div class="uk-text-center">
                                        <a href="#" class="uk-link-muted" onclick="removeProfilePhoto();">remove</a>
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

                        <!---->
                        <button type="submit" class="uk-button uk-button-primary uk-margin-top">Upload</button>
                    </form>
                </div>

                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title uk-text-bold">User Details</h3>
                    <form id="settingsvalidate" action="../Accounts/manageaccount/updateinfo.php" method="post" class="uk-grid-small" uk-grid>
                        <input type="hidden" name="action" value="update_user_details">
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" name="firstName" id="firstName" value="<?php echo $firstName; ?>">
                            <small style="color: red;" class="error-message" data-error="firstName"></small>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" name="lastName" id="lastName" value="<?php echo $lastName; ?>">
                            <small style="color: red;" class="error-message" data-error="lastName"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" name="email" id="email" value="<?php echo $email; ?>">
                            <small style="color: red;" class="error-message" data-error="email"></small>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel" name="phoneNumber" id="mobileNumber"
                                value="<?= htmlspecialchars($_SESSION['phoneNumber'] ?? $phoneNumber, ENT_QUOTES, 'UTF-8') ?>">
                            <small style="color: red;" class="error-message" data-error="phoneNumber"></small>
                        </div>
                        <small style="color: red;" class="error-message" data-error="duplicate"></small>
                        <small style="color: green;" class="error-message" id="successMessage"></small>
                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button class="uk-button uk-button-primary" type="submit">Save Changes</button>
                        </div>
                    </form>
                    <?php unset($_SESSION['update_errors']); // Clear errors after displaying 
                    ?>
                    <?php unset($_SESSION['update_success']); // Clear success message 
                    ?>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- Javascript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


</body>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("settingsvalidate").addEventListener("submit", function(event) {
            event.preventDefault(); // Prevent default form submission

            let formData = new FormData(this);

            fetch("../Accounts/manageaccount/updateinfo.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Clear previous error messages
                    document.querySelectorAll(".error-message").forEach(el => el.textContent = "");

                    if (data.errors) {
                        // Show errors under respective inputs
                        Object.keys(data.errors).forEach(key => {
                            let errorElement = document.querySelector(`small[data-error="${key}"]`);
                            if (errorElement) {
                                errorElement.textContent = data.errors[key];
                            }
                        });
                    } else if (data.success) {
                        alert(data.success);
                        location.reload(); // Reload page on success
                    }
                })
                .catch(error => console.error("Error:", error));
        });
    });

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

    function removeProfilePhoto() {
        document.querySelector('.profile-preview').src = '../CSS/default.jpg';
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
                        });
                    } else {
                        Swal.fire("Error!", data.message, "error");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    Swal.fire("Error!", "An unexpected error occurred.", "error");
                });
        });


        // VIEW AND EDIT PATIENT JS

        let patientDropdown = document.getElementById("patientDropdown");
        let editForm = document.getElementById("editPatientForm");
        let patientIDInput = document.getElementById("patient_id");
        let firstNameInput = document.getElementById("first_name");
        let lastNameInput = document.getElementById("last_name");
        let ageInput = document.getElementById("age");
        let genderInput = document.getElementById("gender");
        let profilePicPreview = document.getElementById("profile_picture_preview");
        let profilePicInput = document.getElementById("profile_picture_input");
        let existingProfilePicInput = document.getElementById("existing_profile_picture");

        patientDropdown.addEventListener("change", function() {
            let patientID = this.value;
            if (!patientID) {
                editForm.style.display = "none";
                return;
            }

            fetch("../Appointments/patient/patient_data/fetch_patient_details.php?patient_id=" + patientID)
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        patientIDInput.value = data.patient.patient_id;
                        firstNameInput.value = data.patient.first_name;
                        lastNameInput.value = data.patient.last_name;
                        ageInput.value = data.patient.age;
                        genderInput.value = data.patient.gender;
                        existingProfilePicInput.value = data.patient.profile_picture;

                        if (data.patient.profile_picture) {
                            profilePicPreview.src = "../uploads/profile_pictures/" + data.patient.profile_picture;
                            profilePicPreview.style.display = "block";
                        } else {
                            profilePicPreview.style.display = "none";
                        }

                        // Display latest referral details
                        if (data.latest_referrals.official) {
                            document.getElementById("official_referral_link").href = "../uploads/doctors_referrals/" + data.latest_referrals.official.official_referral_file;
                            document.getElementById("official_referral_link").style.display = "block";
                        } else {
                            document.getElementById("official_referral_link").style.display = "none";
                        }

                        if (data.latest_referrals.proof_of_booking) {
                            document.getElementById("proof_of_booking_link").href = "../uploads/doctors_referrals/" + data.latest_referrals.proof_of_booking.proof_of_booking_referral_file;
                            document.getElementById("proof_of_booking_link").style.display = "block";
                        } else {
                            document.getElementById("proof_of_booking_link").style.display = "none";
                        }

                        editForm.style.display = "block";
                    } else {
                        editForm.style.display = "none";
                        Swal.fire("Error", "Patient details could not be loaded.", "error");
                    }
                })
                .catch(error => console.error("Error fetching patient details:", error));
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

</html>
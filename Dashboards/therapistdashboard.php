<?php
include "../dbconfig.php";
session_start();

// ✅ Restrict Access to Therapists Only
if (!isset($_SESSION['account_ID']) || strtolower($_SESSION['account_Type']) !== "therapist") {
    header("Location: ../Accounts/loginpage.php");
    exit();
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
        $profilePicture = '../uploads/profile_pictures/' . $userData['profile_picture']; // Corrected path
    } else {
        $profilePicture = '../CSS/default.jpg';
    }
    // $profilePicture = $userData['profile_picture'] ? '../uploads/' . $userData['profile_picture'] : '../CSS/default.jpg';
} else {
    echo "No Data Found.";
}

// Fetch therapist's upcoming appointments
$query = "SELECT a.appointment_id, a.date, a.time, a.session_type, a.status,
                 p.patient_id, p.first_name, p.last_name 
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          WHERE a.therapist_id = ? AND a.status IN ('Approved', 'Pending')
          ORDER BY a.date ASC, a.time ASC";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $therapistID);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);


$stmt->close();


//upcoming appointments

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width" />
    <title>LIWANAG - Dashboard</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../CSS/style.css" type="text/css" />

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!-- FullCalendar Library -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.js'></script>
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
                    <li><a href="#dashboard" onclick="showSection('dashboard')"><span class="uk-margin-small-right" uk-icon="home"></span> Dashboard</a></li>
                
                    <hr>
                    
                    <!-- <li class="uk-parent">
                        <a href="#appointments" onclick="showSection('appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Appointments</a>
                        
                        <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                            <li style="padding:0px 0px 15px 0px"><a href="#upcoming-appointments" onclick="showSection('upcoming-appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Upcoming Appointments </a></li>

                            <li><a href="../Appointments/app_process/rebook_appointment.php"><span class="uk-margin-small-right" uk-icon="calendar"></span> Rebook Appointment</a></li>
                        </ul>
                    </li> 

                    <hr> -->

                    <li class="uk-parent">
                    <a href="#patient-details" onclick="showSection('patient-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Patients</a>
                        
                        <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                        <li><a href="#rebook-patient" onclick="showSection('rebook-patient')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Rebook Patient </a></li>
                        </ul>
                    </li>

                    <hr>

                    <li class="uk-parent">
                    <a href="#manage-availability" onclick="showSection('manage-availability')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Manage Availability </a>
                        
                        <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                        <li><a href="#adjust-availability" onclick="showSection('adjust-availability')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Adjust Availability </a></li>
                        </ul>
                    </li>

                    <hr>
                    
                    <li><a href="#settings" onclick="showSection('settings')"><span class="uk-margin-small-right" uk-icon="cog"></span> Settings</a></li>


                    <li></li>




                    <!-- Current code redirect na nagbase muna ako kay Rap na provided codes : PAGE CHANGE SIMILAR TO HEADTHERAPISTDASHBOARD-->


         <!--           <li><a href="../Appointments/app_process/rebook_appointment.php"><span class="uk-margin-small-right" uk-icon="calendar"></span> Rebook Appointment</a></li>   -->

                </ul>
            </div>

            <div class="sidebar-nav">
                <ul class="uk-nav uk-nav-default">
                </ul>
            </div>

        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <!-- Dashboard Section -->
            <div id="dashboard" class="section">
                <h1 class="uk-text-bold">Dashboard</h1>

                <!-- Calendar Container  **OLD DASHBOARD**
                <div class="calendar-container uk-flex uk-flex-row">
                    <div class="uk-width-expand">
                        <div class="dashboard-calendar-container uk-card uk-card-default uk-card-body">
                            <div class="dashboard-header uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
                                <div class="dashboard-month-selector">
                                    <select class="uk-select month-select" id="monthSelect">
                                    </select>
                                </div>
                            </div>
                            <div id="calendar"></div>
                        </div>
                    </div>

                    Right Sidebar 
                    <div class="uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
                        <div class="sidebar-nav">
                            <ul class="uk-nav uk-nav-default">
                                <li class="uk-nav-header">
                                    <span class="uk-margin-small-right" uk-icon="clock"></span>
                                    Pending Approval
                                </li>
                                <div class="pending-appointments">
                                </div>

                                <li class="uk-nav-header uk-margin-top">
                                    <span class="uk-margin-small-right" uk-icon="calendar"></span>
                                    Upcoming
                                </li>
                                <div class="upcoming-appointments">
                                </div>
                            </ul>
                        </div>
                    </div>
                </div>-->

                <div class="uk-card uk-card-default uk-card-body uk-margin">

                    <iframe id="upcomingAppointmentsFormFrame" src="../Appointments/app_manage/upcoming_appointments.php" style="width: 100%; height: 800px; border: none;">
                    </iframe>

                </div>

            </div> 

            <!-- Controls for DataTable -->
            <div id="appointmentsTableControls" class="uk-margin uk-flex uk-flex-between uk-flex-middle">
                <div id="tableLength"></div> <!-- Items per page -->
                <div id="tableSearch"></div> <!-- Search box -->
            </div>

            <!--Appoinments-->
            <div id="appointments" class="section">
                <h1 class="uk-text-bold">Appointments</h1>
                <p>Appointment table will be displayed here.</p>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <table id="appointmentsTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Name</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Approve</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- population area -->
                        </tbody>
                    </table>
                </div>

            </div>

            <!--Upcoming Appointments-->
            <div id="upcoming-appointments" style="display: none; " class="section">
                <h1 class="uk-text-bold">Upcoming Appointments</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">

                    <iframe id="upcomingAppointmentsFormFrame" src="../Appointments/app_manage/upcoming_appointments.php" style="width: 100%; height: 800px; border: none;">
                    </iframe>

                </div>
            </div>

            <!--Patients-->
            <div id="patient-details" style="display: none;" class="section">
                <h1 class="uk-text-bold">Patient List</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <table id="patientTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Service Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>

                            <!-- population area -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!--Rebook Patient-->
            <div id="rebook-patient" style="display: none; " class="section">
                <h1 class="uk-text-bold">Rebook Patient</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">

                    <iframe id="rebookPatientFormFrame" src="../Appointments/patient/patient_manage/rebook_patient.php" style="width: 100%; height: 800px; border: none;">
                    </iframe>

                </div>
            </div>



            <!--Manage Availablity-->
            <div id="manage-availability" style="display: none; " class="section">
                <h1 class="uk-text-bold">Manage Availability</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">

                    <iframe id="manageAvailabilityFrame" src="../Dashboards/forTherapist/manageSchedule/manage_availability.php" style="width: 100%; height: 800px; border: none;">
                    </iframe>

                </div>
            </div>


            <!--Adjust Availablity-->
            <div id="adjust-availability" style="display: none; " class="section">
                <h1 class="uk-text-bold">Adjust Availability</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">

                    <iframe id="adjustAvailabilityFrame" src="../Dashboards/forTherapist/manageSchedule/override_availability.php" style="width: 100%; height: 800px; border: none;">
                    </iframe>

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

            <div id="tablePagination" class="uk-margin uk-flex uk-flex-center"></div> <!-- Pagination -->
        </div>
    </div>

    </div>








    <!-- Javascript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


</body>

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
    document.addEventListener('DOMContentLoaded', function() {
        // Populate month select
        const monthSelect = document.getElementById('monthSelect');
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        const currentDate = new Date();
        const currentMonth = currentDate.getMonth();
        const currentYear = currentDate.getFullYear();

        // Add months for current year and next year
        for (let year = currentYear; year <= currentYear + 1; year++) {
            months.forEach((month, index) => {
                // Skip past months for current year
                if (year === currentYear && index < currentMonth) return;

                const option = document.createElement('option');
                option.value = `${year}-${(index + 1).toString().padStart(2, '0')}`;
                option.textContent = `${month} ${year}`;

                // Select current month by default
                if (year === currentYear && index === currentMonth) {
                    option.selected = true;
                }

                monthSelect.appendChild(option);
            });
        }

        // Initialize calendar
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            initialDate: currentDate,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            height: 'auto',
            events: [
                // Your events here
            ]
        });
        calendar.render();

        // Handle month select change
        monthSelect.addEventListener('change', function(e) {
            const [year, month] = e.target.value.split('-');
            calendar.gotoDate(`${year}-${month}-01`);
        });

        // Show dashboard by default
        showSection('dashboard');

        // Update active state in sidebar
        document.querySelectorAll('.sidebar-nav li').forEach(item => {
            item.classList.remove('uk-active');
        });
        document.querySelector('.sidebar-nav li:first-child').classList.add('uk-active');
    });

    // Sidebar toggle
    document.querySelector('.sidebar-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar-nav').classList.toggle('uk-open');
    });

    function showSection(sectionId) {
        // Hide all sections
        document.querySelectorAll('.section').forEach(section => {
            section.style.display = 'none';
        });

        // Show selected section
        document.getElementById(sectionId).style.display = 'block';

        // Update active state in sidebar
        document.querySelectorAll('.sidebar-nav li').forEach(item => {
            item.classList.remove('uk-active');
        });
        document.querySelector(`.sidebar-nav li a[href="#${sectionId}"]`).parentElement.classList.add('uk-active');

        // Trigger window resize to fix calendar rendering if showing dashboard
        if (sectionId === 'dashboard') {
            window.dispatchEvent(new Event('resize'));
        }
    }

    function previewProfilePhoto(event) {
        const reader = new FileReader();
        reader.onload = function() {
            const preview = document.querySelector('.profile-preview');
            preview.src = reader.result;
        }
        reader.readAsDataURL(event.target.files[0]);
    }

    $(document).ready(function() {
        $('#patientTable').DataTable();
    });


    $(document).ready(function() {
        $('#appointmentsTable').DataTable({
            columnDefs: [{
                targets: -1, // targets the last column (Actions)
                data: null,
                defaultContent: '<button class="uk-button uk-button-danger uk-button-small">Cancel</button>'
            }]
        });

        // cancel button
        $('#appointmentsTable tbody').on('click', 'button', function() {
            var data = $('#appointmentsTable').DataTable().row($(this).parents('tr')).data();
            alert('Cancel appointment for ' + data[2] + '?');
            // cancellation logic here
        });
    });



    //Rebook Appointmentment Form iframe
    let rebookPatientFormFrame = document.getElementById("rebookPatientFormFrame");

    rebookPatientFormFrame.onload = function() {
        let rebookPatientForm = rebookPatientFormFrame.contentDocument.getElementById("rebookPatientForm");

        if (rebookPatientForm) {
            rebookPatientForm.addEventListener("submit", function(e) {
                e.preventDefault();

                let formData = new FormData(this);

                fetch("../Appointments/patient/patient_manage/rebook_patient.php", {
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


    //Upcoming Appointmentment Form iframe
    let upcomingAppointmentsFormFrame = document.getElementById("upcomingAppointmentsFormFrame");

    upcomingAppointmentsFormFrame.onload = function() {
        let upcomingAppointmentForm = upcomingAppointmentsFormFrame.contentDocument.getElementById("upcomingAppointmentForm");

        if (upcomingAppointmentForm) {
            upcomingAppointmentForm.addEventListener("submit", function(e) {
                e.preventDefault();

                let formData = new FormData(this);

                fetch("../Appointments/app_manage/upcoming_appointments.php", {
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




    //manageAvailabilityFrame
    let manageAvailabilityFrame = document.getElementById("manageAvailabilityFrame");

    manageAvailabilityFrame.onload = function() {
        let manageAvailabilityForm = manageAvailabilityFrame.contentDocument.getElementById("manageAvailabilityForm");

        if (manageAvailabilityForm) {
            manageAvailabilityForm.addEventListener("submit", function(e) {
                e.preventDefault();

                let formData = new FormData(this);

                fetch("../Dashboards/forTherapist/manageSchedule/override_availability.php", {
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
</script>

</html>
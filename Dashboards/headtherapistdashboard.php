<?php
require_once "../dbconfig.php";
session_start();

// ✅ Ensure only Admins & Head Therapists can access
if (!isset($_SESSION['account_ID']) || !in_array(strtolower($_SESSION['account_Type']), ["head therapist"])) {
    header("Location: ../Accounts/loginpage.php");
    exit();
}

$userid = $_SESSION['account_ID'];

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

    if ($userData['profile_picture']) {
        $profilePicture = '/LIWANAG/uploads/profile_pictures/' . $userData['profile_picture']; // Corrected path
    } else {
        $profilePicture = '../CSS/default.jpg';
    }
} else {
    echo "No Data Found.";
}

$stmt->close();

// Define all possible status types
$allStatuses = ['pending', 'approved', 'waitlisted', 'completed', 'cancelled', 'declined', 'others'];

// Initialize counts for all status types with 0
$appointmentCounts = array_fill_keys($allStatuses, 0);

// ✅ Query to count appointments by type
$countQuery = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$result = $connection->query($countQuery);
while ($row = $result->fetch_assoc()) {
    $status = strtolower($row['status']);
    if (in_array($status, $allStatuses)) {
        $appointmentCounts[$status] = $row['count'];
    } else {
        $appointmentCounts['others'] += $row['count'];
    }
}

// ✅ Query to get all appointments
$appointmentQuery = "SELECT a.appointment_id, a.patient_id, a.date, a.time, a.status, 
                            p.first_name, p.last_name,
                            u.account_FName AS client_firstname, u.account_LName AS client_lastname 
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.patient_id
                    JOIN users u ON a.account_id = u.account_ID
                    ORDER BY a.date ASC, a.time ASC";
$appointments = $connection->query($appointmentQuery)->fetch_all(MYSQLI_ASSOC);

// Get total count of all appointments
$totalQuery = "SELECT COUNT(*) as total FROM appointments";
$totalResult = $connection->query($totalQuery);
$totalAppointments = $totalResult->fetch_assoc()['total'];

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Appointment Overview</title>

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

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../CSS/style.css" type="text/css" />
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
        <!--Sidebar-->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
            <button class="uk-button uk-button-default uk-hidden@m uk-width-1-1 uk-margin-bottom sidebar-toggle" type="button">
                Menu <span uk-navbar-toggle-icon></span>
            </button>
            <div class="sidebar-nav">
                <ul class="uk-nav uk-nav-default">
                    <li class="uk-active"><a href="#dashboard" onclick="showSection('dashboard')">Dashboard</a></li>

                    <li><a href="#view-appointments" onclick="showSection('view-appointments')">View All Appointments</a></li>

                    <li><a href="#view-manage-appointments" onclick="showSection('view-manage-appointments')">Manage Appointments</a></li>                    

                    <li><a href="#timetable-settings" onclick="showSection('timetable-settings')">Manage Timetable Settings</a></li>

                    <li><a href="#account-details" onclick="showSection('account-details')">Account Details</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">

            <!-- Dashboard Section 📑 -->
            <div id="dashboard" class="section">
                <h1 class="uk-text-bold">Head Therapist Panel</h1>

                <!-- ✅ Total Appointments Card -->
                <div class="uk-margin-bottom">
                    <div class="uk-card uk-card-primary uk-card-body">
                        <h3 class="uk-card-title">Total Appointments</h3>
                        <p>Total: <?= $totalAppointments ?></p>
                    </div>
                </div>

                <!-- ✅ Appointment Summary Cards -->
                <div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
                    <?php foreach ($appointmentCounts as $status => $count): ?>
                        <div>
                            <div class="uk-card uk-card-default uk-card-body">
                                <h3 class="uk-card-title"><?= ucfirst($status) ?></h3>
                                <p>Total: <?= $count ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr>

                <!-- ✅ Appointments List -->
                <!-- <h3>All Appointments</h3>
                <table id="appointmentsTable" class="uk-table uk-table-striped uk-table-hover">
                    <thead>
                        <tr>
                            <th class="uk-table-shrink">Patient <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Client <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Date <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Time <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                            <th class="uk-table-shrink">Status <span uk-icon="icon: arrow-down-arrow-up"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= htmlspecialchars($appointment['first_name'] . " " . $appointment['last_name']); ?></td>
                                <td><?= htmlspecialchars($appointment['client_firstname'] . " " . $appointment['client_lastname']); ?></td>
                                <td><?= htmlspecialchars($appointment['date']); ?></td>
                                <td><?= htmlspecialchars($appointment['time']); ?></td>
                                <td><?= htmlspecialchars(ucfirst($appointment['status'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <script>
                    $(document).ready(function() {
                        $('#appointmentsTable').DataTable({
                            pageLength: 10,
                            lengthMenu: [10, 25, 50],
                            order: [
                                [2, 'asc']
                            ], // Sort by date column by default
                            language: {
                                lengthMenu: "Show _MENU_ entries per page",
                                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                                search: "Search:",
                                paginate: {
                                    first: "First",
                                    last: "Last",
                                    next: "Next",
                                    previous: "Previous"
                                }
                            },
                            columnDefs: [{
                                    orderable: true,
                                    targets: '_all'
                                }, // Make all columns sortable
                                {
                                    type: 'date',
                                    targets: 2
                                } // Specify date type for date column
                            ]
                        });
                    });
                </script> -->
            </div>

            <!-- View and Manage Appointments Section 📑 -->
            <div id="view-manage-appointments" class="section" style="display: none;">
                <h1 class="uk-text-bold">View & Manage Appointments</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="viewManageAppointmentsFrame" src="../Appointments/app_manage/manage_appointments.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>

            <!-- View All appointments Section 📑 -->
            <div id="view-appointments" class="section" style="display: none;">
                <h1 class="uk-text-bold">View All Appointments</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="viewAppointmentsFrame" src="../Appointments/app_manage/view_all_appointments.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
                </div>
            </div>

            <!-- Manage Timetable Settings Section 📑-->
            <div id="timetable-settings" class="section" style="display: none;">
                <h1 class="uk-text-bold">Manage Timetable Settings</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <iframe id="manageTimetableSettingsFrame" src="forAdmin/manageWebpage/timetable_settings.php" style="width: 100%; border: none;" onload="resizeIframe(this);"></iframe>
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
                <div class="uk-width-1-1 uk-margin-top">
                    <button class="uk-button uk-button-primary" uk-toggle="target: #change-password-modal">Change Password</button>
                </div>
            </div>
        </div>
    </div>
    </form>
                    <!-- Change Password Modal still -->
                        <div id="change-password-modal" uk-modal>
                            <div class="uk-modal-dialog uk-modal-body uk-overflow-auto">
                                <h2 class="uk-modal-title">Change Password</h2>

                                <div id="password-alert-container"></div>
                                
                                <form id="change-password-form" class="uk-form-stacked">
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="current-password">Current Password</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" id="current-password" type="password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="new-password">New Password</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" id="new-password" type="password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin">
                                        <label class="uk-form-label" for="confirm-password">Confirm New Password</label>
                                        <div class="uk-form-controls">
                                            <input class="uk-input" id="confirm-password" type="password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="uk-margin uk-text-right">
                                        <button class="uk-button uk-button-default uk-modal-close" type="button">Cancel</button>
                                        <button class="uk-button uk-button-primary" type="submit">Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php unset($_SESSION['update_errors']); ?>
                    <?php unset($_SESSION['update_success']); ?>
            </div>
            
            
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
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

        // Store initial values
        let originalValues = {};
        inputs.forEach(input => originalValues[input.id] = input.value);
        // Original email value to restore if verification is canceled
    let originalEmail = emailInput ? emailInput.value : '';

        // Modify Edit Button Click Event
        if (editButton) {
        editButton.addEventListener("click", function () {
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

                saveButton.textContent = "Save";
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

        // Change Password Button
    const changePasswordButton = document.querySelector('[uk-toggle="target: #change-password-modal"]');

    if (changePasswordButton) {
        // Initially disable the Change Password button
        changePasswordButton.disabled = true;
    }

                    // Change Password
    const passwordForm = document.getElementById("change-password-form");
    const passwordAlertContainer = document.getElementById("password-alert-container");

    // Prevent default form submission and save actions
    changePasswordButton.addEventListener('click', function(event) {
            // Prevent any default behavior that might submit a form or trigger save actions
            event.preventDefault();
             // Open the modal (UIkit will handle this via the uk-toggle attribute)
             UIkit.modal("#change-password-modal").show();
        });
    
    if (passwordForm) {
        // Initially disable password form inputs
        const passwordInputs = passwordForm.querySelectorAll("input");
        passwordInputs.forEach(input => input.disabled = true);

        passwordForm.addEventListener("submit", function (e) {
            e.preventDefault();
            passwordAlertContainer.innerHTML = "";

            const currentPassword = document.getElementById("current-password").value.trim();
            const newPassword = document.getElementById("new-password").value.trim();
            const confirmPassword = document.getElementById("confirm-password").value.trim();

            // Frontend validation
            if (newPassword !== confirmPassword) {
                showPasswordAlert("error", "New passwords do not match");
                return;
            }

            if (newPassword.length < 8) {
                showPasswordAlert("error", "Password must be at least 8 characters");
                return;
            }

            if (!/[A-Z]/.test(newPassword)) {
                showPasswordAlert("error", "Password must contain at least one uppercase letter");
                return;
            }

            if (!/[a-z]/.test(newPassword)) {
                showPasswordAlert("error", "Password must contain at least one lowercase letter");
                return;
            }

            if (!/[0-9]/.test(newPassword)) {
                showPasswordAlert("error", "Password must contain at least one number");
                return;
            }

            if (!/[^A-Za-z0-9]/.test(newPassword)) {
                showPasswordAlert("error", "Password must contain at least one special character");
                return;
            }

            console.log("Sending password change request");

            fetch("../Accounts/manageaccount/updateinfo.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
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
                        showPasswordAlert("success", data.success);
                        passwordForm.reset();
                        setTimeout(() => {
                            if (typeof UIkit !== "undefined") {
                                UIkit.modal("#change-password-modal").hide();
                            }
                        }, 2000);
                    } else if (data.error) {
                        showPasswordAlert("error", data.error);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    showPasswordAlert("error", "An unexpected error occurred. Please try again.");
                });
        });
    }

    function showPasswordAlert(type, message) {
        const alertClass = type === "success" ? "uk-alert-success" : "uk-alert-danger";
        passwordAlertContainer.innerHTML = `
            <div class="${alertClass}" uk-alert>
                <a class="uk-alert-close" uk-close></a>
                <p>${message}</p>
            </div>`;
    }

    // UIkit Modal 'shown' event handler for debugging
    UIkit.util.on('#change-password-modal', 'shown', function () {
        console.log("Change password modal is shown.");

        let currentPasswordInput = document.getElementById('current-password');
        console.log("currentPasswordInput:", currentPasswordInput);

        let newPasswordInput = document.getElementById('new-password');
        console.log("newPasswordInput:", newPasswordInput);

        let confirmPasswordInput = document.getElementById('confirm-password');
        console.log("confirmPasswordInput:", confirmPasswordInput);
    });


        // Initialize OTP section to be hidden
        otpSection.style.display = "none";
    });

                // Resize iframe
                function resizeIframe(iframe) {
                    iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 'px';
                }

                // Sidebar Toggle
                document.querySelector('.sidebar-toggle').addEventListener('click', function() {
                    document.querySelector('.sidebar-nav').classList.toggle('uk-open');
                });

                // Show Section
                function showSection(sectionId) {
                    document.querySelectorAll('.section').forEach(section => {
                        section.style.display = 'none';
                    });
                    const section = document.getElementById(sectionId);
                    section.style.display = 'block';

                    // Resize iframe if present in the section
                    const iframe = section.querySelector('iframe');
                    if (iframe) {
                        resizeIframe(iframe);
                    }

                    // Set active class on the sidebar link
                    document.querySelectorAll('.sidebar-nav a').forEach(link => {
                        link.parentElement.classList.remove('uk-active');
                    });
                    document.querySelector(`.sidebar-nav a[href="#${sectionId}"]`).parentElement.classList.add('uk-active');
                }

                // Preview Profile Photo
                function previewProfilePhoto(event) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        const preview = document.querySelector('.profile-preview');
                        preview.src = reader.result;
                    }
                    reader.readAsDataURL(event.target.files[0]);
                }

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

                // View and manage appointments iframe
                let viewManageAppointmentsFrame = document.getElementById('viewManageAppointmentsFrame');

                viewManageAppointmentsFrame.onload = function() {
                    resizeIframe(viewManageAppointmentsFrame);
                    let viewManageAppointmentsForm = viewManageAppointmentsFrame.contentDocument.getElementById("viewManageAppointmentsForm");

                    if (viewManageAppointmentsForm) {
                        viewManageAppointmentsForm.addEventListener('submit', function(e) {
                            e.preventDefault();

                            let formData = new FormData(this);

                            fetch("../Appointments/app_manage/manage_appointments.php", {
                                    method: 'POST',
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
                }

                // view all appointments iframe
                let viewAppointmentsFrame = document.getElementById("viewAppointmentsFrame");

                viewAppointmentsFrame.onload = function() {
                    resizeIframe(viewAppointmentsFrame);
                    let viewAppointmentsForm = viewAppointmentsFrame.contentDocument.getElementById("viewAppointmentsForm");

                    if (viewAppointmentsForm) {
                        viewAppointmentsForm.addEventListener("submit", function(e) {
                            e.preventDefault();

                            let formData = new FormData(this);

                            fetch("../Appointments/app_manage/view_all_appointments.php", {
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
</body>

</html>
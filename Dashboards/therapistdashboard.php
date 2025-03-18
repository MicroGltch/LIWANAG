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
        $profilePicture = '../uploads/profile_pictures/' . $userData['profile_picture']; // Corrected path
    } else {
        $profilePicture = '../CSS/default.jpg';
    }
    // $profilePicture = $userData['profile_picture'] ? '../uploads/' . $userData['profile_picture'] : '../CSS/default.jpg';
} else {
    echo "No Data Found.";
}


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
                    
                    <li class="uk-parent">
                        <a href="#appointments" onclick="showSection('appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Appointments</a>
                        
                        <ul class="uk-nav-sub " style="padding:5px 0px 5px 30px">
                            <li style="padding:0px 0px 15px 0px"><a href="#upcoming-appointments" onclick="showSection('upcoming-appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Upcoming Appointments </a></li>

                            <li><a href="../Appointments/app_process/rebook_appointment.php"><span class="uk-margin-small-right" uk-icon="calendar"></span> Rebook Appointment</a></li>
                        </ul>
                    </li>

                    <hr>

                    <li class="uk-parent">
                    <a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Patients</a>
                        
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

                <!-- Calendar Container -->
                <div class="calendar-container uk-flex uk-flex-row">
                    <div class="uk-width-expand">
                        <div class="dashboard-calendar-container uk-card uk-card-default uk-card-body">
                            <div class="dashboard-header uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
                                <div class="dashboard-month-selector">
                                    <select class="uk-select month-select" id="monthSelect">
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                </div>
                            </div>
                            <div id="calendar"></div>
                        </div>
                    </div>

                    <!-- Right Sidebar -->
                    <div class="uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
                        <div class="sidebar-nav">
                            <ul class="uk-nav uk-nav-default">
                                <li class="uk-nav-header">
                                    <span class="uk-margin-small-right" uk-icon="clock"></span>
                                    Pending Approval
                                </li>
                                <div class="pending-appointments">
                                    <!-- Will be populated dynamically -->
                                </div>

                                <li class="uk-nav-header uk-margin-top">
                                    <span class="uk-margin-small-right" uk-icon="calendar"></span>
                                    Upcoming
                                </li>
                                <div class="upcoming-appointments">
                                    <!-- Will be populated dynamically -->
                                </div>
                            </ul>
                        </div>
                    </div>
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
            <div id="account-details" style="display: none;" class="section">
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
                                    <button type="button" class="uk-button uk-button-primary uk-margin-small-bottom" id="uploadButton">
                                        Upload Photo
                                    </button>
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
       value="<?= htmlspecialchars($_SESSION['phoneNumber'] ?? $phoneNumber, ENT_QUOTES, 'UTF-8') ?>"  >
                        <small style="color: red;" class="error-message" data-error="phoneNumber"></small>
                        </div>
                        <small style="color: red;" class="error-message" data-error="duplicate"></small>
                        <small style="color: green;" class="error-message" id="successMessage"></small>
                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button class="uk-button uk-button-primary" type="submit">Save Changes</button>
                        </div>
                    </form>
                    <?php unset($_SESSION['update_errors']); // Clear errors after displaying ?>
                    <?php unset($_SESSION['update_success']); // Clear success message ?>
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


    document.addEventListener("DOMContentLoaded", function() {
        let profileUploadInput = document.getElementById("profileUpload");
        let uploadButton = document.getElementById("uploadButton");


        // Click event to open file dialog
        uploadButton.addEventListener("click", function() {
            profileUploadInput.click();
        });


        // Auto-upload when a file is selected
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
                        document.querySelector(".profile-preview").src = data.imagePath; // Update profile image
                    } else {
                        alert("Error: " + data.error);
                    }
                })
                .catch(error => console.error("Error:", error));
        });
    });


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
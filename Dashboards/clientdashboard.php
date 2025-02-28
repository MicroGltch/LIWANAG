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
        $profilePicture = '../Accounts/images/profilepictures/' . $userData['profile_picture']; // Corrected path
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

$connection->close();
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
                            <li style="display: flex; align-items: center;">  <?php echo $_SESSION['username'];?>
                            </li>
                            <li><a href="../Accounts/logout.php">Logout</a></li>
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
                    
                    <!-- Temporary links lng based from Rap's code pra macustomize sa client dashboard-->
                    <li><a href="../Appointments/patient/frontend/register_patient_form.php"><span class="uk-margin-small-right" uk-icon="user"></span> Register Patient</a></li>
                    <li><a href="../Appointments/patient/frontend/edit_patient_form.php"><span class="uk-margin-small-right" uk-icon="user"></span> View Registered Patients</a></li> 
                    <li><a href="../Appointments/frontend/book_appointment_form.php"><span class="uk-margin-small-right" uk-icon="user"></span> Book Appointment</a></li> 

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
                                                Edit (<?= 2 - $appointment['edit_count']; ?> left)
                                            </button>
                                        <?php else: ?>
                                            <button class="uk-button uk-button-default" disabled>Editing Not Allowed</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
                    <button type="submit" class="uk-button uk-button-primary uk-margin-top">Upload</button>
                </form>
            </div>

            <div class="uk-card uk-card-default uk-card-body">
                <h3 class="uk-card-title uk-text-bold">User Details</h3>
                <form action="../Accounts/manageaccount/updateinfo.php" method="post" class="uk-grid-small" uk-grid>
                    <input type="hidden" name="action" value="update_user_details">
                    <div class="uk-width-1-2@s">
                        <label class="uk-form-label">First Name</label>
                        <input class="uk-input" type="text" name="firstName" value="<?php echo $firstName; ?>">
                    </div>
                    <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Last Name</label>
                        <input class="uk-input" type="text" name="lastName" value="<?php echo $lastName; ?>">
                    </div>
                    <div class="uk-width-1-1">
                        <label class="uk-form-label">Email</label>
                        <input class="uk-input" type="email" name="email" value="<?php echo $email; ?>">
                    </div>
                    <div class="uk-width-1-1">
                        <label class="uk-form-label">Phone Number</label>
                        <input class="uk-input" type="tel" name="phoneNumber" value="<?php echo $phoneNumber; ?>">
                    </div>
                    <div class="uk-width-1-1 uk-text-right uk-margin-top">
                        <button class="uk-button uk-button-primary" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>

    </div>

</body>

<script>

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

        document.addEventListener("DOMContentLoaded", function () {
            // ✅ Cancel Appointment
            document.querySelectorAll(".cancel-btn").forEach(button => {
                button.addEventListener("click", function () {
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
                                    headers: { "Content-Type": "application/json" },
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
            button.addEventListener("click", function () {
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
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ appointment_id: appointmentId, action: "edit", new_date: result.value.newDate, new_time: result.value.newTime })
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire(data.title, data.message, data.status === "success" ? "success" : "error")
                            .then(() => location.reload());
                    });
                });
            });
        });
    });
</script>

</html>
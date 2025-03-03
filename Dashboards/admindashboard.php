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
                    <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Account Details</a></li>
                    <li><a href="#settings" onclick="showSection('settings')"><span class="uk-margin-small-right" uk-icon="cog"></span> Settings</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
        <div id="appointments" class="section">
                <h1 class="uk-text-bold">Appoinments</h1>
                <p>Appointment table will be displayed here.</p>

                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <table id="appointmentsTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th >Date</th>
                                <th >Time</th>
                                <th >Service</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- population area -->
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
                    <form id="settingsvalidate" action="../Accounts/manageaccount/updateinfo.php" method="post" class="uk-grid-small" uk-grid>
                    <input type="hidden" name="action" value="update_user_details">
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" name="firstName" id="firstName" value="<?php echo $firstName; ?>">
                        <span class="error" id="firstNameError" style="color: red;"></span>
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" name="lastName" id="lastName" value="<?php echo $lastName; ?>">
                        <span class="error" id="lastNameError" style="color: red;"></span>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" name="email" id="email" value="<?php echo $email; ?>">
                        <span class="error" id="emailError" style="color: red;"></span>
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel" name="phoneNumber" id="mobileNumber"  value="<?php echo $phoneNumber; ?>" pattern="^\+63\d{10}$"
                        required>
                        <span class="error" id="mobileNumberError" style="color: red;"></span>
                        </div>
                        <?php
                if (isset($_SESSION['signup_error'])) {
                    echo "<div class='uk-alert-danger' uk-alert>
                            <a class='uk-alert-close' uk-close></a>
                            <p>" . $_SESSION['signup_error'] . "</p>
                          </div>";
                    unset($_SESSION['signup_error']); // Remove the message after displaying it
                }
            ?>
                        <div class="uk-width-1-1 uk-text-right uk-margin-top">
                            <button class="uk-button uk-button-primary" type="submit">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- Javascript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- <script src="accountJS/settings.js"></script> -->

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

        //Settings Validation *Ayaw maging external* (settings.js)
document.addEventListener("DOMContentLoaded", function () {
    // Attach a blur event listener to reformat the mobile number when focus is lost.
    let mobileNumberInput = document.getElementById("mobileNumber");
    mobileNumberInput.addEventListener("blur", function() {
        let phone = this.value.trim();
        if (phone.length > 0) {
            // If it starts with "0", replace it with "+63"
            if (phone.startsWith("0")) {
                phone = "+63" + phone.substring(1);
            } else if (!phone.startsWith("+63")) {
                // Otherwise, if it doesn't already start with +63, prepend +63.
                phone = "+63" + phone;
            }
            this.value = phone;
        }
    });


    document.getElementById("settingsvalidate").addEventListener("submit", function (event) {
        let valid = true;


        // First Name Validation
        let firstName = document.getElementById("firstName").value.trim();
        let firstNameError = document.getElementById("firstNameError");
        let nameRegex = /^[A-Za-z ]{2,30}$/;
        if (!nameRegex.test(firstName)) {
            firstNameError.textContent = "Only letters allowed (2-30 characters).";
            valid = false;
        } else {
            firstNameError.textContent = "";
        }


        // Last Name Validation
        let lastName = document.getElementById("lastName").value.trim();
        let lastNameError = document.getElementById("lastNameError");
        if (!nameRegex.test(lastName)) {
            lastNameError.textContent = "Only letters allowed (2-30 characters).";
            valid = false;
        } else {
            lastNameError.textContent = "";
        }


        // Email Validation
        let email = document.getElementById("email").value.trim();
        let emailError = document.getElementById("emailError");
        let emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!emailRegex.test(email)) {
            emailError.textContent = "Invalid email format.";
            valid = false;
        } else {
            emailError.textContent = "";
        }


        // Mobile Number Validation
        let mobileNumber = mobileNumberInput.value.trim();
        let mobileNumberError = document.getElementById("mobileNumberError");


        // Reformat the mobile number if necessary
        if (mobileNumber.length > 0) {
            if (mobileNumber.startsWith("0")) {
                mobileNumber = "+63" + mobileNumber.substring(1);
                mobileNumberInput.value = mobileNumber;
            } else if (!mobileNumber.startsWith("+63")) {
                mobileNumber = "+63" + mobileNumber;
                mobileNumberInput.value = mobileNumber;
            }
        }


        // Validate: must be "+63" followed by exactly 10 digits.
        let mobileRegex = /^\+63\d{10}$/;
        if (!mobileRegex.test(mobileNumber)) {
            mobileNumberError.textContent = "Phone number must be in the format +63XXXXXXXXXX.";
            valid = false;
        } else {
            mobileNumberError.textContent = "";
        }
       
        if (!valid) {
            event.preventDefault();
            return false;
        }
    });
});

    </script>

</html>
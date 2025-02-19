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
                        <a href="#" class="uk-navbar-item">
                            <img class="profile-image" src="../CSS/default.jpg" alt="Profile Image" uk-img>
                        </a>
                        <li><a href="#">Logout</a></li>
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
                        <input class="uk-input" type="text" placeholder="Placeholder">
                    </div>
                    <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Last Name</label>
                        <input class="uk-input" type="text" placeholder="Placeholder">
                    </div>
                    <div class="uk-width-1-1">
                        <label class="uk-form-label">Email</label>
                        <input class="uk-input" type="email" placeholder="Placeholder">
                    </div>
                    <div class="uk-width-1-1">
                        <label class="uk-form-label">Phone Number</label>
                        <input class="uk-input" type="tel" placeholder="Placeholder">
                    </div>
                </form>
            </div>
        </div>
    
              
            <!-- Settings -->
            <div id="settings" class="section" style="display: none;">
                <h1 class="uk-text-bold">Settings</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <div class="uk-flex uk-flex-middle">
                        <div class="profile-upload-container">
                            <img class="uk-border-circle profile-preview" src="../CSS/default.jpg" alt="Profile Photo">
                            <div class="uk-flex uk-flex-column uk-margin-left">
                                <input type="file" id="profileUpload" class="uk-hidden">
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
                </div>
                <div class="uk-card uk-card-default uk-card-body">
                    <h3 class="uk-card-title uk-text-bold">User Details</h3>
                    <form class="uk-grid-small" uk-grid>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">First Name</label>
                            <input class="uk-input" type="text" placeholder="Placeholder">
                        </div>
                        <div class="uk-width-1-2@s">
                            <label class="uk-form-label">Last Name</label>
                            <input class="uk-input" type="text" placeholder="Placeholder">
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Email</label>
                            <input class="uk-input" type="email" placeholder="Placeholder">
                        </div>
                        <div class="uk-width-1-1">
                            <label class="uk-form-label">Phone Number</label>
                            <input class="uk-input" type="tel" placeholder="Placeholder">
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
    </script>

</html>
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

                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
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
                    <li>Admin</li>
                    <li><a href="#appointments" onclick="showSection('appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Appointments</a></li>

                    <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Accounts</a></li>

                    <li><a href="#system-analytics" onclick="showSection('system-analytics')"><span class="uk-margin-small-right" uk-icon="database"></span> System Analytics</a></li>


                    <li><a href="#manage-website" onclick="showSection('manage-website')"><span class="uk-margin-small-right" uk-icon="file-edit"></span> Manage Website Contents</a></li>


                    <li><a href="#settings" onclick="showSection('settings')"><span class="uk-margin-small-right" uk-icon="cog"></span> Settings</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <div id="appointments" class="section">

                <!-- Appoinments -->
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
                                <th>Waitlist</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- population area -->
                        </tbody>
                    </table>
                </div>

            </div>



            <!-- Accounts -->
            <div id="account-details" class="section" style="display: none;">
                <h1 class="uk-text-bold">Accounts</h1>
                <div>
                    <button class="uk-button uk-button-primary" id="btnPatient" style="margin:0px 10px 0px 10px">Patient</button>
                    <button class="uk-button uk-button-primary" id="btnClient" style="margin:0px 10px 0px 10px">Client</button>
                    <button class="uk-button uk-button-primary" id="btnTherapist" style="margin:0px 10px 0px 10px">Therapist</button>

                    <button class="uk-button" id="btnAddAccount" style="margin:0px 10px 0px 10px" >Add Account</button>
                </div>

                <div class="patient-accounts uk-card uk-card-default uk-card-body uk-margin">
                    <table id="patientTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Service Type</th>
                                <th>Assigned Therapist</th>
                                <th>Guardian Name</th>
                                <th>Edit</th>
                                <th>Archive</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- population area -->
                        </tbody>
                    </table>
                </div>

                <div class="client-accounts uk-card uk-card-default uk-card-body uk-margin" style="display: none;">
                    <table id="clientTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Child</th>
                                <th>Edit</th>
                                <th>Archive</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- population area -->
                        </tbody>
                    </table>
                </div>

                <div class="therapist-accounts uk-card uk-card-default uk-card-body uk-margin" style="display: none;">
                    <table id="therapistTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Employee Type</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Edit</th>
                                <th>Archive</th>

                            </tr>
                        </thead>
                        <tbody>
                            <!-- population area -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Settings -->
            <div id="system-analytics" class="section" style="display: none;">
                <h1 class="uk-text-bold">System Analytics</h1>
                <p>System Analytics will be displayed here.</p>

                <div class="uk-card uk-card-default uk-card-body uk-margin">

                </div>

            </div>

            <!-- Manage Website Contents -->
            <div id="manage-website" class="section" style="display: none;">
                <h1 class="uk-text-bold">Manage Website Contents</h1>
                <p>Manage Website will be displayed here.</p>

                <div class="uk-card uk-card-default uk-card-body uk-margin">

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

    document.getElementById('btnPatient').addEventListener('click', function() {
        document.querySelector('.patient-accounts').style.display = 'block';
        document.querySelector('.client-accounts').style.display = 'none';
        document.querySelector('.therapist-accounts').style.display = 'none';
    });

    document.getElementById('btnClient').addEventListener('click', function() {
        document.querySelector('.client-accounts').style.display = 'block';
        document.querySelector('.patient-accounts').style.display = 'none';
        document.querySelector('.therapist-accounts').style.display = 'none';
    });

    document.getElementById('btnTherapist').addEventListener('click', function() {
        document.querySelector('.therapist-accounts').style.display = 'block';
        document.querySelector('.client-accounts').style.display = 'none';
        document.querySelector('.patient-accounts').style.display = 'none';
    });
</script>

</html>
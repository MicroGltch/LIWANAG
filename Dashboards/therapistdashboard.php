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
    <link rel="stylesheet" href="CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="CSS/style.css" type="text/css" />

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.uikit.min.js"></script>

    <!-- FullCalendar Library -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.js'></script>
</head>

<body>
    <!-- Navbar -->
    <nav class="uk-navbar-container logged-in">
        <div class="uk-container">
            <div uk-navbar>

                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="hompage.php">Little Wanderer's Therapy Center</a>
                </div>
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <a href="#" class="uk-navbar-item">
                            <img class="profile-image" src="CSS/default.jpg" alt="Profile Image" uk-img>
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
                    <li><a href="#dashboard" onclick="showSection('dashboard')"><span class="uk-margin-small-right" uk-icon="home"></span> Dashboard</a></li>
                    <li><a href="#appointments" onclick="showSection('appointments')"><span class="uk-margin-small-right" uk-icon="calendar"></span> Appointments</a></li>
                    <li><a href="#account-details" onclick="showSection('account-details')"><span class="uk-margin-small-right" uk-icon="user"></span> Patients</a></li>
                    <li><a href="#settings" onclick="showSection('settings')"><span class="uk-margin-small-right" uk-icon="cog"></span> Settings</a></li>
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


            <!-- Settings -->
            <div id="settings" class="section" style="display: none;">
                <h1 class="uk-text-bold">Settings</h1>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                    <div class="uk-flex uk-flex-middle">
                        <div class="profile-upload-container">
                            <img class="uk-border-circle profile-preview" src="CSS/default.jpg" alt="Profile Photo">
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
        
            <div id="tablePagination" class="uk-margin uk-flex uk-flex-center"></div> <!-- Pagination -->
        </div>
    </div>

    </div>

</body>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Populate month select
        const monthSelect = document.getElementById('monthSelect');
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
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
        if(sectionId === 'dashboard') {
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

    function removeProfilePhoto() {
        document.querySelector('.profile-preview').src = 'CSS/default.jpg';
    }

    $(document).ready(function() {
            $('#patientTable').DataTable();
        });


        $(document).ready(function() {
            $('#appointmentsTable').DataTable({
                columnDefs: [
                    {
                        targets: -1, // targets the last column (Actions)
                        data: null,
                        defaultContent: '<button class="uk-button uk-button-danger uk-button-small">Cancel</button>'
                    }
                ]
            });

            // cancel button
            $('#appointmentsTable tbody').on('click', 'button', function () {
                var data = $('#appointmentsTable').DataTable().row($(this).parents('tr')).data();
                alert('Cancel appointment for ' + data[2] + '?'); 
                // cancellation logic here
            });
        });
</script>

</html>
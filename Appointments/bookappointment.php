<!-- from frontend-manage-account -->

<!DOCTYPE html>

<head>
    <meta name="viewport" content="width=device-width" />

    <title>BOOK APPOINMENT</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- Flatpickr Datepicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>



    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../CSS/style.css" type="text/css" />


</head>

<body>
    <!-- Nav Bar (Ayusin pa alignment n stuff) -->
    <nav class="uk-navbar-container logged-out">
        <div class="uk-container">
            <div uk-navbar>
                <!--Navbar Left-->
                <div class="uk-navbar-left">
                    <ul class="uk-navbar-nav">
                        <li class="uk-active"><a href="#">About Us</a></li>
                        <li class="uk-active"><a href="#">FAQs</a></li>
                        <li class="uk-active"><a href="#">Services</a></li>
                    </ul>
                </div>

                <!--Navbar Center-->
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="hompage.php">Little Wanderer's Therapy Center</a>
                </div>

                <!--Navbar Right-->
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li><a href="signuppage.php">Sign Up to Book an Appointment</a></li>
                        <li><a href="loginpage.php">Login</a></li>
                    </ul>

                </div>

            </div>

        </div>
        </div>
    </nav>

    <div class="body-create-acc uk-flex uk-flex-center uk-flex-middle ">
        <!-- Create Account Card -->
        <div class="register-patient-card uk-card uk-card-default uk-card-body form-card">

            <!-- Title and Helper -->
            <h3 class="uk-card-title uk-flex uk-flex-center">Register the patient</h3>
            <p class="uk-flex uk-flex-center">Enter the patient's personal details to start your journey with us.</p>

            <!-- Form Fields -->
            <form class="uk-form-stacked uk-grid-medium" uk-grid>

                <!-- psa.use uk-margin to automatically add top and bottom margin -->

                <!-- First Name -->
                <div class="uk-width-1@s uk-width-1-2@l ">
                    <label class="uk-form-label" for="form-stacked-text">First Name</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="form-stacked-text" type="text" placeholder="Input your First Name...">
                    </div>
                </div>

                <!-- Last Name -->
                <div class="uk-width-1@s uk-width-1-2@l">
                    <label class="uk-form-label" for="form-stacked-text">Last Name</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="form-stacked-text" type="text" placeholder="Input your Last Name...">
                    </div>
                </div>

                <!-- Email -->
                <div class="uk-width-1@s uk-width-1-2@l">
                    <label class="uk-form-label" for="form-stacked-text">Birthday</label>

                    <div class="uk-form-controls">
                        <input class="uk-input" type="text" id="datepicker" placeholder="Select Date">
                    </div>
                </div>

                <!-- Password -->
                <div class="uk-width-1@s uk-width-1-2@l">
                    <label class="uk-form-label" for="form-stacked-text">Gender</label>
                    <div class="uk-form-controls">
                        <select class="uk-select" id="form-stacked-select">
                            <option>Select an option</option>
                            <option>Male</option>
                            <option>Female</option>
                        </select>
                    </div>
                </div>

                <!-- Address -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="form-stacked-text">Address</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="form-stacked-text" type="text" placeholder="Input your Address...">
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="uk-width-1@s uk-width-1-3@l">
                    <label class="uk-form-label" for="form-stacked-text">Choose a service</label>
                    <div class="uk-form-controls">
                        <select class="uk-select" id="form-stacked-select">
                            <option>Select an option</option>
                            <option>Initial Evaluation</option>
                            <option>Behavioral Therapy</option>
                            <option>Speech Therapy</option>
                            <option>Playgroup</option>
                        </select>
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="uk-width-1@s uk-width-1-3@l">
                <div class="uk-margin">
                <label class="uk-form-label">Choose a Date</label>
                <div class="uk-form-controls">
                    <input class="uk-input" type="text" id="datepicker" placeholder="Select Date">
                </div>
            </div>
                </div>

                <div class="uk-width-1@s uk-width-1-3@l">
                <div class="uk-margin">
                <label class="uk-form-label">Choose a Time</label>
                <div class="uk-form-controls">
                    <select class="uk-select">
                        <option>Select an option...</option>
                        <option>10AM - 11AM</option>
                        <option>11AM - 12PM</option>
                        <option>12PM - 1PM</option>
                        <option>1PM - 2PM</option>
                        <option>2PM - 3PM</option>
                        <option>3PM - 4PM</option>
                        <option>4PM - 5PM</option>
                        <option>5PM - 6PM</option>
                        <option>6PM - 7PM</option>
                    </select>
                </div>
</div>
</div>
                <!-- Divider -->
                <div class="uk-width-1@s uk-width-1@l">
                    <hr>
                </div>

                <!-- Doctor's Referral -->

                <!-- Front Page -->
                <div class="uk-width-1@s uk-width-1-2@l">
                    <div class="js-upload uk-placeholder uk-text-center">
                        <label class="uk-form-label">Front Page</label>
                        <span uk-icon="icon: cloud-upload"></span>
                        <span class="uk-text-middle">Drag and drop a file or</span>
                        <div uk-form-custom>
                            <input type="file" multiple>
                            <span class="uk-link">Browse</span>
                            <span class="uk-text-middle">to choose a file</span>
                        </div>
                    </div>
                </div>
                <progress id="js-progressbar" class="uk-progress" value="0" max="100" hidden></progress>

                <div class="uk-width-1@s uk-width-1-2@l">
                    <!-- Recommendation Page -->
                    <div class="js-upload uk-placeholder uk-text-center">
                        <label class="uk-form-label">Back Page</label>
                        <span uk-icon="icon: cloud-upload"></span>
                        <span class="uk-text-middle">Drag and drop a file or</span>
                        <div uk-form-custom>
                            <input type="file" multiple>
                            <span class="uk-link">Browse</span>
                            <span class="uk-text-middle">to choose a file</span>
                        </div>
                    </div>
                </div>

                <progress id="js-progressbar" class="uk-progress" value="0" max="100" hidden></progress>

                <!-- Sign Up Button -->
                <div class="signup-btn-div uk-width-1@s uk-width-1@l">
                    <button class="uk-button uk-button-primary uk-width-1@s uk-width-1@l">Book Appoinment</button>
                </div>



            </form>


        </div>

    </div>

    </div>

    <!-- Footer -->
    <footer class="footer">
        <p class="footer-text">
            LIWANAG in construction, everything is subject to change.
        </p>
    </footer>

    <script>
        // Initialize Flatpickr for the datepicker
        flatpickr("#datepicker", {
            enableTime: false,
            dateFormat: "Y-m-d"
        });

        
    </script>
</body>


</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width" />
    
    <title>LIWANAG - LOGIN</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="CSS/style.css" type="text/css" />

</head>

<body>
    <!-- Nav Bar -->
    <nav class="uk-navbar-container logged-out">
        <div class="uk-container">
            <div uk-navbar>
                <!-- Navbar Left -->
                <div class="uk-navbar-left">
                    <ul class="uk-navbar-nav">
                        <li class="uk-active"><a href="#">About Us</a></li>
                        <li class="uk-active"><a href="#">FAQs</a></li>
                        <li class="uk-active"><a href="#">Services</a></li>
                    </ul>
                </div>

                <!-- Navbar Center -->
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo" href="hompage.php">Little Wanderer's Therapy Center</a>
                </div>

                <!-- Navbar Right -->
                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav">
                        <li><a href="signuppage.php">Sign Up to Book an Appointment</a></li>
                        <li><a href="loginpage.php">Login</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="uk-flex uk-flex-center uk-flex-middle uk-height-viewport">
        <!-- Login Account Card -->
        <div class="create-acc-card uk-card uk-card-default uk-card-body uk-width-1-2 form-card">
            
            <!-- Title and Helper -->
            <h3 class="uk-card-title uk-flex uk-flex-center">Forgot Password</h3>
            <p class="uk-flex uk-flex-center">To recover your password, kindly provide your email address.</p>
            
            <!-- Form Fields -->
            <form class="uk-form-stacked uk-grid-medium" uk-grid id="forgotPasswordForm">

                <!-- Email -->
                <div class="uk-width-1-1@s uk-width-1-1@l">
                    <label class="uk-form-label" for="form-stacked-text">Email</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="email" type="email" placeholder="Input your Email..." required>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="login-btn-div uk-width-1-1@s uk-width-1-1@l">
                    <button type="submit" id="submitBtn" class="uk-button uk-button-primary uk-width-1-1@s uk-width-1-1@l">Submit</button>
                </div>

            </form>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p class="footer-text">
            LIWANAG in construction, everything is subject to change.
        </p>
    </footer>

    <script>
        document.getElementById("forgotPasswordForm").addEventListener("submit", function(event) {
            event.preventDefault(); // Prevent form from actually submitting

            UIkit.notification({
                message: 'Your request has been sent successfully!',
                status: 'success',
                pos: 'top-center',
                timeout: 5000
            });

            // Optional: Reset the form after showing the notification
            document.getElementById("forgotPasswordForm").reset();
        });
    </script>

</body>
</html>

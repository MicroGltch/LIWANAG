<?php
session_start();
include "../dbconfig.php"
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width" />
    
    <title>LIWANAG - SIGN UP</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../CSS/style.css" type="text/css"/>
    
    
</head>

<body>

    <!-- Nav Bar (Ayusin pa alignment n stuff) -->
    <nav class="uk-navbar-container">
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
                        <a class="uk-navbar-item uk-logo" href="../homepage.php">Little Wanderer's Therapy Center</a>
                    </div>

                <!--Navbar Right-->
                    <div class="uk-navbar-right">
                        <ul class="uk-navbar-nav">
                             <li><a href="signuppage.php">Sign Up to Book an Appointment</a></li>
                            <li><a href="loginpage.php">Login</a></li>
                        </ul>

                        <!-- Buttons ver but need ayusin responsiveness eme so imma leave as comment
                        <div class="uk-navbar-item">
                                <button class="uk-button uk-button-default">Sign Up to Book an Appointment</button>
                                <button class="uk-button uk-button-secondary">Login</button>
                        </div>-->
                    </div>
    
                </div>
    
            </div>
        </div>
    </nav>

    <?php include "../dbconfig.php"; ?>
    <div class="body-create-acc uk-flex uk-flex-center uk-flex-middle">
    <!-- Create Account Card -->
    <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
        <!-- Title and Helper -->
        <h3 class="uk-card-title uk-flex uk-flex-center">Create an Account</h3>
        <p class="uk-flex uk-flex-center">Enter your personal details to start your journey with us.</p>

        <!-- Form Fields -->
        <form id="signupvalidate" class="uk-form-stacked uk-grid-medium" uk-grid method="POST" action="signupverify/signupprocess.php">
            <?php
                if (isset($_SESSION['signup_error'])) {
                    echo "<div class='uk-alert-danger' uk-alert>
                            <a class='uk-alert-close' uk-close></a>
                            <p>" . $_SESSION['signup_error'] . "</p>
                          </div>";
                    unset($_SESSION['signup_error']); // Remove the message after displaying it
                }
            ?>
            <!-- First Name -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label uk-text-left" for="firstName">First Name</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="firstName" name="fname" type="text" placeholder="Input your First Name..." value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>">
                    <span class="error" id="firstNameError" style="color: red;"></span>
                </div>
            </div>

            <!-- Last Name -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label uk-text-left" for="lastName">Last Name</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="lastName" name="lname" type="text" placeholder="Input your Last Name..." value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>">
                    <span class="error" id="lastNameError" style="color: red;"></span>
                </div>
            </div>

            <!-- Email -->
            <div class="uk-width-1@s uk-width-1@l">
                <label class="uk-form-label uk-text-left" for="email">Email</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="email" name="email" type="email" placeholder="Input your Email..." value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <span class="error" id="emailError" style="color: red;"></span>
                </div>
            </div>

            <!-- Password Field -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label uk-text-left" for="password">Password</label>
                <div class="uk-form-controls">
                    <div style="position: relative; display: flex; align-items: center;">
                        <input class="uk-input password-input" id="password" name="password" type="password" 
                            maxlength="20" minlength="8" placeholder="Input your Password..." 
                            style="width: 100%; padding-right: 40px;">
                        <span style="position: absolute; right: 10px; cursor: pointer;" onclick="togglePassword()">
                            <i class="fa fa-eye" id="togglePasswordIcon"></i>
                        </span>
                    </div>
                </div>
                <span class="error" id="passwordError" style="color: red;"></span>
            </div>

            <!-- Confirm Password Field -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label uk-text-left" for="confirmPassword">Confirm Password</label>
                <div class="uk-form-controls">
                    <div style="position: relative; display: flex; align-items: center;">
                        <input class="uk-input password-input" id="confirmPassword" name="confirmPassword" type="password" 
                            maxlength="20" minlength="8" placeholder="Confirm your Password..." 
                            style="width: 100%; padding-right: 40px;">
                        <span style="position: absolute; right: 10px; cursor: pointer;" onclick="toggleConfirmPassword()">
                            <i class="fa fa-eye" id="toggleConfirmPasswordIcon"></i>
                        </span>
                    </div>
                </div>
                <span class="error" id="confirmPasswordError" style="color: red;"></span>
            </div>

            <!-- Address -->
            <div class="uk-width-1@s uk-width-1@l">
                <label class="uk-form-label uk-text-left" for="address">Address</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="address" name="address" type="text" placeholder="Input your Address..." value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                    <span class="error" id="addressError" style="color: red;"></span>
                </div>
            </div>   

                <!-- Phone Number -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label uk-text-left" for="mobileNumber">Phone Number</label>
                    <div class="uk-form-controls">
                        <input 
                            class="uk-input" 
                            id="mobileNumber" 
                            name="phone" 
                            type="tel" 
                            placeholder="Input your Phone Number..." 
                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                            pattern="^\+63\d{10}$" 
                            required>
                        <span class="error" id="mobileNumberError" style="color: red;"></span>
                    </div>
                </div>

                <!-- Sign Up Button -->
                <div class="signup-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="signup" class="uk-button uk-button-primary uk-width-1@s uk-width-1@l">Sign Up</button>
                </div>

                <!-- Divider -->
                <div class="uk-width-1@s uk-width-1@l">
                    <hr>
                </div>
                
                <!-- Login Redirect -->
                <div class="uk-flex uk-flex-middle uk-flex-center uk-width-1@s uk-width-1@l">
                    <p class="login-redirect-txt uk-flex uk-flex-middle uk-flex-center">Already have an account? &nbsp; <a href="loginpage.php"> Login here!</a> </p>
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

    <!-- Javascript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="accountJS/signup.js"></script>
    

</body>

</html>




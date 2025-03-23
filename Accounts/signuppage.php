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

                <!--Navbar Center-->
                    <div class="uk-navbar-center">
                        <a class="logo-navbar uk-navbar-item uk-logo" href="../homepage.php">Little Wanderer's Therapy Center</a>
                    </div>

                <!--Navbar Right-->
                    <div class="uk-navbar-right">
                        <ul class="uk-navbar-nav">
                            <li></li>
                            <li><a href="#"></a></li>
                        </ul>

                    </div>
    
                </div>
    
            </div>
        </div>
    </nav>

    <?php include "../dbconfig.php"; ?>
    <div id="signup-div" class=" body-create-acc uk-flex uk-flex-center uk-flex-middle" >
    <!-- Create Account Card -->
    <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
        <!-- Title and Helper -->
        <h3 class="create-acc-title uk-card-title uk-flex uk-flex-center">Create an Account</h3>
        <p class="create-acc-helper uk-flex uk-flex-center">Enter your personal details to start your journey with us.</p>

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
                            >
                        <span class="error" id="mobileNumberError" style="color: red;"></span>
                    </div>
                </div>

                <!-- Sign Up Button -->
                <div class="signup-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="signup" class="signup-btn uk-button uk-button-primary uk-width-1@s uk-width-1@l" style="border-radius: 15px">Sign Up</button>
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
    <footer class="footer uk-section uk-section-small uk-background-secondary uk-light">
    <div class="footer uk-container">

        <div class="uk-grid-match uk-child-width-1-2@m" uk-grid>
            
        <div style="text-align: left ; ">
                <h4 style="margin-bottom: 7px;">Little Wanderer's Therapy Center</h4>
                <p style="margin-top: 0px;font-size: 13px;">Welcome to Little Wanderer Therapy Center! We guarantee quality service that your child and family need.</p>
                <div>
                    <a href="#" class="uk-icon-button uk-margin-small-right" uk-icon="facebook"></a>

                </div>
            </div>

        
            <div style="text-align: right ;">
                <ul class="uk-list uk-list">
                    <li style="font-size:13px;">
                        <span uk-icon="location" ></span>
                        Benrosi V, 9746 Kamagong, Village, Makati, 1203 Kalakhang Maynila, Philippines
                    </li>
                    <li style="font-size:13px;">
                        <span uk-icon="receiver" ></span>
                        09274492970
                    </li>
                    <li style="font-size:13px;">
                        <span uk-icon="mail" ></span>
                        <a href="mailto:liwanag@company.com" class="uk-link-text">liwanag@company.com</a>
                    </li>
                </ul>
            </div>
            

        </div>
    </div>
</footer>

    <!-- Javascript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function togglePassword() {
        const passwordField = document.getElementById("password");
        const togglePasswordBtn = document.getElementById("togglePasswordIcon");

        if (passwordField.type === "password") {
            passwordField.type = "text";
            togglePasswordBtn.classList.remove("fa-eye");
            togglePasswordBtn.classList.add("fa-eye-slash");
        } else {
            passwordField.type = "password";
            togglePasswordBtn.classList.remove("fa-eye-slash");
            togglePasswordBtn.classList.add("fa-eye");
        }
    }

    function toggleConfirmPassword() {
        const confirmPasswordField = document.getElementById("confirmPassword");
        const toggleConfirmPasswordBtn = document.getElementById("toggleConfirmPasswordIcon");

        if (confirmPasswordField.type === "password") {
            confirmPasswordField.type = "text";
            toggleConfirmPasswordBtn.classList.remove("fa-eye");
            toggleConfirmPasswordBtn.classList.add("fa-eye-slash");
        } else {
            confirmPasswordField.type = "password";
            toggleConfirmPasswordBtn.classList.remove("fa-eye-slash");
            toggleConfirmPasswordBtn.classList.add("fa-eye");
        }
    }

    

    document.addEventListener("DOMContentLoaded", function () {

document.getElementById("signupvalidate").addEventListener("submit", function (event) {
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

    // Password Validation
    let password = document.getElementById("password").value;
    let passwordError = document.getElementById("passwordError");
    let passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\-_])[A-Za-z\d@$!%*?&\-_]{8,20}$/;
    if (!passwordRegex.test(password)) {
        passwordError.textContent = "Password must be 8-20 chars, with uppercase, lowercase, number, and special char.";
        valid = false;
    } else {
        passwordError.textContent = "";
    }

    // Confirm Password Validation
    let confirmPassword = document.getElementById("confirmPassword").value;
    let confirmPasswordError = document.getElementById("confirmPasswordError");
    if (confirmPassword !== password) {
        confirmPasswordError.textContent = "Passwords do not match.";
        valid = false;
    } else {
        confirmPasswordError.textContent = "";
    }

    // Mobile Number Validation
    let mobileNumber = document.getElementById("mobileNumber").value.trim();
    let mobileNumberError = document.getElementById("mobileNumberError");

    // Validate: must be 11 digits, and start with 09
    let mobileRegex = /^09\d{9}$/;
    if (!mobileRegex.test(mobileNumber)) {
        if (mobileNumber.length !== 11) {
            mobileNumberError.textContent = "Phone number must be exactly 11 digits.";
        } else if (!mobileNumber.startsWith("09")) {
            mobileNumberError.textContent = "Phone number must start with 09.";
        } else if (!/^\d+$/.test(mobileNumber)) {
            mobileNumberError.textContent = "Phone number must contain only digits.";
        } else {
            mobileNumberError.textContent = "Invalid phone number format. Must be 09XXXXXXXXX.";
        }
        valid = false;
    } else {
        mobileNumberError.textContent = "";
    }

    // Address Validation
    let address = document.getElementById("address").value;
    let addressError = document.getElementById("addressError");
    if (address.length < 5) {
        addressError.textContent = "Address must be at least 5 characters.";
        valid = false;
    } else {
        addressError.textContent = "";
    }

    // Email Duplication Check (Client-Side)
    if (valid) { // Only perform email check if other validations pass
        fetch('check_email.php', { // Create a PHP file called check_email.php
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                emailError.textContent = "The email you entered is already registered. Please use a different email.";
                valid = false;
            }

            if (!valid) {
                event.preventDefault();
                return false;
            } else {
                // If all validations pass, submit the form
                document.getElementById("signupvalidate").submit();
            }
        })
        .catch(error => {
            valid = false;
            event.preventDefault();
            return false;
        });
    } else {
        event.preventDefault();
        return false;
    }
});
});

    </script>

</body>

</html>




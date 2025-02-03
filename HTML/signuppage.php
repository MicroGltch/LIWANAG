<?php

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
    <link rel="stylesheet" href="CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="CSS/style.css" type="text/css"/>

    <link rel="stylesheet" href="/HTML/CSS/style.css"/>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- LIWANAG JS --> <!--Ayaw :/-->
    <script src="scripts.js"></script>
    
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
                        <a class="uk-navbar-item uk-logo" href="#">Little Wanderer's Therapy Center</a>
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
    
    <?php include "config.php"; ?>
<div class="body-create-acc uk-flex uk-flex-center uk-flex-middle">
    <!-- Create Account Card -->
    <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
        <!-- Title and Helper -->
        <h3 class="uk-card-title uk-flex uk-flex-center">Create an Account</h3>
        <p class="uk-flex uk-flex-center">Enter your personal details to start your journey with us.</p>

        <!-- Form Fields -->
        <form id="registrationForm" class="uk-form-stacked uk-grid-medium" uk-grid method="POST" action="signuppage.php">
            <!-- First Name -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label" for="firstName">First Name</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="firstName" name="firstName" type="text" placeholder="Input your First Name..." required>
                    <span class="error" id="firstNameError" style="color: red;"></span>
                </div>
            </div>

            <!-- Last Name -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label" for="lastName">Last Name</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="lastName" name="lastName" type="text" placeholder="Input your Last Name..." required>
                    <span class="error" id="lastNameError" style="color: red;"></span>
                </div>
            </div>

            <!-- Email -->
            <div class="uk-width-1@s uk-width-1@l">
                <label class="uk-form-label" for="email">Email</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="email" name="email" type="email" placeholder="Input your Email..." required>
                    <span class="error" id="emailError" style="color: red;"></span>
                </div>
            </div>

            <!-- Password Field -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label" for="password">Password</label>
                <div class="uk-form-controls">
                    <div class="uk-inline uk-width-1-1">
                        <input class="uk-input password-input" id="password" name="password" type="password" maxlength="20" minlength="8" placeholder="Input your Password..." required>
                        <span class="toggle-password">
                            <i class="fa fa-eye" id="togglePasswordIcon" onclick="togglePassword('password', 'togglePasswordIcon')"></i>
                        </span>
                    </div>
                </div>
                <span class="error" id="passwordError" style="color: red;"></span>
            </div>

            <!-- Confirm Password Field -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label" for="confirmPassword">Confirm Password</label>
                <div class="uk-form-controls">
                    <div class="uk-inline uk-width-1-1">
                        <input class="uk-input password-input" id="confirmPassword" name="confirmPassword" type="password" maxlength="20" minlength="8" placeholder="Confirm your Password..." required>
                        <span class="toggle-password">
                            <i class="fa fa-eye" id="toggleConfirmPasswordIcon" onclick="toggleConfirmPassword('confirmPassword', 'toggleConfirmPasswordIcon')"></i>
                        </span>
                    </div>
                </div>
                <span class="error" id="confirmPasswordError" style="color: red;"></span>
            </div>

            <!-- Address -->
            <div class="uk-width-1@s uk-width-1@l">
                <label class="uk-form-label" for="address">Address</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="address" name="address" type="text" placeholder="Input your Address..." required>
                    <span class="error" id="addressError" style="color: red;"></span>
                </div>
            </div>  

            <!-- Phone Number -->
            <div class="uk-width-1@s uk-width-1@l">
                <label class="uk-form-label" for="mobileNumber">Phone Number</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="mobileNumber" name="mobileNumber" type="text" placeholder="Input your Phone Number..." required>
                    <span class="error" id="mobileNumberError" style="color: red;"></span>
                </div>
            </div>  

                <!-- Sign Up Button -->
                <div class="signup-btn-div uk-width-1@s uk-width-1@l">
                <button type="submit" name="sign_up" class="uk-button uk-button-primary uk-width-1@s uk-width-1@l" onclick="return validatePassword() && validateMobileNumber()">Sign Up</button>
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

    <script>function togglePassword() {
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
    document.getElementById("registrationForm").addEventListener("submit", function (event) {
        let valid = true;

        // First Name Validation
        let firstName = document.getElementById("firstName").value.trim();
        let firstNameError = document.getElementById("firstNameError");
        let nameRegex = /^[A-Za-z]{2,30}$/;
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
        let passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\-_])[A-Za-z\d@$!%*?&\-_]{8,20}$/
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
        let mobileNumber = document.getElementById("mobileNumber").value;
        let mobileNumberError = document.getElementById("mobileNumberError");
        let mobileRegex = /^\d{10,15}$/;
        if (!mobileRegex.test(mobileNumber)) {
            mobileNumberError.textContent = "Phone number must be 10-15 digits.";
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

        if (!valid) {
            event.preventDefault();
        }
    });
});
</script>

    <?php
    include "send_verification.php";

    if (isset($_POST["sign_up"])) {
        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        //$password = md5($_POST['password']); //hashed password
        $password = $_POST['password'];
        $address = $_POST['address'];
        $mobileNumber = $_POST['mobileNumber'];

        $fullname = $firstName." ".$lastName; //fullname

        $otp = rand(000000,999999); //random otp number

        $sqlinsert = "INSERT INTO users(account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, otp) VALUES ('$firstName', '$lastName', '$email', '$password', '$address', '$mobileNumber', 'Customer', '$otp')";
        
        //convert insert sql to a query and transfer to mysql
        $result =$conn->query($sqlinsert);
    

        //check if connected or not
    if ($result == True) { 
        send_verification($fullname, $email, $otp);
    ?>
    <script>
        Swal.fire({
        position: "center",
        icon: "success",
        title: "Successfully added",
        showConfirmButton: false,
        timer: 1500
        });

        window.location.href= "otpverify.php";
    </script>

    <?php

    } else {
        //if not inserted
       echo $conn->error; //display table error
    }

    ?>

     <?php

    // $insertresult = $conn->query($sqlinsert);

    //     if ($insertresult > 0) {
    //         echo "<script>
    //         Swal.fire({
    //             title: 'Congratulations, your account is successfully created.',
    //             text: 'Do you want to proceed to login?',
    //             icon: 'success',
    //             showCancelButton: true,
    //             confirmButtonColor: '#323232',
    //             cancelButtonColor: '#BABABA',
    //             confirmButtonText: 'Yes, proceed!',
    //             cancelButtonText: 'No, stay here'
    //         }).then((result) => {
    //             if (result.isConfirmed) {
    //                 window.location.href = 'loginpage.html';
    //             }
    //         });
    //         </script>";
    //     } else {
    //         echo "<script>
    //         Swal.fire({
    //             title: 'Error!',
    //             text: 'There was an issue creating your account. Please try again.',
    //             icon: 'error',
    //             confirmButtonColor: '#323232'
    //         });
    //         </script>";
    //     }
    }
    ?> 

    <footer class="footer">
        <p class="footer-text">
            LIWANAG in construction, everything is subject to change.
        </p>
    </footer>

</body>

</html>












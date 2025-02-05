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
        <form id="registrationForm" class="uk-form-stacked uk-grid-medium" uk-grid method="POST" action="signuppage.php">
            <!-- First Name -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label" for="firstName">First Name</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="firstName" name="fname" type="text" placeholder="Input your First Name..." required>
                    <span class="error" id="firstNameError" style="color: red;"></span>
                </div>
            </div>

            <!-- Last Name -->
            <div class="uk-width-1@s uk-width-1-2@l">
                <label class="uk-form-label" for="lastName">Last Name</label>
                <div class="uk-form-controls">
                    <input class="uk-input" id="lastName" name="lname" type="text" placeholder="Input your Last Name..." required>
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
                    <input class="uk-input" id="mobileNumber" name="phone" type="text" placeholder="Input your Phone Number..." required>
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
     <script src="accountJS/signup.js"></script>
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>


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

        
    alert("Signup button clicked, form is being submitted!");



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
    

    // wala muna otp to check
     include "signupverify/send_verification.php";

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["signup"])) {

        $firstName = ucfirst(strtolower($_POST['fname']));
        $lastName = ucfirst(strtolower($_POST['lname']));
        $email = $_POST['email'];
        $password = md5($_POST['password']); //hashed password
        $address = $_POST['address'];
        $phoneNumber = $_POST['phone'];

        // could be used for session later on
        $fullname = $firstName." ".$lastName;

        //pabalik nlng pag oki na otp, i set ko muna as 0
        $otp = rand(000000,999999); //random otp number

        //accountstatus to be checked pa ulit

        $insertAccount = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, otp, created_at) 
                            VALUES ('$firstName', '$lastName', '$email', '$password', '$address', '$phoneNumber', 'Client',  $otp, NOW())";
        
        // TEMPORARY
        // if ($connection->query($insertAccount) === TRUE) {
        //     // Redirect to login page
        //     echo "<script>alert('Account created successfully!');</script>";
        //     header("Location: loginpage.php");
        //     exit(); 
        // } else {
        //     echo "Error: " . $connection->error;
        // }

        // $connection->close();
    


         $insertResult = $connection->query($insertAccount);

        //check if connected or not
         if ($insertResult == TRUE) { 
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
    
            window.location.href = "../signupverify/otpverify.php";

        </script> 

        <?php
        
       } else {
        //if not inserted
         echo $connection->error; //display table error
     }
 

// if($insertAccount == TRUE){
//     
//             Swal.fire({
//                 title: "Account Created!",
//                 text: "You are now registered!",
//                 icon: "success",
//                 showCancelButton: true,
//                 confirmButtonColor: "#741515",
//                 cancelButtonColor: "#E4A11B",
//                 confirmButtonText: "Login",
//                 cancelButtonText: "Home"
//             }).then((result) => {
//                 if (result.isConfirmed) {
//                     window.location.href = "login.php";
//                 } else {
//                     window.location.href = "index.php";
//                 }
//             });
//         </script>
//     <?php
//     }else{
//         echo $connection -> error;
//     }
    
    }
?>
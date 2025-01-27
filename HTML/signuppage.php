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
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="/HTML/CSS/style.css"/>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- LIWANAG JS -->
    <script src="scripts.js"></script>

    <!-- <script>
        // function validatePassword() {
        //     const password = document.getElementById("password").value;
        //     const confirmPassword = document.getElementById("confirmPassword").value;
        //     const errorField = document.getElementById("passwordError");
        //     const lengthErrorField = document.getElementById("passwordLengthError");

        //     if (password.length < 8) {
        //         lengthErrorField.innerHTML = "Password should be at least 8 characters long.";
        //         return false;
        //     } else {
        //         lengthErrorField.innerHTML = "";
        //     }

        //     if (password !== confirmPassword) {
        //         errorField.innerHTML = "Passwords do not match!";
        //         return false;
        //     } else {
        //         errorField.innerHTML = "";
        //         return true;
        //     }
        // }

        // function validateMobileNumber() {
        //     const mobileNumber = document.getElementById("mobileNumber").value;
        //     const mobileNumberError = document.getElementById("mobileNumberError");
        //     const pattern = /^\d+$/;

        //     if (!pattern.test(mobileNumber)) {
        //         mobileNumberError.innerHTML = "Please enter a valid mobile number with digits only.";
        //         return false;
        //     } else {
        //         mobileNumberError.innerHTML = "";
        //         return true;
        //     }
        // }

        // function togglePassword() {
        //     const passwordField = document.getElementById("password");
        //     const togglePasswordBtn = document.getElementById("togglePassword");

        //     if (passwordField.type === "password") {
        //         passwordField.type = "text";
        //         togglePasswordBtn.classList.remove("fa-eye");
        //         togglePasswordBtn.classList.add("fa-eye-slash");
        //     } else {
        //         passwordField.type = "password";
        //         togglePasswordBtn.classList.remove("fa-eye-slash");
        //         togglePasswordBtn.classList.add("fa-eye");
        //     }
        // }

        // function toggleConfirmPassword() {
        //     const confirmPasswordField = document.getElementById("confirmPassword");
        //     const toggleConfirmPasswordBtn = document.getElementById("toggleConfirmPassword");

        //     if (confirmPasswordField.type === "password") {
        //         confirmPasswordField.type = "text";
        //         toggleConfirmPasswordBtn.classList.remove("fa-eye");
        //         toggleConfirmPasswordBtn.classList.add("fa-eye-slash");
        //     } else {
        //         confirmPasswordField.type = "password";
        //         toggleConfirmPasswordBtn.classList.remove("fa-eye-slash");
        //         toggleConfirmPasswordBtn.classList.add("fa-eye");
        //     }
        // }
    </script> -->
</head>
<body>
    <?php include "config.php"; ?>

    <div class="body-create-acc uk-flex uk-flex-center uk-flex-middle">
        <!-- Create Account Card -->
        <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
            <!-- Title and Helper -->
            <h3 class="uk-card-title uk-flex uk-flex-center">Create an Account</h3>
            <p class="uk-flex uk-flex-center">Enter your personal details to start your journey with us.</p>

            <!-- Form Fields -->
            <form class="uk-form-stacked uk-grid-medium" uk-grid method="POST" action="">
                <!-- First Name --> 
                <div class="uk-width-1@s uk-width-1-2@l">
                    <label class="uk-form-label" for="firstName">First Name</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="firstName" name="firstName" type="text" placeholder="Input your First Name..." required>
                    </div>
                </div>

                <!-- Last Name --> 
                <div class="uk-width-1@s uk-width-1-2@l">
                    <label class="uk-form-label" for="lastName">Last Name</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="lastName" name="lastName" type="text" placeholder="Input your Last Name..." required>
                    </div>
                </div>

                <!-- Email -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="email">Email</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="email" name="email" type="email" placeholder="Input your Email..." required>
                    </div>
                </div>

                <!-- Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="password">Password</label>
                    <div class="uk-form-controls">
                        <div class="uk-inline">
                            <input class="uk-input" id="password" name="password" type="password" placeholder="Input your Password..." required>
                            <span class="uk-form-icon uk-form-icon-flip">
                                <i class="fa fa-eye" id="togglePassword" onclick="togglePassword()"></i>
                            </span>
                        </div>
                        <div id="passwordLengthError" style="color: red;"></div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="confirmPassword">Confirm Password</label>
                    <div class="uk-form-controls">
                        <div class="uk-inline">
                            <input class="uk-input" id="confirmPassword" name="confirmPassword" type="password" placeholder="Confirm your Password..." required>
                            <span class="uk-form-icon uk-form-icon-flip">
                                <i class="fa fa-eye" id="toggleConfirmPassword" onclick="toggleConfirmPassword()"></i>
                            </span>
                        </div>
                        <div id="passwordError" style="color: red;"></div>
                    </div>
                </div>

                <!-- Address -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="address">Address</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="address" name="address" type="text" placeholder="Input your Address..." required>
                    </div>
                </div>  

                <!-- Phone Number -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="mobileNumber">Phone Number</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="mobileNumber" name="mobileNumber" type="text" placeholder="Input your Phone Number..." required>
                        <div id="mobileNumberError" style="color: red;"></div>
                    </div>
                </div> 

                <!-- Sign Up Button -->
                <div class="signup-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="sign_up" class="uk-button uk-button-primary uk-width-1@s uk-width-1@l" onclick="return validatePassword() && validateMobileNumber()">Sign Up</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    include "send_verification.php";

    if (isset($_POST["sign_up"])) {
        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        $password = md5($_POST['password']); //hashed password
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

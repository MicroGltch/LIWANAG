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
                            <!-- <li><a href="signuppage.php">Sign Up to Book an Appointment</a></li> -->
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

    <div class="body-create-acc uk-flex uk-flex-center uk-flex-middle "> 
    <!-- Create Account Card -->
        <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
            
            <!-- Title and Helper -->
            <h3 class="uk-card-title uk-flex uk-flex-center">Create an Account</h3>
            <p class="uk-flex uk-flex-center">Enter your personal details to start your journey with us.</p>
            
            <!-- Form Fields -->
            <form action="signuppage.php" method="post" class="uk-form-stacked uk-grid-medium" uk-grid >
                                <!-- to insert for validation: (after method) onsubmit="return validate_signup()" -->

                <!-- psa.use uk-margin to automatically add top and bottom margin -->   
                
                <!-- First Name --> 
                <div class="uk-width-1@s uk-width-1-2@l ">
                    <label class="uk-form-label" for="form-stacked-text">First Name</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="form-stacked-text" type="text" placeholder="Input your First Name..." name="fname" required>
                    </div>
                </div>
            
                <!-- Last Name --> 
                <div class="uk-width-1@s uk-width-1-2@l">
                    <label class="uk-form-label" for="form-stacked-text">Last Name</label>
                    <div class="uk-form-controls">
                        <input  class="uk-input" id="form-stacked-text" type="text" placeholder="Input your Last Name..." name="lname" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="form-stacked-text">Email</label>
                    <div class="uk-form-controls">
                        <input  class="uk-input" id="form-stacked-text" type="text" placeholder="Input your Email..." name="email" required>
                    </div>
                </div>
            
                <!-- Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="password">Password</label>
                    <div class="uk-form-controls">
                        <div class="uk-inline">
                            <input class="uk-input" id="password" name="password" type="password" placeholder="Input your Password..." name="password" required>
                            <!-- Paayos di maketa ung icon -->
                            <span class="uk-form-icon uk-form-icon-flip">
                                <i class="fa fa-eye" id="togglePassword" onclick="togglePassword()"></i>
                            </span>
                        </div>
                        <div id="passwordLengthError" style="color: red;"></div>
                    </div>
                </div>
            
                <!-- Address -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="form-stacked-text">Adress</label>
                    <div class="uk-form-controls">
                        <input  class="uk-input" id="form-stacked-text" type="text" placeholder="Input your Address..." name="address" required>
                    </div>
                </div>  

                <!-- Phone Number -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="form-stacked-text">Phone Number</label>
                    <div class="uk-form-controls">
                        <input class="uk-input phonenumber-input" id="form-stacked-text" type="text" placeholder="Input your Phone Number..." name="phone" required>
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

<?php
    include "../dbconfig.php";

    // wala muna otp to check
    // include "send_verification.php";

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
        //$otp = rand(000000,999999); //random otp number

        //accountstatus to be checked pa ulit

        $insertAccount = "INSERT INTO users (account_FName, account_LName, account_Email, account_Password, account_Address, account_PNum, account_Type, otp, created_at) 
                            VALUES ('$firstName', '$lastName', '$email', '$password', '$address', '$phoneNumber', 'Client', 0, NOW())";
        
        // TEMPORARY
        if ($connection->query($insertAccount) === TRUE) {
            // Redirect to login page
            echo "<script>alert('Account created successfully!');</script>";
            header("Location: loginpage.php");
            exit(); 
        } else {
            echo "Error: " . $connection->error;
        }

        $connection->close();
    
}

        // $insertResult = $connection->query($insertAccount);

        //check if connected or not
        // if ($insertResult == TRUE) { 
        // send_verification($fullname, $email, $otp); balik mo nlng pag nilagay mo ung otp
    ?>
        <!-- <script>
            Swal.fire({
                position: "center",
                icon: "success",
                title: "Successfully added",
                showConfirmButton: false,
                timer: 1500
            });
        </script> -->

        <?php
        //send_verification($fullname, $email, $otp);
        ?> 
            
        <!-- <script>
            window.location.replace("otpverify.php");
        </script> -->
        <?php
//     } else {
//         //if not inserted
//         echo $connection->error; //display table error
//     }
// }

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
    

?>
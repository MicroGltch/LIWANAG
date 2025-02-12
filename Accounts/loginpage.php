<!DOCTYPE html>
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
                            <!-- <li><a href="#">Item</a></li> -->
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

    <div class="uk-flex uk-flex-center uk-flex-middle uk-height-viewport">
    <!-- Login Account Card -->
        <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
            
            <!-- Title and Helper -->
            <h3 class="uk-card-title uk-flex uk-flex-center">Welcome Back</h3>
            <p class="uk-flex uk-flex-center">Please log in to continue.</p>
            
            <!-- Form Fields -->
            <form action="loginlogic.php" method="post" class="uk-form-stacked uk-grid-medium" uk-grid>
                            <!-- add onsubmit for validation -->

                <!-- psa.use uk-margin to automatically add top and bottom margin -->   

                <!-- Email -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="form-stacked-text">Email</label>
                    <div class="uk-form-controls">
                        <input  class="uk-input" id="form-stacked-text" type="text" placeholder="Input your Email..." name="email" >
                        <span class="invalid-feedback" id="email-error"></span> 
                    </div>
                </div>
            
                <!-- Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="login-pass">Password</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="login-pass" type="password" placeholder="Input your Password..." name="password" >
                        <span style="position: absolute; right: 10px; cursor: pointer;" onclick="togglePassword()">
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>  </span>
                        <span class="invalid-feedback" id="pass-error"></span>  </div>
                </div>

                <!-- Add function -->
                <!--Remember Me-->
                <div class="uk-width-1-2@s uk-width-1-2@l uk-text-left@s">
                    <label class="uk-text-small"><input class="uk-checkbox" type="checkbox" id="rememberMe"> Remember me</label>
                </div>

                <!-- Add function -->
                <!--Forgot Password-->
                <div class="uk-width-1-2@s uk-width-1-2@l uk-text-right@s uk-text-right@l">
                    <button class="forgotPass-btn uk-button uk-button-link uk-text-capitalize">Forgot Password?</button>
                </div>

                <!-- Login Button -->
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="log_in" class="uk-button uk-button-primary uk-width-1@s uk-width-1@l">Log In</button>
                </div>

                <!-- Divider -->
                <div class="uk-width-1@s uk-width-1@l">
                    <hr>
                </div>
                
                <!-- Sign up Redirect -->
                <div class="uk-flex uk-flex-middle uk-flex-center uk-width-1@s uk-width-1@l">
                    <p class="signup-redirect-txt uk-flex uk-flex-middle uk-flex-center">No Account Yet? &nbsp; <a href="signuppage.php"> Register here</a> </p>
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
    <script src="accountJS/login.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('login-pass'); // Correct ID

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Remember Me Functionality
        const rememberMeCheckbox = document.getElementById('rememberMe');
        const emailInput = document.getElementById('form-stacked-text'); // Correct ID
        const passwordInputRemember = document.getElementById('login-pass'); // Correct ID


        rememberMeCheckbox.addEventListener('change', function () {
            if (this.checked) {
                localStorage.setItem('rememberedEmail', emailInput.value);
                localStorage.setItem('rememberedPassword', passwordInputRemember.value);
                localStorage.setItem('remembered', 'true');
            } else {
                localStorage.removeItem('rememberedEmail');
                localStorage.removeItem('rememberedPassword');
                localStorage.removeItem('remembered');
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const isRemembered = localStorage.getItem('remembered');
            if (isRemembered === 'true') {
                rememberMeCheckbox.checked = true;
                emailInput.value = localStorage.getItem('rememberedEmail');
                passwordInputRemember.value = localStorage.getItem('rememberedPassword');
            }
        });


    </script>

    <?php
    if (isset($_GET['loginError'])) {
        $errorMessage = urldecode($_GET['loginError']);
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: '$errorMessage',
                });
            </script>";
    }
    ?>
    
    <?php
    // include "../dbconfig.php";
    // session_start();

    // if (isset($_POST['login'])) {

    //     $email = $_POST['email'];
    //     $password = md5($_POST['password']);

    //     $checkEmail = "SELECT * FROM users WHERE account_Email = '$email'";
    //     $checkResult = $connection->query($checkEmail);

    //     if (!$email || !$password) {
    //         echo "<script>
    //             Swal.fire({
    //                 title: 'Incomplete Fields',
    //                 text: 'Please fill in all the required fields.',
    //                 icon: 'error',
    //                 confirmButtonColor: '#741515'
    //             });
    //         </script>";
    //         exit();
    //     } else if ($checkResult->num_rows == 0) {
    //         echo "<script>
    //             Swal.fire({
    //                 title: 'Email Not Found',
    //                 text: 'The email you entered does not exist.',
    //                 icon: 'error',
    //                 confirmButtonColor: '#741515'
    //             });
    //         </script>";
    //         exit();
    //     }

    //     $loginsql = "SELECT * FROM users WHERE account_Email = '$email' AND account_Password = '$password'";
    //     $loginresult = $connection->query($loginsql);

    //     if ($loginresult) { // Check if the query executed successfully
    //         if ($loginresult->num_rows == 1) {
    //             $row = $loginresult->fetch_assoc();
    //             $fullname = $row['account_FName'] . " " . $row['account_LName'];
    //             $_SESSION['username'] = $fullname;
    //             echo "console.error('Login successful for: ' . $fullname)";
    //             echo "<script>window.location.href = '../homepage.php';</script>";
    //             exit();
    //         } else {
    //             echo "<script>console.error('Password incorrect.');</script>";  // Use console.error for errors
    //             echo "<script>
    //                 Swal.fire({
    //                     title: 'Invalid Login',
    //                     text: 'Please check your email and password',
    //                     icon: 'error',
    //                     confirmButtonColor: '#741515'
    //                 });
    //             </script>";
    //             exit(); // Important: Add exit() after the Swal
    //         }
    //     } else {
    //         echo "<script>console.error('Database query error: " . $connection->error . "');</script>";
    //         echo "<script>
    //             Swal.fire({
    //                 title: 'Database Error',
    //                 text: 'An error occurred during login. Please try again later.',
    //                 icon: 'error',
    //                 confirmButtonColor: '#741515'
    //             });
    //         </script>";
    //         exit(); // Important: Add exit() here as well
    //     }

    //     $connection->close();
    // } else {
    //     echo "<script>console.log('No POST data received.');</script>";
    // }


?>
</body>
</html>
        <!-- // LOGS code

        // $logSQL = "Insert into tbl_logs(user_id, user_name, type, action, log_date) values('$userid', '$fullname', '$usertype', 'Logged In', NOW())";
        // $connection ->query($logSQL);
        
        // Redirect to Dashboard/HomePage to Book
        // if($usertype == 'Admin') {
        //     header("location: admin.php");
        //     exit(); 
        // } else if($usertype == 'User'){
        //     header("location: order.php");
        //     exit();
        // } -->
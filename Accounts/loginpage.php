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

    <!-- JS -->
    <script src="accountJS/login.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <!-- Nav Bar -->
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

    <div class="login-div uk-flex uk-flex-center uk-flex-middle uk-height-viewport">
    <!-- Login Account Card -->
        <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
            
            <!-- Title and Helper -->
            <h3 class="login-title uk-card-title uk-flex uk-flex-center">Welcome Back</h3>
            <p class="login-helper uk-flex uk-flex-center">Please log in to continue.</p>
            
            <!-- Form Fields -->
            <form id="login-form" action="loginverify/loginlogic.php" method="post" class="uk-form-stacked uk-grid-medium" uk-grid>
            <!-- add onsubmit for validation -->

                <!-- psa.use uk-margin to automatically add top and bottom margin -->   

                <!-- Email -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label uk-text-left" for="login-email">Email</label>
                    <div class="uk-form-controls">
                    <input class="email-txtbox uk-input" id="login-email" type="text" placeholder="Input your Email..." name="email">
                    <span class="invalid-feedback" id="email-error"></span> 
                    </div>
                </div>
            
                <!-- Password -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label uk-text-left" for="login-pass">Password</label>
                    <div class="uk-form-controls">
                        <input class="password-txtbox uk-input" id="login-pass" type="password" placeholder="Input your Password..." name="password">
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
                <a class="forgot-password-txt" href="passwordmodify/forgetpassword.php"> Forgot Password?</a>
                </div>

                <!-- Login Button -->
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="login" class="login-btn uk-button uk-button-primary uk-width-1@s uk-width-1@l">Log In</button>
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
</body>
</html>
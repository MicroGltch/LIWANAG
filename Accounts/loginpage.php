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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        .password-input-wrapper {
        position: relative;
    }
    .password-input-wrapper i {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        cursor: pointer;
    }

        .fa-eye-slash:before {
    content: "\f070";
        }
        .fa-eye:before {
            content: "\f06e";
        }
    </style>
</head>
<body>
    <!-- Nav Bar -->
    <nav class="uk-navbar-container">
        <div class="uk-container">
            <div uk-navbar>
                <!--Navbar Left-->


                <!--Navbar Center-->
                    <div class="uk-navbar-center">
                        <a class="logo-navbar uk-navbar-item uk-logo" href="../index.php">Little Wanderer's Therapy Center</a>
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
                    <div class="uk-form-controls ">

                    <div style="position: relative; display: flex; align-items: center;">
                        <input class="uk-input password-txtbox" id="login-pass" name="password" type="password" 
                            placeholder="Input your Password..." 
                            style="width: 100%; padding-right: 40px;">
                        <span style="position: absolute; right: 10px; cursor: pointer; top: 10px;" onclick="togglePassword()">
                            <i class="fa fa-eye" id="togglePasswordIcon"></i>
                        </span>
                        
                    </div>

                    </div>
                    <span class="invalid-feedback" id="pass-error"></span>
                </div>
                
                <div class="uk-width-1-2@s uk-width-1-2@l uk-text-left@s">
                    <label class="uk-text-small"><input class="uk-checkbox" type="checkbox" id="rememberMe"> Remember me</label>
                </div>
                <div class="uk-width-1-2@s uk-width-1-2@l uk-text-right@s uk-text-right@l">
                    <a class="forgot-password-txt" href="passwordmodify/forgetpassword.php"> Forgot Password?</a>
                </div>
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="login" class="login-btn uk-button uk-button-primary uk-width-1@s uk-width-1@l" style="border-radius: 15px">Log In</button>
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

<script>
        function togglePassword() {
            var passwordInput = document.getElementById("login-pass");
            var toggleIcon = document.getElementById("togglePasswordIcon");
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
    </script>

</body>
</html>
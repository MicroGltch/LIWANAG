<!DOCTYPE html>
<head>
    <meta name="viewport" content="width=device-width" />
    
    <title>LIWANAG - FORGET PASSWORD</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css" />



    
</head>

<body>
    <!-- Nav Bar (Ayusin pa alignment n stuff) -->
    <nav class="uk-navbar-container">
        <div class="uk-container">
            <div uk-navbar>
                <!--Navbar Left-->


                <!--Navbar Center-->
                    <div class="uk-navbar-center">
                        <a class="logo-navbar uk-navbar-item uk-logo" href="..\..\..\LIWANAG\homepage.php">Little Wanderer's Therapy Center</a>
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

    </div>


    <div class="forget-password-div uk-flex uk-flex-center uk-flex-middle uk-height-viewport" >
    <!-- Login Account Card -->
        <div class="create-acc-card uk-card uk-card-default uk-card-body uk-width-1-2 form-card">
            
            <!-- Title and Helper -->
            <h3 class="forgot-title uk-card-title uk-flex uk-flex-center">Forgot Password</h3>
            <p class="forgot-helper uk-flex uk-flex-center">To recover your password, kindly provide your email address.</p>
            
            <!-- Form Fields -->
            <form action="forgotpasswordprocess.php" method="POST" class="uk-form-stacked uk-grid-medium" uk-grid>

                <!-- psa.use uk-margin to automatically add top and bottom margin -->   

                <!-- Email -->
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" style="text-align: left ;">Email</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" name="email" type="email" placeholder="Input your Email..." required>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="forgot-submit-btn-div uk-width-1@s uk-width-1@l">
                    <button class="forgot-submit-btn uk-button uk-button-primary uk-width-1@s uk-width-1@l" style="border-radius: 15px">Submit</button>
                </div>

                <div class="uk-width-1@s uk-width-1@l">
                    <hr>
                </div>

                <div class="uk-flex uk-flex-middle uk-flex-center uk-width-1@s uk-width-1@l">
                <p class="signup-redirect-txt uk-flex uk-flex-middle uk-flex-center">Back to &nbsp; <a onclick="window.location.href='../loginpage.php';"> Login</a> </p>
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
</body>


</html>
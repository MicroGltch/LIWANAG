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
    <link rel="stylesheet" href="CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>
    
    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="CSS/style.css" type="text/css"/>

    
</head>

<body>
    <!-- Nav Bar (Ayusin pa alignment n stuff) -->
    <nav class="uk-navbar-container logged-in">
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
                        <a class="uk-navbar-item uk-logo" href="hompage.php">Little Wanderer's Therapy Center</a>
                    </div>

                <!--Navbar Right-->
                    <div class="uk-navbar-right">
                        <ul class="uk-navbar-nav">
                            <a href="#" class="uk-navbar-item">
                                <img class="profile-image" src="CSS/default.jpg" alt="Profile Image" uk-img> <!--Temp image-->
                            </a>
                            <li><a href="#">Logout</a></li>
                        </ul>
                    </div>
    
                </div>
    
            </div>
        </div>
    </nav>

    <!--Divider-->
    <hr class="solid">

    <!--Main Content-->
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-height-viewport">
        
        <!--Sidebar-->
        <div class="uk-width-1-1 uk-width-1-5@m uk-background-default uk-padding uk-box-shadow-medium">
            <button class="uk-button uk-button-default uk-hidden@m uk-width-1-1 uk-margin-bottom sidebar-toggle" type="button">
                Menu <span uk-navbar-toggle-icon></span>
            </button>
            
            <div class="sidebar-nav">
                <ul class="uk-nav uk-nav-default">
                    <li><a href="#"><span class="uk-margin-small-right" uk-icon="calendar"></span> Appointments</a></li>
                    <li class="uk-active"><a href="accountdetailspage.php"><span class="uk-margin-small-right" uk-icon="user"></span> Account Details</a></li>
                    <li><a href="settingspage.php"><span class="uk-margin-small-right" uk-icon="cog"></span> Settings</a></li>
                </ul>
            </div>
        </div>

        <!--Account Details Card-->
        <div class="uk-width-1-1 uk-width-4-5@m uk-padding">
            <h1 class="uk-text-bold">Account Details</h1>
            
            <!--Profile Photo Section-->
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <h3 class="uk-card-title uk-text-bold">Profile Photo</h3>
                <div class="uk-flex uk-flex-center">
                    <div class="uk-width-1-4">
                        <img class="uk-border-circle" src="CSS/default.jpg" alt="Profile Photo">
                    </div>
                </div>
            </div>

            <!--User Details Section-->
            <div class="uk-card uk-card-default uk-card-body">
                <h3 class="uk-card-title uk-text-bold">User Details</h3>
                <form class="uk-grid-small" uk-grid>
                    <div class="uk-width-1-2@s">
                        <label class="uk-form-label">First Name</label>
                        <input class="uk-input" type="text" placeholder="Placeholder">
                    </div>
                    <div class="uk-width-1-2@s">
                        <label class="uk-form-label">Last Name</label>
                        <input class="uk-input" type="text" placeholder="Placeholder">
                    </div>
                    <div class="uk-width-1-1">
                        <label class="uk-form-label">Email</label>
                        <input class="uk-input" type="email" placeholder="Placeholder">
                    </div>
                    <div class="uk-width-1-1">
                        <label class="uk-form-label">Phone Number</label>
                        <input class="uk-input" type="tel" placeholder="Placeholder">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p class="footer-text">
            LIWANAG in construction, everything is subject to change.
        </p>
    </footer>

    <script>
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar-nav').classList.toggle('uk-open');
        });
    </script>

</body>


</html>
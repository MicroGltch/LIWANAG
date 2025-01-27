<?php
include "dbconfig.php";
session_start();
?>

<!DOCTYPE html>
<head>
    <meta name="viewport" content="width=device-width" />
    
    <title>LIWANAG - HOMEPAGE</title>
    
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

   <!-- Debugging: Print session data in the console -->
   <script>
        console.log('Session Username:', <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>);
    </script>

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
                        <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
                    </div>

                    <!-- Navbar Right -->
                    <div class="uk-navbar-right">
                        <ul class="uk-navbar-nav">
                            <?php if (isset($_SESSION['username'])): ?>
                                <li><a href="#">Hi, <?php echo $_SESSION['username']; ?>!</a></li>
                                <li><a href="Accounts/logout.php">Logout</a></li>
                            <?php else: ?>
                                <li><a href="Accounts/signuppage.php">Sign Up to Book an Appointment</a></li>
                                <li><a href="Accounts/loginpage.php">Login</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
    
            </div>
        </div>
    </nav>


        <section>

        </section>

        <section>

        </section>

        <section>

        </section>

    <!-- Footer -->
    <footer class="footer">
        <p class="footer-text">
            LIWANAG in construction, everything is subject to change.
        </p>
    </footer>
</body>


</html>
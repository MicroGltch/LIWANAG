<?php
include "dbconfig.php";
session_start();

// Fetch user data from the database
$stmt = $connection->prepare("SELECT account_FName, account_LName, account_Email, account_PNum, profile_picture FROM users WHERE account_ID = ?");
$stmt->bind_param("s", $userid);
$stmt->execute();
$result = $stmt->get_result();


if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $firstName = $userData['account_FName'];
    $lastName = $userData['account_LName'];
    $email = $userData['account_Email'];
    $phoneNumber = $userData['account_PNum'];
    // Determine the profile picture path
    if ($userData['profile_picture']) {
        $profilePicture = '../uploads/profile_pictures/' . $userData['profile_picture']; // Corrected path
    } else {
        $profilePicture = '../CSS/default.jpg';
    }
    // $profilePicture = $userData['profile_picture'] ? '../uploads/' . $userData['profile_picture'] : '../CSS/default.jpg';
} else {
    echo "";
}


$stmt->close();

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
    <link rel="stylesheet" href="CSS/style.css" type="text/css" />


</head>

<body>

    <!-- Debugging: Print session data in the console -->
    <script>
        console.log('Session Username:', <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>);
        console.log('Account Type:', <?php echo isset($_SESSION['account_Type']) ? json_encode($_SESSION['account_Type']) : 'null'; ?>);
    </script>

    <!-- Navbar Wrapper (Removes Absolute Positioning Issues) -->
    <div uk-sticky="start: 200; animation: uk-animation-slide-top; sel-target: .uk-navbar-container; cls-active: uk-navbar-sticky; cls-inactive: uk-navbar-transparent uk-light">

        <!-- Navbar -->
        <nav class="uk-navbar-container uk-light uk-navbar-transparent logged-out">
            <div class="uk-container">
                <div uk-navbar>
                    <!-- Navbar Left -->
                    <div class="uk-navbar-left" style="padding: right 10px;">
                        <ul class="uk-navbar-nav">
                            <li class="uk-active"><a href="#section2">Services</a></li>
                            <li class="uk-active"><a href="#section3">About Us</a></li>
                            <li class="uk-active"><a href="#">Terms and Conditions</a></li>
                            <li class="uk-active"><a href="#">FAQs</a></li>

                        </ul>
                    </div>

                    <!-- Navbar Center -->
                    <div class="uk-navbar-center">
                        <a class="uk-navbar-item uk-logo" href="homepage.php">Little Wanderer's Therapy Center</a>
                    </div>

                    <!-- Navbar Right -->
                    <div class="uk-navbar-right">
                        <ul class="uk-navbar-nav">
                            <?php
                            if (isset($_SESSION['account_ID'])):

                                $account_ID = $_SESSION['account_ID'];
                                $query = "SELECT account_FName, account_Type FROM users WHERE account_ID = ?";
                                $stmt = $connection->prepare($query);
                                $stmt->bind_param("i", $account_ID);
                                $stmt->execute();
                                $stmt->bind_result($account_FN, $account_Type);
                                $stmt->fetch();
                                $stmt->close();
                                $connection->close();


                                // Determine the dashboard URL based on account type
                                switch ($account_Type) {
                                    case 'admin':
                                        $dashboardURL = "Dashboards/admindashboard.php";
                                        break;
                                    case 'therapist':
                                        $dashboardURL = "Dashboards/therapistdashboard.php";
                                        break;
                                    case 'client':
                                    default:
                                        $dashboardURL = "Dashboards/clientdashboard.php";
                                        break;
                                }
                            ?>
                                <li>
                                    <a href="#">Hi, <?php echo htmlspecialchars($account_FN); ?>!</a>
                                    <div class="uk-navbar-dropdown">
                                        <ul class="uk-nav uk-navbar-dropdown-nav">
                                            <li><a href="<?php echo $dashboardURL; ?>" style="color: black !important;">Dashboard</a></li>
                                            <li><a href="Accounts/logout.php" style="color: black !important;">Logout</a></li>
                                        </ul>
                                    </div>
                                </li>
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

    <!---------------- Welcome Section ---------------->

    <div class="uk-section" id="section1">

        <div class="" uk-grid style="margin-left: 0px;">
            <div class="homepage-welcome uk-width-1-2@l uk-width-1-1@s uk-width-1-1@m">

                <div class="welcome-title">

                    Welcome to Little Wanderer's Therapy Center
                </div>

                <div class="welcome-description">
                    At Little Wanderer's Therapy Center, we provide gentle and compassionate care in a warm, supportive environment. Here, every child is met with understanding and encouragement, creating a safe space where they can grow and flourish. You are not alone— together, we’ll nurture hope and possibilities.
                </div>

                <div>
                    <?php if (isset($_SESSION['account_ID'])): ?>
                        <a href="Dashboards/clientdashboard.php#book-appointment" class="welcome-book" style="color: white;">Book an Appointment</a>
                    <?php else: ?>
                        <button class="welcome-book" style="color: white;">Book an Appointment</button>
                    <?php endif; ?>
                </div>

            </div>



        </div>

    </div>

    <div id="section2" class="uk-section">
        <div class="uk-container">


            <h2 class="service-title">
                Therapy Services Offered
            </h2>

            <div class="uk-grid-match uk-child-width-1-2@m" uk-grid>

                
                <div>
                    <div class="ie-img">
                        <img src="CSS/ie.png" alt="Initial Evaluation" style="height: 350px;">
                    </div>
                    <h4 class="service">
                        Initial Evaluation
                    </h4>
                    <p class="service-description">
                        Our welcoming initial evaluation is a gentle way for us to get to know your child and understand their unique strengths and needs. Through playful interaction and observation, we craft a personalized plan that nurtures their growth. This first step ensures we build a supportive and effective therapy journey together.
                    </p>
                </div>

                
                <div>
                    <div class="ie-img">
                        <img src="CSS/playgroup.png" alt="Playgroup" style="height: 350px;">
                    </div>
                    <h4 class="service">
                        Playgroup
                    </h4>
                    <p class="service-description">

                        Our delightful playgroup offers a safe and cheerful space where children can laugh, play, and make new friends. With a mix of guided activities and free play, kids naturally develop social skills, cooperation, and emotional expression. Each session is a joyful adventure that fosters friendships and self-confidence in a nurturing atmosphere.
                    </p>
                </div>

                
                <div>
                    <div class="ie-img">
                        <img src="CSS/behavioral.png" alt="Behavioral" style="height: 350px;">
                    </div>
                    <h4 class="service">
                        Behavioral
                    </h4>
                    <p class="service-description">
                        In our compassionate behavioral therapy sessions, we empower children to develop positive behaviors and emotional balance. Together, we explore gentle strategies that address challenges like anxiety, tantrums, or focus difficulties. Our supportive environment helps your child thrive, building resilience and joyful connections at home and beyond.
                    </p>
                </div>

                
                <div>
                    <div class="ie-img">
                        <img src="CSS/speech.png" alt="Speech" style="height: 350px;">
                    </div>
                    <h4 class="service">
                        Speech
                    </h4>
                    <p class="service-description">
                        Our engaging speech therapy sessions are designed to help your child find their voice and express themselves with confidence. Through fun activities and loving encouragement, we work on speech clarity, language skills, and social communication. Every milestone celebrated brings your child closer to sharing their thoughts and feelings with ease.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!---------------- About Us  ---------------->
    <div id="section3" class="uk-section">
        <div class="uk-container">
            <div class="uk-grid-match uk-child-width-1-2@m" uk-grid>
                testing tetsing
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p class="footer-text">
            LIWANAG in construction, everything is subject to change.
        </p>
    </footer>
</body>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const bookAppointmentButton = document.querySelector('.welcome-book');

        if (bookAppointmentButton && bookAppointmentButton.tagName === 'BUTTON') { // Check if it's a button
            bookAppointmentButton.addEventListener('click', function(event) {
                event.preventDefault(); // Prevent form submission if it's a button

                Swal.fire({
                    title: 'Do you Have an Account?',
                    text: 'You need to be logged in to book an appointment.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Login',
                    cancelButtonText: 'No, Sign Up'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'Accounts/loginpage.php';
                    } else if (result.isDismissed) {
                        window.location.href = 'Accounts/signuppage.php';
                    }
                });
            });
        }
    });
</script>

</html>




<!---------------- Services  
    <div id="section2" class="uk-section">
        <div class="uk-container">

             uk-parallax="opacity: 0,1; y: -50,0; scale: 1.5,1; end: 50vh + 50%; "
            <h2 class="service-title">
                Therapy Services Offered
            </h2>

            <div class="uk-grid-match uk-child-width-1-2@m" uk-grid>

                 Initial Evaluation 
                <div>
                    <div class="ie-img"
                        uk-parallax="opacity: 0,1; x: -50,0; end: 60vh + 60%; @m: x: -100,0; end: 60vh + 60%;">
                        <img src="CSS/ie.png" alt="Initial Evaluation" style="height: 350px;">
                    </div>
                    <h4 class="service"
                        uk-parallax="opacity: 0,1; x: -50,0; end: 60vh + 60%; @m: x: -100,0; end: 60vh + 60%;">
                        Initial Evaluation
                    </h4>
                    <p class="service-description"
                        uk-parallax="opacity: 0,1; x: -100,0; end: 65vh + 65%; @m: x: -200,0; end: 65vh + 65%;">
                        Our welcoming initial evaluation is a gentle way for us to get to know your child and understand their unique strengths and needs. Through playful interaction and observation, we craft a personalized plan that nurtures their growth. This first step ensures we build a supportive and effective therapy journey together.
                    </p>
                </div>

                 Playgroup 
                <div>
                    <div class="ie-img"
                        uk-parallax="opacity: 0,1; x: 50,0; end: 60vh + 60%; @m: x: 100,0; end: 60vh + 60%;">
                        <img src="CSS/playgroup.png" alt="Playgroup" style="height: 350px;">
                    </div>
                    <h4 class="service"
                        uk-parallax="opacity: 0,1; x: 50,0; end: 60vh + 60%; @m: x: 100,0; end: 60vh + 60%;">
                        Playgroup
                    </h4>
                    <p class="service-description"
                        uk-parallax="opacity: 0,1; x: 100,0; end: 65vh + 65%; @m: x: 200,0; end: 65vh + 65%;">

                        Our delightful playgroup offers a safe and cheerful space where children can laugh, play, and make new friends. With a mix of guided activities and free play, kids naturally develop social skills, cooperation, and emotional expression. Each session is a joyful adventure that fosters friendships and self-confidence in a nurturing atmosphere.
                    </p>
                </div>

                 Behavioral 
                <div>
                    <div class="ie-img"
                        uk-parallax="opacity: 0,1; x: -50,0; end: 70vh + 70%; @m: x: -100,0; end: 80vh + 80%;">
                        <img src="CSS/behavioral.png" alt="Behavioral" style="height: 350px;">
                    </div>
                    <h4 class="service"
                        uk-parallax="opacity: 0,1; x: -50,0; end: 70vh + 70%; @m: x: -100,0; end: 80vh + 80%;">
                        Behavioral
                    </h4>
                    <p class="service-description"
                        uk-parallax="opacity: 0,1; x: -100,0; end: 75vh + 75%; @m: x: -200,0; end: 85vh + 85%;">
                        In our compassionate behavioral therapy sessions, we empower children to develop positive behaviors and emotional balance. Together, we explore gentle strategies that address challenges like anxiety, tantrums, or focus difficulties. Our supportive environment helps your child thrive, building resilience and joyful connections at home and beyond.
                    </p>
                </div>

                 Speech
                <div>
                    <div class="ie-img"
                        uk-parallax="opacity: 0,1; x: 50,0; end: 70vh + 70%; @m: x: 100,0; end: 80vh + 80%;">
                        <img src="CSS/speech.png" alt="Speech" style="height: 350px;">
                    </div>
                    <h4 class="service"
                        uk-parallax="opacity: 0,1; x: 50,0; end: 70vh + 70%; @m: x: 100,0; end: 80vh + 80%;">
                        Speech
                    </h4>
                    <p class="service-description"
                        uk-parallax="opacity: 0,1; x: 100,0; end: 75vh + 75%; @m: x: 200,0; end: 85vh + 85%;">
                        Our engaging speech therapy sessions are designed to help your child find their voice and express themselves with confidence. Through fun activities and loving encouragement, we work on speech clarity, language skills, and social communication. Every milestone celebrated brings your child closer to sharing their thoughts and feelings with ease.
                    </p>
                </div>
            </div>
        </div>
    </div> 
    ---------------->
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

// Fetch dynamic content from the database
$stmt = $connection->prepare("SELECT section_name, content FROM webpage_content");
$stmt->execute();
$result = $stmt->get_result();

$content = [];
while ($row = $result->fetch_assoc()) {
    $content[$row['section_name']] = $row['content'];
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

                    <!-- Navbar Left (Mobile: Offcanvas Trigger) -->
                    <div class="menu-div uk-navbar-left uk-hidden@s">
                        <a href="#offcanvas-slide" class="menu-button uk-button uk-button-default" uk-toggle>Menu</a>
                    </div>

                    <!-- Navbar Left (Desktop) -->
                    <div class="uk-navbar-left uk-visible@s">
                        <ul class="uk-navbar-nav">
                            <li class="uk-active"><a href="#section2">Services</a></li>
                            <li class="uk-active"><a href="#section3">About Us</a></li>
                            <li class="uk-active"><a href="#tnc-modal" uk-toggle>Terms and Conditions</a></li>
                            <li class="uk-active"><a href="#faqs-modal" uk-toggle>FAQs</a></li>
                        </ul>
                    </div>

                    <!-- Navbar Center -->
                    <div class="logo-center-div uk-navbar-center">
                        <a class="logo-navbar uk-navbar-item uk-logo" href="homepage.php" style="margin-top: -15px;">
                            Little Wanderer's Therapy Center
                        </a>
                    </div>

                    <!-- Navbar Right -->
                    <div class="uk-navbar-right">
                        <ul class="uk-navbar-nav">
                            <?php if (isset($_SESSION['account_ID'])): ?>
                                <?php
                                $account_ID = $_SESSION['account_ID'];
                                $query = "SELECT account_FName, account_Type FROM users WHERE account_ID = ?";
                                $stmt = $connection->prepare($query);
                                $stmt->bind_param("i", $account_ID);
                                $stmt->execute();
                                $stmt->bind_result($account_FN, $account_Type);
                                $stmt->fetch();
                                $stmt->close();
                                $connection->close();

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
                                    <a class="username-nav" href="#">Hi, <?php echo htmlspecialchars($account_FN); ?>!</a>
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
        </nav>
    </div>

    <!-- Offcanvas Menu (Mobile) -->
    <div id="offcanvas-slide" uk-offcanvas="mode: slide; overlay: true">
        <div class="uk-offcanvas-bar">
            <button class="uk-offcanvas-close" type="button" uk-close></button>
            <ul class="uk-nav uk-nav-default">
                <li class="uk-active"><a href="#section2" style="color:black; margin-top:25px; border-radius: 15px;">Services</a></li>
                <li class="uk-active"><a href="#section3" style="color:black; border-radius: 15px;">About Us</a></li>
                <li class="uk-active"><a href="#tnc-modal" uk-toggle style="color:black; border-radius: 15px;">Terms and Conditions</a></li>
                <li class="uk-active"><a href="#faqs-modal" uk-toggle style="color:black; border-radius: 15px;">FAQs</a></li>
            </ul>
        </div>
    </div>


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
                        <?php if ($_SESSION['account_Type'] == 'client'): ?>
                            <a href="Dashboards/clientdashboard.php#book-appointment" class="welcome-book" style="color: white;">Book an Appointment</a>
                        <?php else: ?>
                            <a href="<?php echo $dashboardURL; ?>" class="welcome-book" style="color: white;">Go to Dashboard</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="welcome-book" style="color: white; border-radius: 15px">Book an Appointment</button>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>


    <!---------------- Services Section ---------------->

    <div id="section2" class="uk-section">
        <div class="uk-container">
            <h2 class="service-title uk-text-center">
                Therapy Services Offered
            </h2>

            <div class="uk-grid-match uk-child-width-1-2@m uk-grid" uk-height-match="target: > div > .service-content">

                <!-- Initial Evaluation Service -->
                <div>
                    <div class="service-content uk-flex uk-flex-column">
                        <div class="ie-img uk-flex uk-flex-center">
                            <img id="ie-img" src="CSS/ie.png" alt="Initial Evaluation" style="height: 350px;">
                        </div>

                        <div class="service-content uk-flex uk-flex-column">
                            <h4 class="service uk-text-center">Initial Evaluation</h4>

                            <div class="service-description uk-flex-1">
                                <p>
                                    Our welcoming initial evaluation is a gentle way for us to get to know your child and understand their unique strengths and needs. Through playful interaction and observation, we craft a personalized plan that nurtures their growth.
                                </p>
                            </div>

                            <div class="uk-flex uk-flex-column">
                                <div class="service-description"><strong>This includes:</strong></div>
                                <ul class="service-description uk-list uk-list-disc">
                                    <li>1-hour evaluation session which includes interview, assessment, and discussion of findings.</li>
                                    <li>Parent's interview including review of history and discussion of findings, recommendations, and goals.</li>
                                    <li>Assessment, Functional Assessment, and Child Observation.</li>
                                    <li>Review of records and latest progress reports from Developmental Pediatrician, schools, and other practitioners.</li>
                                    <li>Assessment is recorded for documentation but is strictly for clinician's use only and cannot be given to the parents.</li>
                                    <li>Comprehensive written reports will be given 4-6 weeks after the date of assessment.</li>
                                    <li>All incoming students, whether the child underwent or is still undergoing therapy from another therapy center, will undergo assessment at Little Wanderer Therapy Center.</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Play Group Service -->
                <div>
                    <div class="service-content uk-flex uk-flex-column">
                        <div class="ie-img uk-flex uk-flex-center">
                            <img id="ie-img" src="CSS/playgroup.png" alt="Playgroup" style="height: 350px;">
                        </div>

                        <div class="service-content uk-flex uk-flex-column">

                            <h4 class="service uk-text-center">Play Group Session</h4>

                            <div class="service-description uk-flex-1">
                                <p>
                                    Our delightful playgroup offers a safe and cheerful space where children can laugh, play, and make new friends.
                                    With a mix of guided activities and free play, kids naturally develop social skills, cooperation, and emotional expression.
                                </p>
                            </div>

                            <div class="uk-flex uk-flex-column">
                                <div class="service-description"><strong>This includes:</strong></div>
                                <ul class="service-description uk-list uk-list-disc">
                                    <li>2-hour playgroup program Thursday to Saturday 1-3 PM.</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Applied Behavioral Therapy -->
                <div>
                    <div class="service-content uk-flex uk-flex-column">
                        <div class="ie-img uk-flex uk-flex-center">
                            <img id="ie-img" src="CSS/behavioral.png" alt="Behavioral Therapy" style="height: 350px;">
                        </div>

                        <div class="service-content uk-flex uk-flex-column">
                            <h4  class="service uk-text-center">Applied Behavioral Therapy</h4>

                            <div class="service-description uk-flex-1">
                                <p>
                                    In our compassionate behavioral therapy sessions, we empower children to develop positive behaviors and emotional balance. Together, we explore gentle strategies that address challenges like anxiety, tantrums, or focus difficulties.
                                </p>
                            </div>

                            <div class="uk-flex uk-flex-column">
                                <div class="service-description"><strong>This includes:</strong></div>
                                <ul class="service-description uk-list uk-list-disc">
                                    <li>50 minutes of intervention (Face to Face).</li>
                                    <li>5 minutes of note-taking.</li>
                                    <li>5 minutes giving feedback to the parents.</li>
                                </ul>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Occupational Therapy -->
                <div>
                    <div class="service-content uk-flex uk-flex-column">
                        <div class="ie-img uk-flex uk-flex-center">
                            <img id="ie-img" src="CSS/speech.png" alt="Speech Therapy" style="height: 350px;">
                        </div>

                        <div class="service-content uk-flex uk-flex-column">
                        <h4  class="service uk-text-center">Occupational Therapy</h4>

                        <div class="service-description uk-flex-1">
                        <p>
                            Our engaging occupational therapy sessions are designed to help your child find their voice and express themselves with confidence. Through fun activities and loving encouragement, we work on speech clarity, language skills, and social communication.
                        </p>
                        </div>

                        <div class="uk-flex uk-flex-column">
                            <div class="service-description"><strong>This includes:</strong></div>
                            <ul class="service-description uk-list uk-list-disc">
                                <li>55 minutes of intervention (Face to Face) with note-taking.</li>
                                <li>5 minutes giving feedback to parents or significant others regarding the child's performance during the session and necessary home instructions.</li>
                            </ul>
                        </div>

                    </div>
                    </div>
                </div>

            </div>
        </div>
    </div>




    <!--About Us Section-->
    <div id="section3" class="uk-section">
        <h2 class="about-us-title">
            About Us
        </h2>
        <div class="about-us uk-grid uk-flex-middle" uk-grid style="margin-left: 0px;">

            <!-- Description Section -->
            <div class="description-div uk-width-1-2@l uk-width-1-1@s uk-width-1-2@m">

                <div class="about-us-quote">
                    Nurturing Wander and Wonders
                </div>
                
                <!-- TESTING FOR MANAGE CONTENT -->
                <div class="about-us-description">
                    <!-- Wanderer Therapy Center, located in Makati City, is committed to improving the lives of children by offering accessible, high-quality therapy services. Our mission is to identify special needs early, provide the necessary support, and implement effective interventions, ensuring that young people can fully participate and thrive. We aim to make a positive difference in the lives of the children and families in our community by nurturing wander and wonder. -->
                    <?php echo $content['about_us'] ?? ''; ?>

                </div>
            </div>

            <!-- Slideshow Section -->
            <div class="slideshow-div uk-width-1-2@l uk-width-1-1@s uk-width-1-2@m">
                <div class="" uk-slideshow="finite: true; pause-on-hover: true; autoplay: true; autoplay-interval: 6000">
                    <ul class="about-us-slideshow uk-slideshow-items" style="aspect-ratio: 16 / 12; width: 100%;">
                        <li>
                            <img src="CSS/building.png" alt="" uk-cover>
                        </li>
                        <li>
                            <img src="CSS/lobby.png" alt="" uk-cover>
                        </li>
                        <li>
                            <img src="CSS/session-room.png" alt="" uk-cover>
                        </li>
                        <li>
                            <img src="CSS/middle-table.png" alt="" uk-cover>
                        </li>
                        <li>
                            <img src="CSS/play-area.png" alt="" uk-cover>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="tnc-modal" uk-modal>
        <div class="uk-modal-dialog uk-modal-body" style="border-radius: 15px">
            <h2 class="uk-modal-title">Terms and Conditions</h2>
            <!-- TESTING FOR MANAGE CONTENT -->
            <!-- <p> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum placerat convallis placerat. Etiam dictum malesuada dui. Sed et tortor viverra, lobortis nibh eu, pulvinar purus. Vivamus vel lacus vitae magna blandit posuere sit amet ac neque. Aliquam consequat posuere lectus a varius. Mauris a lorem pulvinar, feugiat nunc in, varius nisi. Nunc nulla risus, ornare ultricies eleifend a, tincidunt vitae diam. Nulla metus dolor, egestas id condimentum quis, maximus sit amet urna. Nunc ac mollis augue. Phasellus tincidunt leo sed dolor molestie malesuada. Duis suscipit feugiat elit, eu viverra nisi porttitor ut. Mauris vitae imperdiet nibh. Pellentesque mattis ex condimentum erat mattis blandit. Aliquam ac venenatis tellus. Nunc in interdum nibh. Phasellus varius ornare purus ut volutpat.

                Donec vehicula, augue non mattis venenatis, nisl quam tincidunt velit, id mattis quam quam nec justo. Nullam efficitur tempor volutpat. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Pellentesque gravida elit non libero porta, in euismod arcu aliquet. Praesent posuere posuere dolor. Nullam mattis lectus nisl, at aliquam urna interdum vitae. Etiam non lobortis urna. Donec elementum, urna sed lobortis maximus, nulla metus elementum nibh, sit amet egestas libero tellus vel nibh. Sed sapien ex, tincidunt pellentesque magna a, condimentum lobortis ante. Quisque sed sollicitudin arcu, a congue elit. Vestibulum dictum elit vitae eleifend gravida. Nulla suscipit felis at eros dignissim convallis. Mauris ut tincidunt justo. Vivamus velit leo, ornare vitae nibh eget, congue tempus quam. Duis vehicula eu erat ac fringilla.

                Vivamus eleifend, risus sed iaculis tincidunt, urna dui hendrerit lacus, et pharetra nulla massa a urna. Interdum et malesuada fames ac ante ipsum primis in faucibus. Morbi pretium eget turpis id pulvinar. Nullam in imperdiet sem, et consectetur dui. Pellentesque mattis ex in feugiat tempus. In at nunc orci. Sed accumsan scelerisque ipsum, vel lobortis sem gravida non. Curabitur facilisis, felis molestie ornare consectetur, nibh ex congue nisi, in mattis erat leo at nisi. Vestibulum et lorem nec lorem elementum eleifend. </p>
             -->
             <div><?php echo nl2br(htmlspecialchars_decode($content['terms'] ?? '', ENT_QUOTES)); ?></div>

                <p class="uk-text-right">
                <button class="uk-button uk-button-primary uk-modal-close" type="button" style="border-radius: 15px">Close</button>
            </p>
        </div>
    </div>

    <!-- FAQs Modal -->
    <div id="faqs-modal" uk-modal>
        <div class="uk-modal-dialog uk-modal-body" style="border-radius: 15px">
            <h2 class="uk-modal-title">FAQs</h2>
            <!-- TESTING FOR MANAGE CONTENT -->
            <!-- <p> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum placerat convallis placerat. Etiam dictum malesuada dui. Sed et tortor viverra, lobortis nibh eu, pulvinar purus. Vivamus vel lacus vitae magna blandit posuere sit amet ac neque. Aliquam consequat posuere lectus a varius. Mauris a lorem pulvinar, feugiat nunc in, varius nisi. Nunc nulla risus, ornare ultricies eleifend a, tincidunt vitae diam. Nulla metus dolor, egestas id condimentum quis, maximus sit amet urna. Nunc ac mollis augue. Phasellus tincidunt leo sed dolor molestie malesuada. Duis suscipit feugiat elit, eu viverra nisi porttitor ut. Mauris vitae imperdiet nibh. Pellentesque mattis ex condimentum erat mattis blandit. Aliquam ac venenatis tellus. Nunc in interdum nibh. Phasellus varius ornare purus ut volutpat.

                Donec vehicula, augue non mattis venenatis, nisl quam tincidunt velit, id mattis quam quam nec justo. Nullam efficitur tempor volutpat. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Pellentesque gravida elit non libero porta, in euismod arcu aliquet. Praesent posuere posuere dolor. Nullam mattis lectus nisl, at aliquam urna interdum vitae. Etiam non lobortis urna. Donec elementum, urna sed lobortis maximus, nulla metus elementum nibh, sit amet egestas libero tellus vel nibh. Sed sapien ex, tincidunt pellentesque magna a, condimentum lobortis ante. Quisque sed sollicitudin arcu, a congue elit. Vestibulum dictum elit vitae eleifend gravida. Nulla suscipit felis at eros dignissim convallis. Mauris ut tincidunt justo. Vivamus velit leo, ornare vitae nibh eget, congue tempus quam. Duis vehicula eu erat ac fringilla.

                Vivamus eleifend, risus sed iaculis tincidunt, urna dui hendrerit lacus, et pharetra nulla massa a urna. Interdum et malesuada fames ac ante ipsum primis in faucibus. Morbi pretium eget turpis id pulvinar. Nullam in imperdiet sem, et consectetur dui. Pellentesque mattis ex in feugiat tempus. In at nunc orci. Sed accumsan scelerisque ipsum, vel lobortis sem gravida non. Curabitur facilisis, felis molestie ornare consectetur, nibh ex congue nisi, in mattis erat leo at nisi. Vestibulum et lorem nec lorem elementum eleifend. </p>
             -->
             <div><?php echo nl2br(htmlspecialchars_decode($content['faqs'] ?? '', ENT_QUOTES)); ?></div>

                <p class="uk-text-right">
                <button class="uk-button uk-button-primary uk-modal-close" type="button" style="border-radius: 15px">Close</button>
            </p>
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

        
            <div style="text-align: right;">
    <ul class="uk-list uk-list">
        <li style="font-size:13px;">
            <span uk-icon="location"></span>
            <?php echo htmlspecialchars($content['address'] ?? 'Not set'); ?>
        </li>
        <li style="font-size:13px;">
            <span uk-icon="receiver"></span>
            <?php echo htmlspecialchars($content['mobile'] ?? 'Not set'); ?>
        </li>
        <li style="font-size:13px;">
            <span uk-icon="mail"></span>
            <a href="mailto:<?php echo htmlspecialchars($content['email'] ?? ''); ?>" class="uk-link-text">
                <?php echo htmlspecialchars($content['email'] ?? 'Not set'); ?>
            </a>
        </li>
    </ul>
</div>
            

        </div>
    </div>
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
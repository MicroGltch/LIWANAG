<!DOCTYPE html>
<head>
    <meta name="viewport" content="width=device-width" />
    <title>LIWANAG - VERIFY ACCOUNT</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Roboto:wght@100..900&display=swap" rel="stylesheet">

    <!-- UIkit Library -->
    <link rel="stylesheet" href="../../CSS/uikit-3.22.2/css/uikit.min.css" />
    <script src="../../CSS/uikit-3.22.2/js/uikit.min.js"></script>
    <script src="../../CSS/uikit-3.22.2/js/uikit-icons.min.js"></script>

    <!-- LIWANAG CSS -->
    <link rel="stylesheet" href="../../CSS/style.css" type="text/css"/>
</head>
<body>
    <nav class="uk-navbar-container">
        <div class="uk-container">
            <div uk-navbar>
                <div class="uk-navbar-center">
                    <a class="uk-navbar-item uk-logo">Little Wanderer's Therapy Center</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="uk-width-1@s uk-width-1@l">
        <hr>
    </div>

    <div class="uk-flex uk-flex-center uk-flex-middle uk-height-viewport">
        <div class="create-acc-card uk-card uk-card-default uk-card-body form-card">
            <h3 class="uk-card-title uk-flex uk-flex-center">Verify your Email</h3>
            <p class="uk-flex uk-flex-center">Please input the One-Time Password (OTP) sent to your email</p>

            <form id="otp-form" class="uk-form-stacked uk-grid-medium" uk-grid>
                <div class="uk-width-1@s uk-width-1@l">
                    <label class="uk-form-label" for="otp-input">OTP Verification.<br>If you don't see this email in your inbox, check your spam folder.</label>
                    <div class="uk-form-controls">
                        <input class="uk-input" id="otp-input" type="text" name="otp">
                        <span class="error" id="otp-error" style="color: red;"></span>
                    </div>
                </div>
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="verify" class="uk-button uk-button-primary uk-width-1@s">Verify</button>
                </div>
            </form>

            <div class="uk-margin">
                <button id="resend-otp" class="uk-button uk-button-secondary uk-width-1@s" disabled>Resend OTP (1:00)</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
     <script src="../accountJS/otp.js"></script>

</body>
</html>

<?php
session_start(); 
require_once "../../dbconfig.php"; 

// Check if session data exists
if (!isset($_SESSION['email'])) {
    $_SESSION['otp_error'] = "Session expired. Please sign up again.";
    header("Location: ../signuppage.php");
    exit();
}

$email = $_SESSION['email'];
date_default_timezone_set('Asia/Manila');

// First check if account is already active
function checkAccountStatus($connection, $email) {
    $check_active_sql = "SELECT account_Status FROM users WHERE account_Email = ?";
    $stmt = $connection->prepare($check_active_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['account_Status'] === 'Active') {
            return true;
        }
    }
    
    return false;
}

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    $input_otp = trim($_POST['otp']);
    
    try {
        // Check if account is already active
        if (checkAccountStatus($connection, $email)) {
            echo json_encode(['status' => 'success', 'message' => 'Your account is already verified!']);
            exit;
        }
        
        // Verify OTP from otp_verifications table
        $otp_sql = "SELECT * FROM otp_verifications WHERE email = ? AND otp = ? AND expiry_time > NOW() AND used = 0";
        $stmt = $connection->prepare($otp_sql);
        $stmt->bind_param("ss", $email, $input_otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            // Update user status to Active
            $update_sql = "UPDATE users SET account_Status = 'Active' WHERE account_Email = ?";
            $stmt = $connection->prepare($update_sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            // Mark OTP as used
            $update_otp = "UPDATE otp_verifications SET used = 1 WHERE email = ? AND otp = ?";
            $stmt = $connection->prepare($update_otp);
            $stmt->bind_param("ss", $email, $input_otp);
            $stmt->execute();
            
            // Clear OTP from session if exists
            if (isset($_SESSION['otp'])) {
                unset($_SESSION['otp']);
                unset($_SESSION['otp_time']);
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Email verified successfully!']);
        } else {
            // Check if OTP is expired
            $expiry_check_sql = "SELECT expiry_time FROM otp_verifications WHERE email = ? AND otp = ? AND expiry_time <= NOW()";
            $stmt = $connection->prepare($expiry_check_sql);
            $stmt->bind_param("ss", $email, $input_otp);
            $stmt->execute();
            $expiry_result = $stmt->get_result();
            
            if ($expiry_result->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new one.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please try again.']);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Handle OTP resend
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resend_otp'])) {
    try {
        // Check if account is already active
        if (checkAccountStatus($connection, $email)) {
            // Clear any existing OTP data in otp_verifications table
            $update_null_sql = "UPDATE otp_verifications SET otp = NULL, expiry_time = NULL, used = 1 WHERE email = ?";
            $stmt = $connection->prepare($update_null_sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            echo json_encode(['status' => 'success', 'message' => 'Your account is already verified!']);
            exit;
        }
        
        // Generate new OTP
        $new_otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));
        
        // Check if there's an existing OTP record
        $check_sql = "SELECT id FROM otp_verifications WHERE email = ?";
        $stmt = $connection->prepare($check_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing OTP
            $update_sql = "UPDATE otp_verifications SET otp = ?, expiry_time = ?, used = 0 WHERE email = ?";
            $stmt = $connection->prepare($update_sql);
            $stmt->bind_param("sss", $new_otp, $otp_expiry, $email);
            $stmt->execute();
        } else {
            // Insert new OTP
            $insert_sql = "INSERT INTO otp_verifications (email, otp, expiry_time, used) VALUES (?, ?, ?, 0)";
            $stmt = $connection->prepare($insert_sql);
            $stmt->bind_param("sss", $email, $new_otp, $otp_expiry);
            $stmt->execute();
        }
        
        // Get user name for sending email
        $user_sql = "SELECT account_FName, account_LName FROM users WHERE account_Email = ?";
        $stmt = $connection->prepare($user_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user_data = $user_result->fetch_assoc();
        
        $fullname = $user_data['account_FName'] . " " . $user_data['account_LName'];
        
        // Send verification email
        require_once "mail_helper.php";
        // Then use the function as before:
            $mail_result = send_verification($fullname, $email, $new_otp);

            if ($mail_result !== true) {
                $_SESSION['signup_error'] = "Failed to send verification email: " . $mail_result;
                header("Location: ../signuppage.php");
                exit();
            }
        
        if ($mail_result === true) {
            // Store OTP in session too for backup verification method
            $_SESSION['otp'] = $new_otp;
            $_SESSION['otp_time'] = time();
            
            echo json_encode(['status' => 'success', 'message' => 'A new verification code has been sent to your email.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $mail_result]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}
?>

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
                        <input class="uk-input" id="otp-input" type="text" name="otp" maxlength="6">
                        <span class="error" id="otp-error" style="color: red;"></span>
                    </div>
                </div>
                <div class="login-btn-div uk-width-1@s uk-width-1@l">
                    <button type="submit" name="verify" class="uk-button uk-button-primary uk-width-1@s">Verify</button>
                </div>
            </form>
            
            <div class="uk-margin">
                <button id="resend_otp" class="uk-button uk-button-secondary uk-width-1@s" disabled>Resend OTP (1:00)</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
<script>
    // OTP countdown timer
document.addEventListener('DOMContentLoaded', function() {
    const resendButton = document.getElementById('resend_otp');
    const otpForm = document.getElementById('otp-form');
    const otpInput = document.getElementById('otp-input');
    const otpError = document.getElementById('otp-error');
    
    // Start countdown timer for OTP resend
    let seconds = 60;
    const countdownTimer = setInterval(() => {
        seconds--;
        
        if (seconds <= 0) {
            clearInterval(countdownTimer);
            resendButton.disabled = false;
            resendButton.textContent = "Resend OTP";
        } else {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            resendButton.textContent = `Resend OTP (${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds})`;
        }
    }, 1000);
    
    // OTP resend button event
    resendButton.addEventListener('click', function() {
        if (resendButton.disabled) return;
        
        // Disable resend button during request
        resendButton.disabled = true;
        
        // Send AJAX request to resend OTP
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'OTP Resent',
                            text: response.message,
                            confirmButtonColor: '#3085d6'
                        });
                        
                        // Redirect if account is already verified
                        if (response.message.includes('already verified')) {
                            setTimeout(() => {
                                window.location.href = '../loginpage.php';
                            }, 2000);
                            return;
                        }
                        
                        // Reset the countdown timer
                        seconds = 60;
                        const newTimer = setInterval(() => {
                            seconds--;
                            
                            if (seconds <= 0) {
                                clearInterval(newTimer);
                                resendButton.disabled = false;
                                resendButton.textContent = "Resend OTP";
                            } else {
                                const minutes = Math.floor(seconds / 60);
                                const remainingSeconds = seconds % 60;
                                resendButton.textContent = `Resend OTP (${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds})`;
                            }
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#3085d6'
                        });
                        resendButton.disabled = false;
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred',
                        confirmButtonColor: '#3085d6'
                    });
                    resendButton.disabled = false;
                }
            }
        };
        xhr.onerror = function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Network error, please try again',
                confirmButtonColor: '#3085d6'
            });
            resendButton.disabled = false;
        };
        xhr.send('resend_otp=1');
    });
    
    // OTP form submission
    otpForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Clear previous errors
        otpError.textContent = '';
        
        // Validate OTP input
        const otp = otpInput.value.trim();
        if (!otp) {
            otpError.textContent = 'Please enter the OTP code';
            return;
        }
        
        if (!/^\d{6}$/.test(otp)) {
            otpError.textContent = 'OTP must be 6 digits';
            return;
        }
        
        // Submit OTP verification
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonColor: '#3085d6'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Redirect to login page after successful verification
                                window.location.href = '../loginpage.php';
                            }
                        });
                    } else {
                        otpError.textContent = response.message;
                    }
                } catch (e) {
                    otpError.textContent = 'An unexpected error occurred';
                }
            }
        };
        xhr.onerror = function() {
            otpError.textContent = 'Network error, please try again';
        };
        xhr.send(`verify=1&otp=${otp}`);
    });
});
</script>
</html>
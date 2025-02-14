<html>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>

</html>
<?php
session_start();
include "../dbconfig.php";
// Function to display SweetAlert message
function showSweetAlert($title, $text, $icon, $redirect = null) {  // Added optional redirect
    echo "<script>
        Swal.fire({
            title: '$title',
            text: '$text',
            icon: '$icon',
            confirmButtonColor: '#741515'
        }).then(() => {"; // Use .then() for redirect after Swal

    if ($redirect) {
        echo "window.location.href = '$redirect';";
    }

    echo "});
      </script>";
}

    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = $_POST['email'];
        $password = md5($_POST['password']);

        $checkEmail = "SELECT * FROM users WHERE account_Email = '$email'";
        $checkResult = $connection->query($checkEmail);

        if (empty($email) || empty($_POST['password'])) {
            showSweetAlert("Incomplete Fields!", "Please complete the fields to login.", "error", "loginpage.php"); // Redirect using Swal
            exit();
        } else if ($checkResult->num_rows == 0) {
            showSweetAlert("Email Not Found", "Email not found. Please sign up.", "error", "signuppage.php"); // Redirect using Swal
            exit();
        } else {
            $statusCheckSql = "SELECT account_Status FROM users WHERE account_Email = '$email'";
            $statusResult = $connection->query($statusCheckSql);

            if ($statusResult && $row = $statusResult->fetch_assoc()) {
                $status = $row['account_Status'];

                if ($status == 'Active') {
                    $loginsql = "SELECT * FROM users WHERE account_Email = '$email' AND account_Password = '$password'";
                    $loginresult = $connection->query($loginsql);

                    if ($loginresult && $loginresult->num_rows == 1) {
                        $row = $loginresult->fetch_assoc();
                        $fullname = $row['account_FName'] . " " . $row['account_LName'];
                        $_SESSION['username'] = $fullname;
                        echo "<script>window.location.href = '../homepage.php';</script>";
                        exit();
                    } else {
                        showSweetAlert("Invalid Password", "Invalid password.", "error", "loginpage.php"); // Redirect using Swal
                        exit();
                    }
                } elseif ($status == 'Pending') {
                    showSweetAlert("Pending Account", "Your account is pending verification. Please verify your account or sign up again.", "info", "signuppage.php"); // Redirect using Swal
                    exit();
                } else { // Other status (e.g., 'Suspended')
                    showSweetAlert("Account Status", "Your account is " . $status . ". Please contact support.", "warning", "loginpage.php"); // Redirect using Swal
                    exit();
                }
            } else {
                error_log("Error checking account status: " . $connection->error);
                showSweetAlert("Error", "An error occurred. Please try again later.", "error", "loginpage.php");  // Redirect using Swal
                exit();
            }
        }
    } else {
        header("Location: loginpage.php");
        exit();
    }
?>
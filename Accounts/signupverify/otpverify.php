<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="otp.css"> <!--gawa ng css here-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

</head>
<body>
    <div class="container mt-5 w-25 border  border-secondary rounded p-5">
        <div class="row mb-4">
            <div class="col text-center fw-bold">
                <span class="display-2 text-secondary" >An OTP number is sent to your email! </span>
            </div>
        </div>
        <form action="otpverify.php" method=post>
        <!-- Email input -->
        <div class="form-outline mb-4">
            <input type="number" name="otp" id="form2Example1" class="form-control" />
            <label class="form-label" for="form2Example1">OTP Number</label>
        </div>


        <!-- Submit button -->
        <input type="submit" name=sub value="Verify" class="btn btn-primary btn-block mb-4">
        </form>
    </div> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>   
</body>
</html>
<?php
require_once "../LIWANAG/dbconfig.php"; //include database connection

// Verify OTP
if (isset($_POST['sub'])) {
    $otp_user = $_POST['otp'];

    // Use prepared statement to prevent SQL injection
    $otp_sql = "SELECT * FROM users WHERE otp = ?";
    $stmt = $conn->prepare($otp_sql);
    $stmt->bind_param("s", $otp_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // Update user status and clear OTP
        $updatesql = "UPDATE users SET account_Status = 'Active', otp = NULL WHERE otp = ?";
        $stmt = $conn->prepare($updatesql);
        $stmt->bind_param("s", $otp_user);
        $stmt->execute();

        // Redirect to login page
        echo "<script>
            Swal.fire({
                title: 'Congratulations, your account is successfully created.',
                text: 'Do you want to proceed to login?',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#323232',
                cancelButtonColor: '#BABABA',
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'No, stay here'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'loginpage.html';
                }
            });
            </script>";
        exit;
    } else {
        // Invalid OTP
        ?>
        <script>
            Swal.fire({
                position: "center",
                icon: "error",
                title: "Invalid OTP",
                showConfirmButton: false,
                timer: 1500
            });
        </script>
        <?php
    }
}
?>

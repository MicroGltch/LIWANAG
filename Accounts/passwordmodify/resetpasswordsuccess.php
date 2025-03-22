<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width" />
    <title>Password Reset Status</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php
if (isset($_GET['status']) && isset($_GET['message'])) {
    $status = htmlspecialchars($_GET['status']);
    $message = htmlspecialchars($_GET['message']);

    echo "<script>
        Swal.fire({
            icon: '" . ($status == 'success' ? 'success' : 'error') . "',
            title: '" . ($status == 'success' ? 'Success' : 'Error') . "',
            text: '" . $message . "',
            allowOutsideClick: false
        }).then((result) => {
            window.location.href = '../loginpage.php'; // Redirect to login
        });
    </script>";
} else {
    // Handle cases where status or message are missing (optional)
    echo "<script>
        window.location.href = '../loginpage.php'; // Redirect to login
    </script>";
}
?>

</body>
</html>
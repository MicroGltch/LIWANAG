<?php
session_start();

// Destroy session
session_unset();
session_destroy();

// Clear "Remember Me" stored credentials
echo "<script>
    localStorage.removeItem('rememberedEmail');
    localStorage.removeItem('rememberedPassword');
    localStorage.removeItem('remembered');
    window.location.href = 'loginpage.php';
</script>";
exit();
?>

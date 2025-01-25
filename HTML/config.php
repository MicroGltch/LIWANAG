<?php
// Database configuration
$servername = "localhost";
$username = "u999302509_liwanag_user";
$password = "1f&KyP2Re;#Y"; // Replace with your actual password
$database = "u999302509_liwanag_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}else{
    echo "you are connected";
}
?>



<?php
// Database configuration
$servername = "153.92.15.39"; 
$username = "u999302509_liwanag_user";
$password = "Jan272025!"; 
$database = "u999302509_liwanag_db";

$connection = new mysqli($servername, $username, $password, $database);

// Database Connection Check
// if ($connection->connect_error) {
//     echo "<script>console.error('Connection failed: " . addslashes($conn->connect_error) . "');</script>";
// } else {
//     echo "<script>console.log('You are connected to the database.');</script>";
// }

?>
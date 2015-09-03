<?php
$servername = "";
$username = "";
$password = "";
$table = "";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $table);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
//echo "Connected successfully";
?>
<?php
$host = 'localhost';
$user = 'root';
$password = ''; // Default XAMPP MySQL has no password
$dbname = 'unilis';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

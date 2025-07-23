<?php
$mysqli = new mysqli("localhost", "root", "", "keyauth");
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}
?>

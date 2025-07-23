<?php
// Load DB config
require_once __DIR__ . '/config.php';

// Connect to DB using config variables
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

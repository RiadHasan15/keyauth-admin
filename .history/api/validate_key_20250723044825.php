<?php
header("Content-Type: application/json");

// DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "keyauth";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);
$app_id = $data['app_id'] ?? '';
$api_token = $data['api_token'] ?? '';
$license_key = $data['license_key'] ?? '';

// Debug log
$log = "Received:\nApp ID: $app_id\nAPI Token: $api_token\nLicense Key: $license_key\n";

// Validate app
$stmt = $conn->prepare("SELECT * FROM apps WHERE id = ?");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$result = $stmt->get_result();
$app = $result->fetch_assoc();

if (!$app) {
    file_put_contents(__DIR__ . "/debug.log", $log . "Error: App not found\n\n", FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Invalid app ID"]);
    exit;
}

// Check API token
$log .= "DB Token: " . $app['api_token'] . "\n";
if ($api_token !== $app['api_token']) {
    file_put_contents(__DIR__ . "/debug.log", $log . "Error: Invalid API token\n\n", FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Invalid API token"]);
    exit;
}

// Validate license key
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE `key` = ? AND app = ?");
$stmt->bind_param("ss", $license_key, $app['name']);
$stmt->execute();
$key_result = $stmt->get_result();
$key = $key_result->fetch_assoc();

if (!$key) {
    file_put_contents(__DIR__ . "/debug.log", $log . "Error: License key not found\n\n", FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Invalid license key"]);
    exit;
}

// Success
file_put_contents(__DIR__ . "/debug.log", $log . "Success: License key is valid\n\n", FILE_APPEND);
echo json_encode(["success" => true, "message" => "License key is valid"]);
?>

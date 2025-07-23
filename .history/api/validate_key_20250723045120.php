<?php
// validate_key.php

header('Content-Type: application/json');

$log_file = '../logs/validation.log';
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON"]);
    file_put_contents($log_file, "Invalid JSON received: $rawData\n", FILE_APPEND);
    exit;
}

$app_id = trim($data['app_id'] ?? '');
$api_token = trim($data['api_token'] ?? '');
$license_key = trim($data['license_key'] ?? '');

if (empty($app_id) || empty($api_token) || empty($license_key)) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$conn = new mysqli("localhost", "root", "", "keyauth");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

// Verify app & token
$stmt = $conn->prepare("SELECT * FROM apps WHERE name = ? AND api_token = ?");
$stmt->bind_param("ss", $app_id, $api_token);
$stmt->execute();
$app_result = $stmt->get_result();

if ($app_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Invalid API token"]);
    file_put_contents($log_file, "[$app_id] Invalid token: $api_token\n", FILE_APPEND);
    exit;
}

// Validate license key
$stmt2 = $conn->prepare("SELECT * FROM license_keys WHERE `key` = ? AND app_name = ?");
$stmt2->bind_param("ss", $license_key, $app_id);
$stmt2->execute();
$key_result = $stmt2->get_result();

if ($key_result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Invalid or inactive license key"]);
    file_put_contents($log_file, "[$app_id] Invalid key: $license_key\n", FILE_APPEND);
    exit;
}

$key = $key_result->fetch_assoc();

if ($key['status'] !== 'Active') {
    echo json_encode(["success" => false, "message" => "Key is not active"]);
    file_put_contents($log_file, "[$app_id] Inactive key: $license_key\n", FILE_APPEND);
    exit;
}

echo json_encode(["success" => true, "message" => "Key is valid"]);
file_put_contents($log_file, "[$app_id] Validated key: $license_key\n", FILE_APPEND);

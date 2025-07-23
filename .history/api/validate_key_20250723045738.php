<?php
// api/validate_key.php

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['app_id'], $data['api_token'], $data['license_key'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$appId = $data['app_id'];
$apiToken = $data['api_token'];
$licenseKey = $data['license_key'];

// Connect to DB
$conn = new mysqli("localhost", "root", "", "keyauth_panel");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

// Check app + token match
$stmt = $conn->prepare("SELECT * FROM apps WHERE name = ? AND api_token = ?");
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API token']);
    exit;
}

// Validate key
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE `key` = ? AND app_name = ?");
$stmt->bind_param("ss", $licenseKey, $appId);
$stmt->execute();
$keyResult = $stmt->get_result();

if ($keyResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid license key']);
} else {
    $keyData = $keyResult->fetch_assoc();
    echo json_encode(['success' => true, 'message' => 'Key is valid', 'data' => $keyData]);
}

// Log the request
file_put_contents('../logs/api_log.txt', date('Y-m-d H:i:s') . ' ' . json_encode($data) . PHP_EOL, FILE_APPEND);

$conn->close();
?>

<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Allow preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['app_id'], $input['api_token'], $input['license_key'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$appId = $input['app_id'];
$apiToken = $input['api_token'];
$licenseKey = $input['license_key'];
$hwid = isset($input['hwid']) ? $input['hwid'] : '';
$ip = $_SERVER['REMOTE_ADDR'];

// Database connection
$mysqli = new mysqli("localhost", "root", "", "keyauth");

if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Step 1: Verify API token for app
$stmt = $mysqli->prepare("SELECT id FROM apps WHERE app_id = ? AND api_token = ?");
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid app_id or api_token.']);
    exit;
}
$stmt->close();

// Step 2: Verify license key and get its ID
$stmt = $mysqli->prepare("SELECT id FROM license_keys WHERE license_key = ? AND app_id = ?");
$stmt->bind_param("ss", $licenseKey, $appId);
$stmt->execute();
$stmt->bind_result($licenseKeyId);
$stmt->fetch();
$stmt->close();

if (!$licenseKeyId) {
    echo json_encode(['success' => false, 'message' => 'License key not found or does not belong to this app.']);
    exit;
}

// Step 3: Insert into hwid_logs using correct licenseKeyId
$stmt = $mysqli->prepare("INSERT INTO hwid_logs (app_id, key_id, hwid, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("siss", $appId, $licenseKeyId, $hwid, $ip);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'License validated and logged.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to insert HWID log.']);
}
$stmt->close();
$mysqli->close();
?>

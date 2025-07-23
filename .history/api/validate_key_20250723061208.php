<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../db.php'; // Your $mysqli connection

function log_msg($msg) {
    $logfile = __DIR__ . '/../logs/api_log.txt';
    file_put_contents($logfile, date('Y-m-d H:i:s') . " " . $msg . "\n", FILE_APPEND);
}

$input = file_get_contents('php://input');
log_msg("Received raw input: " . $input);

$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    log_msg("Invalid JSON");
    exit;
}

if (!isset($data['app_id'], $data['api_token'], $data['license_key'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    log_msg("Missing parameters");
    exit;
}

$appId = $data['app_id'];
$apiToken = $data['api_token'];
$licenseKey = $data['license_key'];

log_msg("Checking app: $appId, token: $apiToken, key: $licenseKey");

// Validate app and token
$stmt = $mysqli->prepare("SELECT id FROM apps WHERE name = ? AND api_token = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
    log_msg("Prepare failed: " . $mysqli->error);
    exit;
}
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid app or API token']);
    log_msg("Invalid app or API token");
    exit;
}
$app = $result->fetch_assoc();
$appDbId = $app['id'];

// Validate license key
$stmt = $mysqli->prepare("SELECT * FROM license_keys WHERE license_key = ? AND app_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
    log_msg("Prepare failed: " . $mysqli->error);
    exit;
}
$stmt->bind_param("si", $licenseKey, $appDbId);
$stmt->execute();
$keyResult = $stmt->get_result();

if ($keyResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'License key not found']);
    log_msg("License key not found");
    exit;
}

$keyData = $keyResult->fetch_assoc();

// Check expiration
if (!empty($keyData['expires_at']) && $keyData['expires_at'] < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'License key expired']);
    log_msg("License key expired");
    exit;
}

// Success response
echo json_encode([
    'success' => true,
    'message' => 'License key is valid',
    'data' => [
        'license_key' => $keyData['license_key'],
        'expires_at' => $keyData['expires_at'],
    ]
]);

log_msg("License validated successfully: " . $keyData['license_key']);

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$logfile = __DIR__ . '/../logs/api_log.txt';

function log_msg($msg) {
    global $logfile;
    file_put_contents($logfile, date('Y-m-d H:i:s') . " " . $msg . "\n", FILE_APPEND);
}

// Read and log raw input
$input = file_get_contents('php://input');
log_msg("Received raw input: " . $input);

$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    log_msg("Invalid JSON");
    exit;
}

// Check required fields
if (!isset($data['app_id'], $data['api_token'], $data['license_key'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    log_msg("Missing parameters");
    exit;
}

$appId = $data['app_id'];
$apiToken = $data['api_token'];
$licenseKey = $data['license_key'];

log_msg("Checking app: $appId, token: $apiToken, key: $licenseKey");

// Connect to DB
$conn = new mysqli("localhost", "root", "", "keyauth");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    log_msg("DB connection failed: " . $conn->connect_error);
    exit;
}

// Validate app & api token
$stmt = $conn->prepare("SELECT * FROM apps WHERE name = ? AND api_token = ?");
if (!$stmt) {
    log_msg("Prepare apps statement failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    log_msg("Execute apps statement failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

log_msg("Apps query returned rows: " . $result->num_rows);

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API token or app']);
    log_msg("Invalid API token or app");
    exit;
}

// Validate license key
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE `key` = ? AND app_name = ?");

if (!$stmt) {
    log_msg("Prepare license_keys statement failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
$stmt->bind_param("ss", $licenseKey, $appId);
$stmt->execute();
$keyResult = $stmt->get_result();
if (!$keyResult) {
    log_msg("Execute license_keys statement failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

log_msg("License keys query returned rows: " . $keyResult->num_rows);

if ($keyResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'License key not found']);
    log_msg("License key not found");
    exit;
}

$keyData = $keyResult->fetch_assoc();

log_msg("License key status: " . $keyData['status']);

if ($keyData['status'] !== 'Active') {
    echo json_encode(['success' => false, 'message' => 'License key is not active']);
    log_msg("License key is not active");
    exit;
}

// Success response
echo json_encode([
    'success' => true,
    'message' => 'License key is valid',
    'data' => [
        'key' => $keyData['key'],
        'status' => $keyData['status'],
        'hwid' => $keyData['hwid'],
        'expires' => $keyData['expires'] ?? null,
    ]
]);
log_msg("License validated successfully");

// Close connection
$conn->close();
exit;

<?php
ob_start();

ini_set('display_errors', 0); // don't display errors to client
ini_set('log_errors', 1);     // enable error logging
ini_set('error_log', __DIR__ . '/../logs/php_error.log'); // your error log file path
error_reporting(E_ALL);       // report all errors

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "keyauth";

$logfile = __DIR__ . '/../logs/api_log.txt';

function log_msg($msg) {
    global $logfile;
    file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

function send_response($arr) {
    ob_clean();
    echo json_encode($arr);
    exit;
}

// Read raw input
$input = file_get_contents('php://input');
log_msg("Incoming request: " . $input);
$data = json_decode($input, true);

if (!$data) {
    log_msg("Invalid JSON received.");
    send_response(['success' => false, 'message' => 'Invalid JSON']);
}

if (!isset($data['app_id'], $data['api_token'], $data['license_key'])) {
    log_msg("Missing required parameters.");
    send_response(['success' => false, 'message' => 'Missing parameters']);
}

$appId = $data['app_id'];
$apiToken = $data['api_token'];
$licenseKey = $data['license_key'];
$hwid = $data['hwid'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

log_msg("Validating - App: $appId | Token: $apiToken | Key: $licenseKey | HWID: $hwid | IP: $ip");

// Connect to DB
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    log_msg("DB connection failed: " . $conn->connect_error);
    send_response(['success' => false, 'message' => 'Database connection failed']);
}

// Validate app
$stmt = $conn->prepare("SELECT id FROM apps WHERE name = ? AND api_token = ?");
if (!$stmt) {
    log_msg("Prepare failed (apps): " . $conn->error);
    send_response(['success' => false, 'message' => 'Internal error']);
}
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$appResult = $stmt->get_result();

if ($appResult->num_rows === 0) {
    log_msg("Invalid app ID or API token.");
    send_response(['success' => false, 'message' => 'Invalid API token or app']);
}

$appData = $appResult->fetch_assoc();
$appDbId = $appData['id'];

// Validate license key
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE license_key = ? AND app_id = ?");
if (!$stmt) {
    log_msg("Prepare failed (license_keys): " . $conn->error);
    send_response(['success' => false, 'message' => 'Internal error']);
}
$stmt->bind_param("si", $licenseKey, $appDbId);
$stmt->execute();
$keyResult = $stmt->get_result();

if ($keyResult->num_rows === 0) {
    log_msg("License key not found.");
    send_response(['success' => false, 'message' => 'License key not found']);
}

$keyData = $keyResult->fetch_assoc();

// Check bans
if ((int)$keyData['banned'] === 1) {
    log_msg("License key is banned.");
    send_response(['success' => false, 'message' => 'License key is banned']);
}

if ((int)$keyData['hwid_banned'] === 1) {
    log_msg("HWID is banned for key: $licenseKey");
    send_response(['success' => false, 'message' => 'HWID is banned']);
}

// HWID binding
if ($hwid !== null) {
    if (empty($keyData['hwid'])) {
        $stmt = $conn->prepare("UPDATE license_keys SET hwid = ? WHERE id = ?");
        if (!$stmt) {
            log_msg("Prepare failed (update hwid): " . $conn->error);
            send_response(['success' => false, 'message' => 'Internal error']);
        }
        $stmt->bind_param("si", $hwid, $keyData['id']);
        $stmt->execute();
        log_msg("HWID bound to key: $licenseKey");
    } elseif ($keyData['hwid'] !== $hwid) {
        log_msg("HWID mismatch. Expected: {$keyData['hwid']} | Given: $hwid");
        send_response(['success' => false, 'message' => 'HWID mismatch']);
    }
}

// Log HWID usage
if (empty($keyData['id'])) {
    log_msg("Invalid license key ID before inserting hwid log.");
    send_response(['success' => false, 'message' => 'Internal error']);
}
$hwidForLog = $hwid ?? '';

$stmt = $conn->prepare("INSERT INTO hwid_logs (key_id, hwid, ip_address) VALUES (?, ?, ?)");

if (!$stmt) {
    log_msg("Prepare failed (hwid_logs): " . $conn->error);
    send_response(['success' => false, 'message' => 'Internal error']);
}
$stmt->bind_param("iss", $keyData['id'], $hwidForLog, $ip);
$stmt->execute();

// Success response
$response = [
    'success' => true,
    'message' => 'License key is valid',
    'data' => [
        'license_key' => $keyData['license_key'],
        'status' => $keyData['banned'] ? 'Banned' : 'Active',
        'hwid' => $keyData['hwid'],
        'expires_at' => $keyData['expires_at']
    ]
];

log_msg("Key validated successfully: $licenseKey");

ob_clean();
echo json_encode($response);
$conn->close();
exit;

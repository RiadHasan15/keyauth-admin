<?php
ob_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

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

// Use the database connection from db.php
$conn = $mysqli;
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

// Debug: Log the key data
log_msg("Key data retrieved: " . json_encode($keyData));

// Check key ID validity
if (empty($keyData['id']) || !is_numeric($keyData['id'])) {
    log_msg("Invalid license key ID: " . ($keyData['id'] ?? 'null'));
    send_response(['success' => false, 'message' => 'Invalid license key data']);
}

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

// --- New Activation Limit Enforcement ---

$activationLimit = (int)($keyData['activation_limit'] ?? 1);
$licenseKeyId = (int)$keyData['id'];
$hwidForLog = $hwid ?? '';

if ($hwid !== null && $activationLimit > 0) {

    // Count unique activated HWIDs for this license key
    $stmt = $conn->prepare("SELECT COUNT(*) AS hwid_count FROM license_activations WHERE license_key_id = ?");
    if (!$stmt) {
        log_msg("Prepare failed (count activations): " . $conn->error);
        send_response(['success' => false, 'message' => 'Internal error']);
    }
    $stmt->bind_param("i", $licenseKeyId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $currentActivations = (int)$result['hwid_count'];

    // Check if this HWID is already activated
    $stmt = $conn->prepare("SELECT 1 FROM license_activations WHERE license_key_id = ? AND hwid = ? LIMIT 1");
    if (!$stmt) {
        log_msg("Prepare failed (check hwid exists): " . $conn->error);
        send_response(['success' => false, 'message' => 'Internal error']);
    }
    $stmt->bind_param("is", $licenseKeyId, $hwidForLog);
    $stmt->execute();
    $hwidExists = $stmt->get_result()->num_rows > 0;

    if (!$hwidExists) {
        if ($currentActivations >= $activationLimit) {
            log_msg("Activation limit reached for license key $licenseKey. Limit: $activationLimit");
            send_response(['success' => false, 'message' => 'Activation limit reached']);
        } else {
            // Insert new activation record
            $stmt = $conn->prepare("INSERT INTO license_activations (license_key_id, hwid) VALUES (?, ?)");
            if (!$stmt) {
                log_msg("Prepare failed (insert activation): " . $conn->error);
                send_response(['success' => false, 'message' => 'Internal error']);
            }
            $stmt->bind_param("is", $licenseKeyId, $hwidForLog);
            $stmt->execute();
            log_msg("New HWID activated for license key $licenseKey: $hwidForLog");
        }
    }
}

// Log HWID usage in hwid_logs as well (optional, for full history)
$stmt = $conn->prepare("INSERT INTO hwid_logs (license_key_id, hwid, ip_address) VALUES (?, ?, ?)");
if (!$stmt) {
    log_msg("Prepare failed (hwid_logs): " . $conn->error);
    send_response(['success' => false, 'message' => 'Internal error']);
}
$stmt->bind_param("iss", $licenseKeyId, $hwidForLog, $ip);
$stmt->execute();

$response = [
    'success' => true,
    'message' => 'License key is valid',
    'data' => [
        'license_key' => $keyData['license_key'],
        'status' => $keyData['banned'] ? 'Banned' : 'Active',
        'hwid' => $keyData['hwid'],
        'expires_at' => $keyData['expires_at'],
        'activation_limit' => $activationLimit
    ]
];

log_msg("Key validated successfully: $licenseKey");

ob_clean();
echo json_encode($response);
$conn->close();
exit;

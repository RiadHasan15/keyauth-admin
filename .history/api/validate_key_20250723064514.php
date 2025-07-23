<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

$logfile = __DIR__ . '/../logs/api_log.txt';

function log_msg($msg) {
    global $logfile;
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
$hwid = $data['hwid'] ?? null;

log_msg("Checking app: $appId, token: $apiToken, key: $licenseKey, hwid: $hwid");

$conn = new mysqli("localhost", "root", "", "keyauth");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    log_msg("DB connection failed: " . $conn->connect_error);
    exit;
}

// Validate app & api token
$stmt = $conn->prepare("SELECT * FROM apps WHERE name = ? AND api_token = ?");
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid API token or app']);
    log_msg("Invalid API token or app");
    exit;
}

// Validate license key
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE license_key = ? AND app_id = (SELECT id FROM apps WHERE name = ?)");
$stmt->bind_param("ss", $licenseKey, $appId);
$stmt->execute();
$keyResult = $stmt->get_result();
if ($keyResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'License key not found']);
    log_msg("License key not found");
    exit;
}

$keyData = $keyResult->fetch_assoc();

// Check banned status
if ((int)$keyData['banned'] === 1) {
    echo json_encode(['success' => false, 'message' => 'License key is banned']);
    log_msg("License key is banned");
    exit;
}
if ((int)$keyData['hwid_banned'] === 1) {
    echo json_encode(['success' => false, 'message' => 'HWID is banned']);
    log_msg("HWID is banned");
    exit;
}

// HWID binding logic
if ($hwid !== null) {
    if (empty($keyData['hwid'])) {
        // Bind HWID to key if none set yet
        $stmt = $conn->prepare("UPDATE license_keys SET hwid = ? WHERE id = ?");
        $stmt->bind_param("si", $hwid, $keyData['id']);
        $stmt->execute();
        log_msg("HWID bound to key {$keyData['license_key']}");
    } else {
        // Check HWID match
        if ($keyData['hwid'] !== $hwid) {
            echo json_encode(['success' => false, 'message' => 'HWID mismatch']);
            log_msg("HWID mismatch for key {$keyData['license_key']}");
            exit;
        }
    }
}

// Log HWID usage
$stmt = $conn->prepare("INSERT INTO hwid_logs (license_key_id, hwid, ip_address) VALUES (?, ?, ?)");
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$stmt->bind_param("iss", $keyData['id'], $hwid, $ip);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'License key is valid',
    'data' => [
        'license_key' => $keyData['license_key'],
        'status' => $keyData['banned'] ? 'Banned' : 'Active',
        'hwid' => $keyData['hwid'],
        'expires_at' => $keyData['expires_at']
    ]
]);
log_msg("License validated successfully");

$conn->close();
exit;

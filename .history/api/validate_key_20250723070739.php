<?php
// Set JSON header immediately to avoid accidental output before headers
header('Content-Type: application/json; charset=utf-8');

// Disable displaying errors to users (use logs instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log file path
$logfile = __DIR__ . '/../logs/api_log.txt';

// Log function
function log_msg(string $msg): void {
    global $logfile;
    file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

// Send JSON response and exit cleanly
function send_response(array $response): void {
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Read and decode input JSON
$input = file_get_contents('php://input');
log_msg("Incoming request: $input");

$data = json_decode($input, true);
if ($data === null) {
    log_msg("Invalid JSON received");
    send_response(['success' => false, 'message' => 'Invalid JSON']);
}

// Validate required parameters
if (empty($data['app_id']) || empty($data['api_token']) || empty($data['license_key'])) {
    log_msg("Missing required parameters");
    send_response(['success' => false, 'message' => 'Missing parameters']);
}

$appId = $data['app_id'];
$apiToken = $data['api_token'];
$licenseKey = $data['license_key'];
$hwid = $data['hwid'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Database config
$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "keyauth";

// Connect DB with error handling
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_errno) {
    log_msg("DB connection failed: {$conn->connect_error}");
    send_response(['success' => false, 'message' => 'Database connection failed']);
}

// Validate app & token
$stmt = $conn->prepare("SELECT id FROM apps WHERE name = ? AND api_token = ?");
if (!$stmt) {
    log_msg("Prepare failed: {$conn->error}");
    send_response(['success' => false, 'message' => 'Server error']);
}
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$appResult = $stmt->get_result();
if ($appResult->num_rows === 0) {
    log_msg("Invalid app or API token for app: $appId");
    send_response(['success' => false, 'message' => 'Invalid API token or app']);
}
$appData = $appResult->fetch_assoc();
$appDbId = $appData['id'];
$stmt->close();

// Validate license key
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE license_key = ? AND app_id = ?");
if (!$stmt) {
    log_msg("Prepare failed: {$conn->error}");
    send_response(['success' => false, 'message' => 'Server error']);
}
$stmt->bind_param("si", $licenseKey, $appDbId);
$stmt->execute();
$keyResult = $stmt->get_result();
if ($keyResult->num_rows === 0) {
    log_msg("License key not found: $licenseKey");
    send_response(['success' => false, 'message' => 'License key not found']);
}
$keyData = $keyResult->fetch_assoc();
$stmt->close();

// Check banned statuses
if ((int)$keyData['banned'] === 1) {
    log_msg("License key banned: $licenseKey");
    send_response(['success' => false, 'message' => 'License key is banned']);
}
if ((int)$keyData['hwid_banned'] === 1) {
    log_msg("HWID banned for key: $licenseKey");
    send_response(['success' => false, 'message' => 'HWID is banned']);
}

// HWID binding/check
if ($hwid !== null) {
    if (empty($keyData['hwid'])) {
        $stmt = $conn->prepare("UPDATE license_keys SET hwid = ? WHERE id = ?");
        if (!$stmt) {
            log_msg("Prepare failed for HWID bind: {$conn->error}");
            send_response(['success' => false, 'message' => 'Server error']);
        }
        $stmt->bind_param("si", $hwid, $keyData['id']);
        $stmt->execute();
        $stmt->close();
        log_msg("HWID bound to key: $licenseKey");
    } else if ($keyData['hwid'] !== $hwid) {
        log_msg("HWID mismatch for key $licenseKey. Expected: {$keyData['hwid']}, got: $hwid");
        send_response(['success' => false, 'message' => 'HWID mismatch']);
    }
}

// Log HWID usage (ignore errors here)
$stmt = $conn->prepare("INSERT INTO hwid_logs (license_key_id, hwid, ip_address) VALUES (?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("iss", $keyData['id'], $hwid, $ip);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// Success response
$response = [
    'success' => true,
    'message' => 'License key is valid',
    'data' => [
        'license_key' => $keyData['license_key'],
        'status' => (int)$keyData['banned'] === 1 ? 'Banned' : 'Active',
        'hwid' => $keyData['hwid'],
        'expires_at' => $keyData['expires_at']
    ]
];

log_msg("Key validated successfully: $licenseKey");
send_response($response);

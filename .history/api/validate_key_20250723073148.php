<?php
ob_start();





header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(0);

$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "keyauth";

$logfile = __DIR__ . '/../logs/api_log.txt';

function log_msg($msg) {
    global $logfile;
    file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

// Read raw input
$input = file_get_contents('php://input');
log_msg("Incoming request: " . $input);
$data = json_decode($input, true);

if (!$data) {
    log_msg("Invalid JSON received.");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

if (!isset($data['app_id'], $data['api_token'], $data['license_key'])) {
    log_msg("Missing required parameters.");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
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
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Validate app
$stmt = $conn->prepare("SELECT id FROM apps WHERE name = ? AND api_token = ?");
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$appResult = $stmt->get_result();

if ($appResult->num_rows === 0) {
    log_msg("Invalid app ID or API token.");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid API token or app']);
    exit;
}

$appData = $appResult->fetch_assoc();
$appDbId = $appData['id'];

// Validate license key
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE license_key = ? AND app_id = ?");
$stmt->bind_param("si", $licenseKey, $appDbId);
$stmt->execute();
$keyResult = $stmt->get_result();

if ($keyResult->num_rows === 0) {
    log_msg("License key not found.");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'License key not found']);
    exit;
}

$keyData = $keyResult->fetch_assoc();

// Check bans
if ((int)$keyData['banned'] === 1) {
    log_msg("License key is banned.");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'License key is banned']);
    exit;
}

if ((int)$keyData['hwid_banned'] === 1) {
    log_msg("HWID is banned for key: $licenseKey");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'HWID is banned']);
    exit;
}

// HWID binding
if ($hwid !== null) {
    if (empty($keyData['hwid'])) {
        $stmt = $conn->prepare("UPDATE license_keys SET hwid = ? WHERE id = ?");
        $stmt->bind_param("si", $hwid, $keyData['id']);
        $stmt->execute();
        log_msg("HWID bound to key: $licenseKey");
    } elseif ($keyData['hwid'] !== $hwid) {
        log_msg("HWID mismatch. Expected: {$keyData['hwid']} | Given: $hwid");
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'HWID mismatch']);
        exit;
    }
}

// Log HWID usage
$stmt = $conn->prepare("INSERT INTO hwid_logs (license_key_id, hwid, ip_address) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $keyData['id'], $hwid, $ip);
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

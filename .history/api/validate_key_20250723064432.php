<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// === CONFIG ===
$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "keyauth";

$logfile = __DIR__ . '/../logs/api_log.txt';

// === LOG FUNCTION ===
function log_msg($msg) {
    global $logfile;
    file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

// === GET RAW INPUT ===
$input = file_get_contents('php://input');
log_msg("Incoming JSON: " . $input);
$data = json_decode($input, true);

// === BASIC CHECK ===
if (!$data) {
    log_msg("Invalid JSON received.");
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

if (!isset($data['app_id'], $data['api_token'], $data['license_key'])) {
    log_msg("Missing required parameters.");
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$appId = $data['app_id'];
$apiToken = $data['api_token'];
$licenseKey = $data['license_key'];
$hwid = $data['hwid'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

log_msg("Validating - App: $appId | Token: $apiToken | Key: $licenseKey | HWID: $hwid | IP: $ip");

// === CONNECT DB ===
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    log_msg("DB connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// === VALIDATE APP & TOKEN ===
$stmt = $conn->prepare("SELECT id FROM apps WHERE name = ? AND api_token = ?");
$stmt->bind_param("ss", $appId, $apiToken);
$stmt->execute();
$appResult = $stmt->get_result();
if ($appResult->num_rows === 0) {
    log_msg("Invalid app ID or API token.");
    echo json_encode(['success' => false, 'message' => 'Invalid API token or app']);
    exit;
}
$appData = $appResult->fetch_assoc();
$appDbId = $appData['id'];

// === VALIDATE LICENSE KEY ===
$stmt = $conn->prepare("SELECT * FROM license_keys WHERE license_key = ? AND app_id = ?");
$stmt->bind_param("si", $licenseKey, $appDbId);
$stmt->execute();
$keyResult = $stmt->get_result();
if ($keyResult->num_rows === 0) {
    log_msg("License key not found.");
    echo json_encode(['success' => false, 'message' => 'License key not found']);
    exit;
}
$keyData = $keyResult->fetch_assoc();

// === BAN CHECKS ===
if ((int)$keyData['banned'] === 1) {
    log_msg("License key is banned.");
    echo json_encode(['success' => false, 'message' => 'License key is banned']);
    exit;
}
if ((int)$keyData['hwid_banned'] === 1) {
    log_msg("HWID is banned for key: $licenseKey");
    echo json_encode(['success' => false, 'message' => 'HWID is banned']);
    exit;
}

// === HWID BINDING ===
if ($hwid !== null) {
    if (empty($keyData['hwid'])) {
        // Bind HWID if not set
        $stmt = $conn->prepare("UPDATE license_keys SET hwid = ? WHERE id = ?");
        $stmt->bind_param("si", $hwid, $keyData['id']);
        $stmt->execute();
        log_msg("HWID bound to key: $licenseKey");
    } elseif ($keyData['hwid'] !== $hwid) {
        log_msg("HWID mismatch. Expected: {$keyData['hwid']} | Given: $hwid");
        echo json_encode(['success' => false, 'message' => 'HWID mismatch']);
        exit;
    }
}

// === LOG HWID USAGE ===
$stmt = $conn->prepare("INSERT INTO hwid_logs (license_key_id, hwid, ip_address) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $keyData['id'], $hwid, $ip);
$stmt->execute();

// === SUCCESS RESPONSE ===
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
echo json_encode($response);
$conn->close();
exit;

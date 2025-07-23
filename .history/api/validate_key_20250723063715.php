<?php
header('Content-Type: application/json');
require_once '../db.php';  // Adjust path if needed

// Read JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['key'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$keyInput = $data['key'];
$hwidInput = $data['hwid'] ?? null;
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Fetch license key data
$stmt = $mysqli->prepare("SELECT * FROM license_keys WHERE license_key = ?");
$stmt->bind_param("s", $keyInput);
$stmt->execute();
$result = $stmt->get_result();
$keyData = $result->fetch_assoc();

if (!$keyData) {
    echo json_encode(['success' => false, 'message' => 'License key not found']);
    exit;
}

// Check if license is banned
if ((int)$keyData['banned'] === 1) {
    echo json_encode(['success' => false, 'message' => 'License key is banned']);
    exit;
}

// Check if HWID is banned
if ((int)$keyData['hwid_banned'] === 1) {
    echo json_encode(['success' => false, 'message' => 'HWID is banned']);
    exit;
}

// Check expiration if applicable
if (!empty($keyData['expires_at']) && $keyData['expires_at'] !== '0000-00-00') {
    $today = date('Y-m-d');
    if ($today > $keyData['expires_at']) {
        echo json_encode(['success' => false, 'message' => 'License key has expired']);
        exit;
    }
}

// HWID binding and validation
if ($hwidInput) {
    if (empty($keyData['hwid'])) {
        // Bind HWID to key
        $updateStmt = $mysqli->prepare("UPDATE license_keys SET hwid = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hwidInput, $keyData['id']);
        $updateStmt->execute();
    } else if ($keyData['hwid'] !== $hwidInput) {
        // Mismatch: reject
        echo json_encode(['success' => false, 'message' => 'HWID mismatch']);
        exit;
    }
}

// Log HWID usage if HWID provided
if ($hwidInput) {
    $logStmt = $mysqli->prepare("INSERT INTO hwid_logs (key_id, hwid, ip) VALUES (?, ?, ?)");
    $logStmt->bind_param("iss", $keyData['id'], $hwidInput, $clientIp);
    $logStmt->execute();
}

// If all checks pass, return success and key data
$responseData = [
    'key' => $keyData['license_key'],
    'status' => 'Active',
    'hwid' => $keyData['hwid'],
    'expires_at' => $keyData['expires_at'],
];

echo json_encode([
    'success' => true,
    'message' => 'License key is valid',
    'data' => $responseData
]);

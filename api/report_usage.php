<?php
require_once __DIR__ . '/../functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Parse JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (
    !$data ||
    !isset($data['app_id'], $data['api_token'], $data['license_key'], $data['hwid'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Validate API token for app
$app = validateApiToken($data['app_id'], $data['api_token']);
if (!$app) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API token']);
    exit;
}

$keys = loadJson(__DIR__ . '/../data/keys.json');
$updated = false;

foreach ($keys as &$key) {
    if ($key['key'] === $data['license_key'] && $key['app_id'] === $data['app_id']) {
        if (!($key['banned'] ?? false)) {
            $key['hwid'] = $data['hwid'];
            $updated = true;
            logAction("HWID updated for key {$key['key']} to {$data['hwid']}");
        }
        break;
    }
}

if ($updated) {
    saveJson(__DIR__ . '/../data/keys.json', $keys);
    echo json_encode(['success' => true, 'message' => 'HWID updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Key not found or banned']);
}

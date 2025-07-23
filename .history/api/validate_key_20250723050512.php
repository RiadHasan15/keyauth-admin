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
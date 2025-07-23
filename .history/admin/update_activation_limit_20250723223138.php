<?php
// update_activation_limit.php (debug version)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Debug: Log the request
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);
file_put_contents('debug.log', "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

try {
    require_once __DIR__ . '/../functions.php';
    file_put_contents('debug.log', "functions.php loaded successfully\n", FILE_APPEND);
    
    requireLogin();
    file_put_contents('debug.log', "Login check passed\n", FILE_APPEND);
    
    require_once __DIR__ . '/../db.php';
    file_put_contents('debug.log', "db.php loaded successfully\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents('debug.log', "Error loading files: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'File loading error: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$licenseKeyId = isset($_POST['license_key_id']) ? intval($_POST['license_key_id']) : 0;
$activationLimit = isset($_POST['activation_limit']) ? intval($_POST['activation_limit']) : -1;

file_put_contents('debug.log', "License Key ID: $licenseKeyId, Activation Limit: $activationLimit\n", FILE_APPEND);

if ($licenseKeyId <= 0 || $activationLimit < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

if (!isset($mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE license_keys SET activation_limit = ? WHERE id = ?");
if (!$stmt) {
    $error = 'Prepare failed: ' . $mysqli->error;
    file_put_contents('debug.log', $error . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

$stmt->bind_param("ii", $activationLimit, $licenseKeyId);

if ($stmt->execute()) {
    file_put_contents('debug.log', "Update successful\n", FILE_APPEND);
    
    // Check if logAction function exists
    if (function_exists('logAction')) {
        logAction("Activation limit updated: Key ID $licenseKeyId to $activationLimit");
    }
    
    echo json_encode(['success' => true]);
} else {
    $error = 'Update failed: ' . $stmt->error;
    file_put_contents('debug.log', $error . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
}

$stmt->close();
exit;
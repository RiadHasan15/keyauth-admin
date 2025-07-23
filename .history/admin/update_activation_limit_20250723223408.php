<?php
// update_activation_limit.php
header('Content-Type: application/json');

// Start session first
session_start();

// Check if admin is logged in (for AJAX requests, don't redirect)
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$licenseKeyId = isset($_POST['license_key_id']) ? intval($_POST['license_key_id']) : 0;
$activationLimit = isset($_POST['activation_limit']) ? intval($_POST['activation_limit']) : -1;

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
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

$stmt->bind_param("ii", $activationLimit, $licenseKeyId);

if ($stmt->execute()) {
    // Log the action (simple version without requiring functions.php)
    $logData = [
        'event' => "Activation limit updated: Key ID $licenseKeyId to $activationLimit",
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'time' => date('Y-m-d H:i:s')
    ];
    
    // Try to log to file if possible
    $logFile = __DIR__ . '/../data/logs.json';
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        $logs[] = $logData;
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
}

$stmt->close();
exit;
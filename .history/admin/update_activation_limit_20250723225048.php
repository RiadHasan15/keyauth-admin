<?php
// update_activation_limit.php

header('Content-Type: application/json');
require_once __DIR__ . '/../functions.php';
requireLogin();
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

try {
    $stmt = $mysqli->prepare("UPDATE license_keys SET activation_limit = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("ii", $activationLimit, $licenseKeyId);

    if ($stmt->execute()) {
        logAction("Activation limit updated: Key ID $licenseKeyId to $activationLimit");
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Update failed: " . $stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;

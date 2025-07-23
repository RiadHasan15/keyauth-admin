<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

// Check if the user is logged in
requireLogin();

// Set content type to JSON
header('Content-Type: application/json');

// Check for required POST data
if (!isset($_POST['license_key_id']) || !isset($_POST['expires_at'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$licenseKeyId = intval($_POST['license_key_id']);
$newExpiration = $_POST['expires_at'];

// Validate datetime format (basic check)
if (!strtotime($newExpiration)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Prepare and execute the update query
$stmt = $conn->prepare("UPDATE license_keys SET expires_at = ? WHERE id = ?");
$stmt->bind_param("si", $newExpiration, $licenseKeyId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Expiration updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();

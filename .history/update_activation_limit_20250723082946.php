<?php
require_once 'db.php';

// Validate input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['license_key_id'] ?? 0);
    $limit = intval($_POST['activation_limit'] ?? 0);

    if ($id <= 0 || $limit < 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE license_keys SET activation_limit = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $limit, $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);

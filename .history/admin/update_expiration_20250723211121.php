<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';   // Correct: db.php is one level above (in root)

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$license_key_id = isset($_POST['license_key_id']) ? intval($_POST['license_key_id']) : 0;
$expires_at = isset($_POST['expires_at']) && !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

if ($license_key_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid license key ID']);
    exit;
}

// Validate datetime format if provided
if ($expires_at !== null) {
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $expires_at);
    if (!$date) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    // Convert to MySQL datetime format
    $expires_at = $date->format('Y-m-d H:i:s');
}

try {
    $stmt = $mysqli->prepare("UPDATE license_keys SET expires_at = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("si", $expires_at, $license_key_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log the action
        $logMessage = $expires_at ? "Expiration updated to $expires_at for Key ID $license_key_id" : "Expiration removed for Key ID $license_key_id";
        logAction($logMessage);
        
        echo json_encode(['success' => true, 'message' => 'Expiration date updated successfully']);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

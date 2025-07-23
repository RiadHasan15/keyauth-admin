<?php
// Start output buffering to catch any output before JSON
ob_start();

// Disable error display to avoid corrupting JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Your sample response data
$response = [
    'success' => true,
    'message' => 'This is a clean JSON response test.',
    'time' => date('Y-m-d H:i:s')
];

// Get any unexpected output before JSON
$unexpectedOutput = ob_get_clean();

if (trim($unexpectedOutput) !== '') {
    // Log unexpected output for debugging
    error_log("Unexpected output before JSON: " . $unexpectedOutput);
}

// Send proper JSON headers and output JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;

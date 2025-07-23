<?php
// Check activation limit functionality
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

echo "<h2>Activation Limit Functionality Check</h2>\n";

// 1. Check database connection
if ($mysqli->connect_error) {
    echo "❌ Database connection failed: " . $mysqli->connect_error . "\n";
    exit;
} else {
    echo "✅ Database connection successful\n<br>";
}

// 2. Check license_keys table structure
$result = $mysqli->query("DESCRIBE license_keys");
if ($result) {
    echo "✅ License keys table exists\n<br>";
    echo "<strong>Table columns:</strong>\n<br>";
    $hasActivationLimit = false;
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n<br>";
        if ($row['Field'] === 'activation_limit') {
            $hasActivationLimit = true;
        }
    }
    
    if ($hasActivationLimit) {
        echo "✅ activation_limit column exists\n<br>";
    } else {
        echo "❌ activation_limit column missing - need to add it\n<br>";
        echo "<strong>Adding activation_limit column...</strong>\n<br>";
        $addColumn = $mysqli->query("ALTER TABLE license_keys ADD COLUMN activation_limit INT DEFAULT 0");
        if ($addColumn) {
            echo "✅ activation_limit column added successfully\n<br>";
        } else {
            echo "❌ Failed to add activation_limit column: " . $mysqli->error . "\n<br>";
        }
    }
} else {
    echo "❌ Failed to check table structure: " . $mysqli->error . "\n<br>";
}

// 3. Check if we have any license keys
$result = $mysqli->query("SELECT COUNT(*) as count FROM license_keys");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Found " . $row['count'] . " license keys in database\n<br>";
    
    if ($row['count'] > 0) {
        $keys = $mysqli->query("SELECT id, license_key, activation_limit FROM license_keys LIMIT 3");
        echo "<strong>Sample license keys:</strong>\n<br>";
        while ($key = $keys->fetch_assoc()) {
            echo "- ID: " . $key['id'] . ", Key: " . substr($key['license_key'], 0, 10) . "..., Limit: " . $key['activation_limit'] . "\n<br>";
        }
    }
} else {
    echo "❌ Failed to count license keys: " . $mysqli->error . "\n<br>";
}

// 4. Test logAction function
try {
    logAction("Test activation limit check");
    echo "✅ logAction function works correctly\n<br>";
} catch (Exception $e) {
    echo "❌ logAction function failed: " . $e->getMessage() . "\n<br>";
}

// 5. Check file paths
$files = [
    'functions.php' => __DIR__ . '/functions.php',
    'db.php' => __DIR__ . '/db.php',
    'config.php' => __DIR__ . '/config.php',
    'update_activation_limit.php' => __DIR__ . '/update_activation_limit.php',
    'admin/index.php' => __DIR__ . '/admin/index.php'
];

echo "<strong>File existence check:</strong>\n<br>";
foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name exists\n<br>";
    } else {
        echo "❌ $name missing at $path\n<br>";
    }
}

echo "\n<br><strong>Test completed!</strong>\n";
?>
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "keyauth"; // your DB name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$table = 'license_keys';

$sql = "DESCRIBE $table";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h3>Table structure for `$table`:</h3><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li><b>" . $row['Field'] . "</b> — Type: " . $row['Type'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "Table `$table` does not exist or has no columns.";
}

$conn->close();

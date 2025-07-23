<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "keyauth";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch HWID logs
$sql = "
    SELECT hl.id, hl.hwid, hl.logged_at, lk.license_key
    FROM hwid_logs hl
    JOIN license_keys lk ON hl.license_key_id = lk.id
    ORDER BY hl.logged_at DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>HWID Usage Logs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        table {
            border-collapse: collapse;
            width: 95%;
            margin: 20px auto;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f3f3f3;
        }
    </style>
</head>
<body>
    <h2 style="text-align:center;">HWID Usage Logs</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>License Key</th>
                <th>HWID</th>
                <th>Logged At</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['license_key']) ?></td>
                    <td><?= htmlspecialchars($row['hwid']) ?></td>
                    <td><?= htmlspecialchars($row['logged_at']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p style="text-align:center;">No HWID logs found.</p>
    <?php endif; ?>

</body>
</html>

<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check properties table structure
echo "Properties table structure:\n";
$result = mysqli_query($conn, "DESCRIBE properties");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  {$row['Field']} ({$row['Type']}) - {$row['Null']} - {$row['Key']} - Default: {$row['Default']}\n";
    }
}

echo "\n\nRecent properties:\n";
$result = mysqli_query($conn, "SELECT id, title, admin_approved FROM properties ORDER BY id DESC LIMIT 5");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "  ID: {$row['id']} | {$row['title']} | Approved: {$row['admin_approved']}\n";
    }
}

mysqli_close($conn);
?>

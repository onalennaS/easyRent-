<?php
// Quick verification script
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get recently created properties
$query = "SELECT p.id, p.title, p.admin_approved, p.status, 
          COUNT(pi.id) as image_count
          FROM properties p
          LEFT JOIN property_images pi ON p.id = pi.property_id
          WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
          GROUP BY p.id
          ORDER BY p.id DESC
          LIMIT 10";

$result = mysqli_query($conn, $query);

echo "Recently created properties:\n";
echo str_repeat("=", 80) . "\n";

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $status = isset($row['status']) ? $row['status'] : ($row['admin_approved'] ? 'approved' : 'pending');
        echo "ID: {$row['id']} | {$row['title']}\n";
        echo "  Status: $status | Images: {$row['image_count']}\n\n";
    }
} else {
    echo "No recent properties found.\n";
}

mysqli_close($conn);
?>

<?php
$conn = mysqli_connect('localhost', 'root', '', 'easyrent_db');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "=== Properties Created (ID >= 24) ===\n\n";

$result = mysqli_query($conn, "
    SELECT 
        id, 
        title, 
        admin_approved,
        (SELECT COUNT(*) FROM property_images WHERE property_id = properties.id) as image_count
    FROM properties 
    WHERE id >= 24 
    ORDER BY id
");

while($row = mysqli_fetch_assoc($result)) {
    echo "ID: {$row['id']}\n";
    echo "Title: {$row['title']}\n";
    echo "Approved: " . ($row['admin_approved'] ? 'Yes' : 'No') . "\n";
    echo "Images: {$row['image_count']}\n";
    echo "---\n";
}

mysqli_close($conn);
?>

<?php
$conn = mysqli_connect('localhost', 'root', '', 'easyrent_db');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'landlord_documents'");

if (mysqli_num_rows($result) > 0) {
    echo "✓ landlord_documents table exists!\n\n";
    
    // Show table structure
    echo "Table structure:\n";
    $columns = mysqli_query($conn, "DESCRIBE landlord_documents");
    while ($col = mysqli_fetch_assoc($columns)) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} else {
    echo "✗ landlord_documents table does NOT exist!\n";
}

mysqli_close($conn);
?>

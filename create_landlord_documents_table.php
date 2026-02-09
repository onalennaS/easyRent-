<?php
/**
 * Create landlord_documents table if it doesn't exist
 */

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "Creating landlord_documents table...\n";

// Create landlord_documents table
$create_documents_table = "
    CREATE TABLE IF NOT EXISTS landlord_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT NOT NULL,
        document_type VARCHAR(100) NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_doc (landlord_id, document_type),
        INDEX idx_landlord_id (landlord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (mysqli_query($conn, $create_documents_table)) {
    echo "✓ landlord_documents table created successfully!\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}

// Also create landlord_profiles table if it doesn't exist
echo "\nCreating landlord_profiles table...\n";

$create_profile_table = "
    CREATE TABLE IF NOT EXISTS landlord_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        postal_code VARCHAR(10) NOT NULL,
        id_number VARCHAR(20) NOT NULL,
        tax_number VARCHAR(50),
        bank_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        branch_code VARCHAR(10) NOT NULL,
        account_holder VARCHAR(255) NOT NULL,
        business_registration VARCHAR(100),
        experience_years INT DEFAULT 0,
        property_count INT DEFAULT 0,
        about TEXT,
        profile_image VARCHAR(255),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_landlord (landlord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (mysqli_query($conn, $create_profile_table)) {
    echo "✓ landlord_profiles table created successfully!\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}

// Check if rejection_reason column exists, add it if not
echo "\nChecking for rejection_reason column...\n";
$check_column = "SHOW COLUMNS FROM landlord_documents LIKE 'rejection_reason'";
$result = mysqli_query($conn, $check_column);
if (mysqli_num_rows($result) == 0) {
    $alter_table = "ALTER TABLE landlord_documents ADD COLUMN rejection_reason TEXT NULL AFTER status";
    if (mysqli_query($conn, $alter_table)) {
        echo "✓ rejection_reason column added successfully!\n";
    } else {
        echo "Error adding column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ rejection_reason column already exists.\n";
}

echo "\nDone!\n";

mysqli_close($conn);
?>

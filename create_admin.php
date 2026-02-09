<?php
/**
 * Create Admin User Script
 * This script creates an admin user in the users table
 * Run this script once to create the admin account
 */

require_once 'config/database.php';

// Admin credentials
$admin_email = 'admin@gmail.com';
$admin_password = 'Sel13183$';
$admin_username = 'admin'; // You can change this if needed

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if admin already exists
    $check_query = "SELECT id FROM users WHERE email = :email OR user_type = 'admin'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':email', $admin_email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $existing = $check_stmt->fetch();
        echo "Admin user already exists!\n";
        echo "Email: " . $admin_email . "\n";
        echo "User ID: " . $existing['id'] . "\n";
        exit;
    }
    
    // Hash the password
    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    
    // Insert admin user
    $query = "INSERT INTO users (username, email, password_hash, user_type, is_active, created_at) 
              VALUES (:username, :email, :password_hash, 'admin', 1, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $admin_username);
    $stmt->bindParam(':email', $admin_email);
    $stmt->bindParam(':password_hash', $password_hash);
    
    if ($stmt->execute()) {
        $admin_id = $conn->lastInsertId();
        echo "Admin user created successfully!\n";
        echo "User ID: " . $admin_id . "\n";
        echo "Username: " . $admin_username . "\n";
        echo "Email: " . $admin_email . "\n";
        echo "Password: " . $admin_password . "\n";
        echo "\nYou can now log in with these credentials.\n";
    } else {
        throw new Exception('Failed to create admin user');
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Admin creation error: " . $e->getMessage());
    exit(1);
}
?>

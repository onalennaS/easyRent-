<?php
/**
 * Fix User IDs Script
 * This script fixes the duplicate ID 0 issue in the users table
 * Run this script once to resolve the problem
 */

require_once 'config/database.php';

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    echo "Connected to database successfully.\n";

    // Check users with id = 0
    $check_query = "SELECT id, username, email, user_type FROM users WHERE id = 0";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute();
    $users_with_zero = $check_stmt->fetchAll();

    echo "Users with ID 0:\n";
    foreach ($users_with_zero as $user) {
        echo "- ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Type: {$user['user_type']}\n";
    }

    if (count($users_with_zero) == 0) {
        echo "No users with ID 0 found. The issue may already be resolved.\n";
        exit;
    }

    if (count($users_with_zero) > 1) {
        // Delete the tenant user with id=0, keep the admin
        $delete_query = "DELETE FROM users WHERE id = 0 AND user_type = 'tenant'";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->execute();
        echo "Deleted tenant user with ID 0.\n";
    }

    // Update the remaining user (admin) to id=1
    $update_query = "UPDATE users SET id = 1 WHERE id = 0";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->execute();
    echo "Updated admin user ID to 1.\n";

    // Now, modify the id column to be auto_increment primary key
    $alter_query = "ALTER TABLE users MODIFY COLUMN id INT AUTO_INCREMENT PRIMARY KEY";
    $conn->exec($alter_query);
    echo "Modified id column to AUTO_INCREMENT PRIMARY KEY.\n";

    // Set auto_increment to 2
    $auto_inc_query = "ALTER TABLE users AUTO_INCREMENT = 2";
    $conn->exec($auto_inc_query);
    echo "Set AUTO_INCREMENT to 2.\n";

    echo "User ID issue fixed successfully!\n";
    echo "Admin user now has ID 1, and future users will have auto-incremented IDs starting from 2.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Fix user IDs error: " . $e->getMessage());
    exit(1);
}
?>
